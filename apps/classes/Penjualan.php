<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../config.php');
require_once('../classes/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

class Penjualan extends DBConnection
{
    private $settings;
    private $error = null;

    public function __construct()
    {
        global $_settings;
        $this->settings = $_settings ?? null;
        parent::__construct();

        // Validasi session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** ======================= [ ENSURE DATABASE CONNECTION ] ======================= **/
    private function ensureConnection()
    {
        if (!$this->conn || $this->conn->connect_error) {
            // Coba buat koneksi baru atau dapatkan dari global
            global $conn; // Jika ada koneksi global

            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $this->conn = $conn;
            } else {
                // Buat koneksi baru
                require_once '../config.php'; // Sesuaikan path
                $this->conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

                if ($this->conn->connect_error) {
                    throw new Exception("Database connection failed: " . $this->conn->connect_error);
                }
            }
        }
    }

    function capture_err()
    {
        if (!$this->error)
            return false;
        else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->error;
            return json_encode($resp);
            exit;
        }
    }

    /** ======================= [ VALIDATE CSRF TOKEN ] ======================= **/
    // private function validateCSRF() {
    //     $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    //     $session_token = $_SESSION['csrf_token'] ?? '';
        
    //     if (empty($token) || empty($session_token)) {
    //         throw new Exception("CSRF token tidak ditemukan");
    //     }
        
    //     if (!hash_equals($session_token, $token)) {
    //         throw new Exception("CSRF token validation failed");
    //     }
        
    //     return true;
    // }

    /** ======================= [ SANITIZATION METHODS ] ======================= **/
    private function sanitizeString($input)
    {
        if ($input === null) return '';
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    private function sanitizeInt($input)
    {
        if ($input === null) return 0;
        return intval($input);
    }

    private function sanitizeFloat($input)
    {
        return NumericInput::money($input);
    }

    private function sanitizeQuantity($input)
    {
        return NumericInput::quantity($input, 4);
    }

    private function ppnRate()
    {
        $rate = $this->settings ? $this->settings->info('ppn_rate') : false;
        if ($rate === false || $rate === '' || !is_numeric($rate)) {
            $rate = 11;
        }

        return max(0, min(100, (float)$rate)) / 100;
    }

    private function priceWithItemPpn($price, $isPpn)
    {
        $price = (float)$price;
        return ((int)$isPpn === 1) ? round($price * (1 + $this->ppnRate())) : $price;
    }

    private function ppnFromGrossAmount($grossAmount, $isPpn)
    {
        $grossAmount = (float)$grossAmount;
        if ((int)$isPpn !== 1 || $grossAmount == 0.0) {
            return 0.0;
        }

        return round($grossAmount - ($grossAmount / (1 + $this->ppnRate())));
    }

    private function applySignedDiscount($amount, $discount, $label = 'Diskon')
    {
        $amount = (float)$amount;
        $discount = (float)$discount;

        if ($discount < 0) {
            throw new Exception("{$label} tidak boleh bernilai minus");
        }

        if ($discount > abs($amount) + 0.01) {
            throw new Exception("{$label} tidak boleh melebihi subtotal Rp " . number_format(abs($amount), 0, ',', '.'));
        }

        if ($amount < 0) {
            return $amount + $discount;
        }

        return $amount - $discount;
    }

    private function applyInvoiceDiscountToPpn(&$details, $saleDisc)
    {
        $total = 0.0;
        foreach ($details as $detail) {
            $lineBase = (float)$detail['qty'] * (float)$detail['harga'];
            $total += $this->applySignedDiscount($lineBase, (float)$detail['disc'], 'Diskon item');
        }

        $saleDisc = (float)$saleDisc;
        $discountedTotal = $this->applySignedDiscount($total, $saleDisc, 'Diskon faktur');
        $invoiceAdjustment = $discountedTotal - $total;
        $totalPpn = 0.0;
        foreach ($details as &$detail) {
            $lineBase = (float)$detail['qty'] * (float)$detail['harga'];
            $lineTotal = $this->applySignedDiscount($lineBase, (float)$detail['disc'], 'Diskon item');
            $lineAfterInvoiceDiscount = $lineTotal;
            if ($total != 0.0 && $saleDisc > 0) {
                $lineAfterInvoiceDiscount += $invoiceAdjustment * ($lineTotal / $total);
            }

            $detail['ppn_item'] = $this->ppnFromGrossAmount($lineAfterInvoiceDiscount, $detail['is_ppn'] ?? 0);
            $totalPpn += (float)$detail['ppn_item'];
        }
        unset($detail);

        return round($totalPpn);
    }

    private function sanitizeDate($input)
    {
        if (empty($input)) return null;

        $timestamp = strtotime($input);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function currentUserLevel()
    {
        if (function_exists('current_user_level')) {
            return current_user_level();
        }

        return intval($_SESSION['userdata']['level_id'] ?? $_SESSION['level_id'] ?? 0);
    }

    private function currentUserId()
    {
        return $this->sanitizeInt($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    private function findMinusAuthorizer($password)
    {
        $password = (string)$password;
        if ($password === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT u.id, u.firstname, u.username, u.level_id, u.password_bcrypt,
                    COALESCE(ul.level_nama, '') AS level_nama
             FROM users u
             LEFT JOIN user_level ul ON ul.level_id = u.level_id
             WHERE u.status = 1
               AND (
                    u.level_id IN (1, 4)
                    OR UPPER(TRIM(COALESCE(ul.level_nama, ''))) = 'OWNER'
               )"
        );
        if (!$stmt) {
            throw new Exception("Prepare validasi password gagal: " . $this->conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['password_bcrypt']) && password_verify($password, $row['password_bcrypt'])) {
                unset($row['password_bcrypt']);
                $stmt->close();
                return $row;
            }
        }
        $stmt->close();

        return null;
    }

    private function formatMinusQty($qty)
    {
        $formatted = number_format((float)$qty, 4, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function describeMinusItems(array $details)
    {
        $minusItems = [];
        foreach ($details as $detail) {
            $qty = (float)($detail['qty'] ?? 0);
            if ($qty >= 0) {
                continue;
            }

            $productName = trim((string)($detail['nama_produk'] ?? ''));
            if ($productName === '') {
                $productName = 'Produk #' . (int)($detail['produk_id'] ?? 0);
            }
            $minusItems[] = $productName . ' (Qty ' . $this->formatMinusQty($qty) . ')';
        }

        return $minusItems ? implode(', ', $minusItems) : 'Total transaksi minus';
    }

    private function currentOperatorDescription()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return 'User tidak diketahui';
        }

        $stmt = $this->conn->prepare(
            "SELECT u.firstname, u.username, COALESCE(ul.level_nama, '') AS level_nama
             FROM users u
             LEFT JOIN user_level ul ON ul.level_id = u.level_id
             WHERE u.id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return 'User #' . $userId;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return 'User #' . $userId;
        }

        $name = trim((string)($user['firstname'] ?? '')) ?: ('User #' . $userId);
        $username = trim((string)($user['username'] ?? ''));
        $role = trim((string)($user['level_nama'] ?? ''));

        return $name
            . ($username !== '' ? ' [' . $username . ']' : '')
            . ($role !== '' ? ' (' . $role . ')' : '');
    }

    private function logMinusAuthorization(array $authorizer, $saleId, $invoice, array $details, $saleTotal, $transactionType)
    {
        $authorizerId = (int)($authorizer['id'] ?? 0);
        if ($authorizerId <= 0) {
            return;
        }

        $description = $transactionType
            . ' | Invoice: ' . $invoice
            . ' | Item minus: ' . $this->describeMinusItems($details)
            . ' | Total: Rp ' . number_format((float)$saleTotal, 0, ',', '.')
            . ' | Operator: ' . $this->currentOperatorDescription();

        $this->logActivity(
            "Otorisasi Qty Minus",
            "sale",
            (int)$saleId,
            $description,
            $authorizerId
        );
    }

    private function hasMinusTransaction(array $details, $saleTotal)
    {
        if ((float)$saleTotal < 0) {
            return true;
        }

        foreach ($details as $detail) {
            if ((float)($detail['qty'] ?? 0) < 0) {
                return true;
            }
        }

        return false;
    }

    private function resolveSalesIdFromRequest()
    {
        $sessionUserId = $this->currentUserId();
        if ($sessionUserId <= 0) {
            throw new Exception("User tidak terautentikasi");
        }

        if ($this->currentUserLevel() === 3) {
            return $sessionUserId;
        }

        $salesId = $this->sanitizeInt($_POST['sales_id'] ?? $_POST['user_id'] ?? 0);
        if ($salesId <= 0) {
            throw new Exception("Pilih sales terlebih dahulu");
        }

        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ? AND level_id = 3 AND status = 1 LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare validasi sales gagal: " . $this->conn->error);
        }

        $stmt->bind_param("i", $salesId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception("Sales yang dipilih tidak valid atau tidak aktif");
        }

        return $salesId;
    }

    private function calculateDueDate($saleDate, $bayarId, $saleJt, $daysInput)
    {
        if ((int)$bayarId !== 2) {
            return null;
        }

        $days = $this->sanitizeInt($daysInput);
        if ($days < 0 || $days > 3650) {
            throw new Exception("Tempo hari harus berada di antara 1 sampai 3650 hari");
        }
        if ($days > 0) {
            return date('Y-m-d', strtotime($saleDate . " +{$days} days"));
        }

        return $saleJt;
    }

    private function normalizeSaleChannel($channel)
    {
        $channel = strtolower(trim((string)$channel));
        if (!in_array($channel, ['sales', 'online'], true)) {
            throw new Exception("Kanal transaksi harus Sales atau Sales Online");
        }

        return $channel;
    }

    private function normalizeSalesDetails(array $produkIds, array $qtys, array $hargas, array $discounts, array $isis)
    {
        $details = [];
        $grossTotal = 0.0;

        foreach ($produkIds as $k => $pidRaw) {
            $produkId = $this->sanitizeInt($pidRaw);
            if ($produkId <= 0) {
                continue;
            }

            $qty = $this->sanitizeQuantity($qtys[$k] ?? 0);
            $harga = $this->sanitizeFloat($hargas[$k] ?? 0);
            $disc = $this->sanitizeFloat($discounts[$k] ?? 0);
            $isi = $this->sanitizeInt($isis[$k] ?? 1);

            if ($qty == 0) {
                throw new Exception("Quantity produk tidak valid pada baris " . ($k + 1));
            }

            $produkStmt = $this->conn->prepare("SELECT nama_produk, harga_beli, harga_newmodal, harga_sales, harga_kasir, is_ppn FROM produk WHERE id = ? AND status = 1 LIMIT 1");
            if (!$produkStmt) {
                throw new Exception("Prepare produk query gagal: " . $this->conn->error);
            }

            $produkStmt->bind_param("i", $produkId);
            $produkStmt->execute();
            $produk = $produkStmt->get_result()->fetch_assoc();
            $produkStmt->close();

            if (!$produk) {
                throw new Exception("Produk pada baris " . ($k + 1) . " tidak ditemukan atau nonaktif");
            }

            $isPpn = (int)($produk['is_ppn'] ?? 0);
            $masterPrice = (float)($produk['harga_sales'] ?: $produk['harga_kasir'] ?: 0);
            if ($isPpn === 1 && $masterPrice > 0 && abs($harga - $masterPrice) < 0.01) {
                $harga = $this->priceWithItemPpn($harga, 1);
            }

            $newModal = (float)($produk['harga_newmodal'] ?? 0);
            if ($harga < $newModal) {
                throw new Exception("Harga jual {$produk['nama_produk']} tidak boleh kurang dari New Modal Rp " . number_format($newModal, 0, ',', '.'));
            }

            $lineBase = $qty * $harga;
            $lineTotal = $this->applySignedDiscount($lineBase, $disc, 'Diskon item baris ' . ($k + 1));
            $grossTotal += $lineTotal;

            $details[] = [
                'produk_id' => $produkId,
                'nama_produk' => $produk['nama_produk'],
                'qty' => $qty,
                'harga' => $harga,
                'disc' => $disc,
                'capital' => $newModal,
                'purchase_cost' => (float)($produk['harga_beli'] ?? 0),
                'is_ppn' => $isPpn,
                'ppn_item' => $this->ppnFromGrossAmount($lineTotal, $isPpn),
                'isi' => $isi > 0 ? $isi : 1,
            ];
        }

        if (empty($details)) {
            throw new Exception("Tidak ada produk yang valid untuk disimpan");
        }

        return [$details, $grossTotal];
    }

    /** Terapkan mutasi stok; qty positif mengurangi, qty minus (retur) menambah. */
    private function applyInventoryDetails(array $details)
    {
        $remaining = [];
        foreach ($details as $detail) {
            $produkId = (int)($detail['produk_id'] ?? 0);
            $qty = (float)($detail['qty'] ?? $detail['sale_qty'] ?? 0);
            $isi = max(1, (int)($detail['isi'] ?? 1));
            if ($produkId <= 0 || $qty == 0.0) {
                throw new Exception('Detail stok transaksi tidak valid');
            }

            $stockQty = $qty * $isi;

            if ($stockQty > 0) {
                $stmt = $this->conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ? AND status = 1 AND stok >= ?");
                if (!$stmt) {
                    throw new Exception('Prepare pengurangan stok gagal: ' . $this->conn->error);
                }
                $stmt->bind_param('did', $stockQty, $produkId, $stockQty);
            } else {
                $stmt = $this->conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ? AND status = 1");
                if (!$stmt) {
                    throw new Exception('Prepare pengembalian stok gagal: ' . $this->conn->error);
                }
                $stmt->bind_param('di', $stockQty, $produkId);
            }

            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception("Stok produk #{$produkId} tidak mencukupi atau produknya sudah nonaktif");
            }
            $stmt->close();

            $stockStmt = $this->conn->prepare("SELECT stok FROM produk WHERE id = ? LIMIT 1");
            $stockStmt->bind_param('i', $produkId);
            $stockStmt->execute();
            $stockRow = $stockStmt->get_result()->fetch_assoc();
            $stockStmt->close();
            $remaining[$produkId] = (float)($stockRow['stok'] ?? 0);
        }

        return $remaining;
    }

    /** Pulihkan stok dari detail lama sebelum edit/hapus. */
    private function restoreInventoryForSale($saleId)
    {
        $stmt = $this->conn->prepare("SELECT produk_id, sale_qty, COALESCE(NULLIF(isi, 0), 1) AS isi FROM sale_det WHERE sale_id = ? ORDER BY noid");
        if (!$stmt) {
            throw new Exception('Prepare detail pemulihan stok gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($details as $detail) {
            $produkId = (int)$detail['produk_id'];
            $qty = (float)$detail['sale_qty'];
            $stockQty = $qty * max(1, (int)$detail['isi']);
            $update = $this->conn->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
            if (!$update) {
                throw new Exception('Prepare pemulihan stok gagal: ' . $this->conn->error);
            }
            $update->bind_param('di', $stockQty, $produkId);
            if (!$update->execute() || $update->affected_rows !== 1) {
                $update->close();
                throw new Exception("Gagal memulihkan stok produk #{$produkId}");
            }
            $update->close();
        }
    }

    private function markStockApplied($saleId, $applied)
    {
        $value = $applied ? 1 : 0;
        $stmt = $this->conn->prepare("UPDATE sale SET stock_applied = ? WHERE saleid = ?");
        if (!$stmt) {
            throw new Exception('Prepare penanda stok gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('ii', $value, $saleId);
        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui penanda stok: ' . $stmt->error);
        }
        $stmt->close();
    }

    private function assertNoPostedReturns($saleId, $action)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM sale_retur WHERE saleid = ? AND post = 1");
        if (!$stmt) {
            throw new Exception('Prepare validasi retur gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
        if ($total > 0) {
            throw new Exception("Transaksi sudah memiliki retur yang diposting dan tidak dapat {$action}");
        }
    }

    private function nextPiutangNumber()
    {
        $prefix = 'PUT-' . date('Y-m-');
        $like = $prefix . '%';
        $stmt = $this->conn->prepare("SELECT no_piutang FROM piutang_pelanggan WHERE no_piutang LIKE ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) {
            throw new Exception('Prepare nomor piutang gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $next = 1;
        if (!empty($row['no_piutang']) && preg_match('/(\d+)$/', $row['no_piutang'], $matches)) {
            $next = ((int)$matches[1]) + 1;
        }
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function piutangStatus($total, $received, $dueDate)
    {
        $remaining = max((float)$total - (float)$received, 0);
        if ($remaining <= 0) {
            return 'LUNAS';
        }
        if (!empty($dueDate) && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return 'OVERDUE';
        }
        return ((float)$received > 0) ? 'SEBAGIAN' : 'BELUM';
    }

    /** Sinkronkan piutang transaksi Sales/Online kredit di transaksi DB yang sama. */
    private function syncSalesReceivable($invoice, $oldInvoice, $custId, $saleDate, $dueDate, $saleTotal, $bayarId, $catatan, $userId)
    {
        $invoice = trim((string)$invoice);
        $oldInvoice = trim((string)$oldInvoice);
        $shouldCreate = ((int)$bayarId === 2 && (float)$saleTotal > 0);
        if (!$shouldCreate) {
            $this->removeSalesReceivable($oldInvoice ?: $invoice, true);
            if ($oldInvoice !== '' && $oldInvoice !== $invoice) {
                $this->removeSalesReceivable($invoice, true);
            }
            return null;
        }

        if ((int)$custId <= 0 || empty($dueDate)) {
            throw new Exception('Pelanggan dan jatuh tempo wajib untuk penjualan kredit');
        }

        $tanggalPiutang = date('Y-m-d', strtotime($saleDate));
        $tanggalJatuhTempo = date('Y-m-d', strtotime($dueDate));
        $lookupOld = $oldInvoice !== '' ? $oldInvoice : $invoice;
        $stmt = $this->conn->prepare("SELECT id, total_diterima FROM piutang_pelanggan WHERE ref_penjualan = ? OR ref_penjualan = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
        if (!$stmt) {
            throw new Exception('Prepare cek piutang Sales gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('ss', $invoice, $lookupOld);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $received = (float)($existing['total_diterima'] ?? 0);
        if ($received > (float)$saleTotal) {
            throw new Exception('Total transaksi tidak boleh lebih kecil dari penerimaan piutang yang sudah tercatat');
        }
        $remaining = max((float)$saleTotal - $received, 0);
        $status = $this->piutangStatus($saleTotal, $received, $tanggalJatuhTempo);
        $description = trim((string)$catatan) !== '' ? $catatan : "Piutang otomatis dari penjualan {$invoice}";

        if ($existing) {
            $piutangId = (int)$existing['id'];
            $stmt = $this->conn->prepare("UPDATE piutang_pelanggan SET tanggal_piutang = ?, tanggal_jatuh_tempo = ?, pelanggan_id = ?, total_piutang = ?, sisa_piutang = ?, status = ?, keterangan = ?, ref_penjualan = ?, user_id = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Prepare update piutang Sales gagal: ' . $this->conn->error);
            }
            $stmt->bind_param('ssiddsssii', $tanggalPiutang, $tanggalJatuhTempo, $custId, $saleTotal, $remaining, $status, $description, $invoice, $userId, $piutangId);
        } else {
            $noPiutang = $this->nextPiutangNumber();
            $stmt = $this->conn->prepare("INSERT INTO piutang_pelanggan (no_piutang, tanggal_piutang, tanggal_jatuh_tempo, pelanggan_id, total_piutang, total_diterima, sisa_piutang, status, keterangan, ref_penjualan, user_id, tgl_input) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                throw new Exception('Prepare insert piutang Sales gagal: ' . $this->conn->error);
            }
            $stmt->bind_param('sssiddsssi', $noPiutang, $tanggalPiutang, $tanggalJatuhTempo, $custId, $saleTotal, $remaining, $status, $description, $invoice, $userId);
        }

        if (!$stmt->execute()) {
            throw new Exception('Sinkronisasi piutang Sales gagal: ' . $stmt->error);
        }
        $stmt->close();
        return ['sisa_piutang' => $remaining, 'status' => $status];
    }

    private function removeSalesReceivable($invoice, $ignoreMissing = false)
    {
        $invoice = trim((string)$invoice);
        if ($invoice === '') {
            return;
        }
        $stmt = $this->conn->prepare("SELECT p.id, COUNT(r.id) AS total_penerimaan FROM piutang_pelanggan p LEFT JOIN penerimaan_piutang r ON r.piutang_id = p.id WHERE p.ref_penjualan = ? GROUP BY p.id LIMIT 1 FOR UPDATE");
        if (!$stmt) {
            throw new Exception('Prepare cek piutang Sales gagal: ' . $this->conn->error);
        }
        $stmt->bind_param('s', $invoice);
        $stmt->execute();
        $piutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$piutang) {
            return;
        }
        if ((int)$piutang['total_penerimaan'] > 0) {
            throw new Exception('Transaksi tidak dapat diubah/dihapus karena piutangnya sudah memiliki penerimaan');
        }
        $piutangId = (int)$piutang['id'];
        $stmt = $this->conn->prepare("DELETE FROM piutang_pelanggan WHERE id = ?");
        $stmt->bind_param('i', $piutangId);
        if (!$stmt->execute()) {
            throw new Exception('Hapus piutang Sales gagal: ' . $stmt->error);
        }
        $stmt->close();
    }

    private function applySalesScope(&$whereParts, &$params, &$types)
    {
        if ($this->currentUserLevel() !== 3) {
            return;
        }

        $userId = $this->currentUserId();
        $whereParts[] = "s.user_id = ?";
        $params[] = $userId > 0 ? $userId : -1;
        $types .= "i";
    }

    /** ======================= [ GET DATA PENJUALAN - FIXED ] ======================= **/
    function get_data_penjualan()
    {
        try {
            $this->ensureConnection();

            $draw   = intval($_POST['draw'] ?? 1);
            $start  = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = trim($_POST['search']['value'] ?? '');

            // Filter dengan sanitization
            $tgl_awal = $this->sanitizeDate($_POST['tgl_awal'] ?? date('Y-m-01'));
            $tgl_akhir = $this->sanitizeDate($_POST['tgl_akhir'] ?? date('Y-m-d'));
            $jenis = $this->sanitizeInt($_POST['jenis'] ?? '');
            $pelanggan_id = $this->sanitizeInt($_POST['pelanggan_id'] ?? 0);

            // Validasi tanggal
            if (!strtotime($tgl_awal) || !strtotime($tgl_akhir)) {
                throw new Exception("Format tanggal tidak valid");
            }

            // Sorting
            $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
            $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc');
            $orderDir = ($orderDir === 'desc') ? 'DESC' : 'ASC';
            $columns = [
                0 => 's.saleid',
                1 => 's.sale_date',
                2 => 's.sale_invoice',
                3 => 'u.firstname',
                4 => 's.bayar_id',
                5 => 'p.nama',
                6 => 's.sale_total',
                7 => 's.sale_status'
            ];
            $orderColumn = $columns[$orderColumnIndex] ?? 's.saleid';

            /* =====================================================
               WHERE builder
               ===================================================== */
            $scopeWhereParts = [
                "s.sale_status != 'DELETED'",
                "s.sale_channel IN ('sales', 'online')"
            ]; // Menu Penjualan hanya menampilkan riwayat penjualan Sales
            $scopeParams = [];
            $scopeTypes = "";
            $this->applySalesScope($scopeWhereParts, $scopeParams, $scopeTypes);

            $whereParts = $scopeWhereParts;
            $params = $scopeParams;
            $types = $scopeTypes;

            // 🔍 Filter global pencarian
            if (!empty($search)) {
                $whereParts[] = "(s.sale_invoice LIKE ? OR s.sale_nofak LIKE ? OR p.nama LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                $types .= "sss";
            }

            // 📅 Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(s.sale_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // 💰 Filter jenis transaksi
            if (!empty($jenis)) {
                $whereParts[] = "s.bayar_id = ?";
                $params[] = $jenis;
                $types .= "i";
            }

            // 👤 Filter pelanggan
            if ($pelanggan_id > 0) {
                $whereParts[] = "s.cust_id = ?";
                $params[] = $pelanggan_id;
                $types .= "i";
            }

            // Gabungkan kondisi WHERE
            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            /* =====================================================
               Hitung total record
               ===================================================== */
            $totalWhere = " WHERE " . implode(" AND ", $scopeWhereParts);
            $totalRecords = 0;
            $totalSql = "SELECT COUNT(*) AS cnt FROM sale s $totalWhere";
            if ($stmtTotal = $this->prepare($totalSql)) {
                if (!empty($scopeParams)) {
                    $stmtTotal->bind_param($scopeTypes, ...$scopeParams);
                }
                $stmtTotal->execute();
                $resultTotal = $stmtTotal->get_result();
                $totalRecords = $resultTotal->fetch_assoc()['cnt'] ?? 0;
                $stmtTotal->close();
            }

            /* =====================================================
               Hitung filtered record
               ===================================================== */
            $countSql = "SELECT COUNT(*) AS cnt 
                         FROM sale s 
                         LEFT JOIN pelanggan p ON p.id = s.cust_id
                         $where";

            $recordsFiltered = 0;
            if ($stmtCount = $this->prepare($countSql)) {
                if (!empty($params)) {
                    $stmtCount->bind_param($types, ...$params);
                }
                $stmtCount->execute();
                $resultCount = $stmtCount->get_result();
                $recordsFiltered = $resultCount->fetch_assoc()['cnt'] ?? 0;
                $stmtCount->close();
            }

            /* =====================================================
               Query utama (dengan pagination)
               ===================================================== */
            $sql = "SELECT 
                        s.saleid,
                        s.sale_date,
                        s.sale_invoice,
                        s.sale_channel,
                        s.sale_nofak,
                        s.bayar_id,
                        s.sale_total,
                        s.sale_status,
                        s.status,
                        COALESCE(u.firstname, u.username, '-') AS sales_name,
                        p.nama AS pelanggan_name,
                        p.alamat AS pelanggan_alamat
                    FROM sale s
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    LEFT JOIN users u ON u.id = s.user_id
                    $where
                    ORDER BY $orderColumn $orderDir
                    LIMIT ?, ?";

            $paramsPagination = $params;
            $paramsPagination[] = $start;
            $paramsPagination[] = $length;
            $typesPagination = $types . "ii";

            $data = [];
            if ($stmt = $this->prepare($sql)) {
                if (!empty($paramsPagination)) {
                    $stmt->bind_param($typesPagination, ...$paramsPagination);
                }
                $stmt->execute();
                $res = $stmt->get_result();

                /* =====================================================
                   Format output untuk DataTables
                   ===================================================== */
                $no = $start + 1;
                while ($r = $res->fetch_assoc()) {
                    $r['no'] = $no++;
                    $saleTimestamp = strtotime($r['sale_date'] ?? '');
                    $r['can_edit'] = $r['sale_status'] === 'ACTIVE'
                        && ($this->currentUserLevel() !== 3
                            || ($saleTimestamp && (time() - $saleTimestamp) <= 86400));
                    $r['sale_date'] = date("d/m/Y", strtotime($r['sale_date']));
                    $data[] = $r;
                }
                $stmt->close();
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            ]);
        } catch (Exception $e) {
            error_log("[GET_DATA_PENJUALAN ERROR] " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "draw" => $draw ?? 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Terjadi kesalahan sistem: " . $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ RIWAYAT TRANSAKSI SALES & KASIR ] ======================= **/
    function get_riwayat_transaksi()
    {
        try {
            $this->ensureConnection();

            $draw   = intval($_POST['draw'] ?? 1);
            $start  = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = trim($_POST['search']['value'] ?? '');

            $tgl_awal = $this->sanitizeDate($_POST['tgl_awal'] ?? '');
            $tgl_akhir = $this->sanitizeDate($_POST['tgl_akhir'] ?? '');
            $source = strtolower($this->sanitizeString($_POST['source'] ?? 'all'));
            $bayar_id = $this->sanitizeInt($_POST['bayar_id'] ?? 0);
            $status = strtoupper($this->sanitizeString($_POST['status'] ?? ''));
            $pelanggan_id = $this->sanitizeInt($_POST['pelanggan_id'] ?? 0);
            $petugas_id = $this->sanitizeInt($_POST['petugas_id'] ?? 0);
            $payment_method = strtolower($this->sanitizeString($_POST['payment_method'] ?? ''));

            $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 2);
            $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            $columns = [
                0 => 's.saleid',
                1 => 's.sale_invoice',
                2 => 's.sale_date',
                3 => 's.sale_invoice',
                4 => 'p.nama',
                5 => 'u.firstname',
                6 => 's.bayar_id',
                7 => 's.payment_method',
                8 => 's.sale_total',
                9 => 's.sale_status'
            ];
            $orderColumn = $columns[$orderColumnIndex] ?? 's.sale_date';

            $baseWhereParts = [];
            $baseParams = [];
            $baseTypes = '';

            if ($status !== '' && in_array($status, ['ACTIVE', 'VOID', 'DELETED'], true)) {
                $baseWhereParts[] = "s.sale_status = ?";
                $baseParams[] = $status;
                $baseTypes .= 's';
            } else {
                $baseWhereParts[] = "s.sale_status != 'DELETED'";
            }

            $whereParts = $baseWhereParts;
            $params = $baseParams;
            $types = $baseTypes;

            if ($source === 'sales') {
                $whereParts[] = "s.sale_channel = 'sales'";
            } elseif ($source === 'online') {
                $whereParts[] = "s.sale_channel = 'online'";
            } elseif ($source === 'kasir') {
                $whereParts[] = "s.sale_channel = 'kasir'";
            }

            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "DATE(s.sale_date) BETWEEN ? AND ?";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= 'ss';
            } elseif (!empty($tgl_awal)) {
                $whereParts[] = "DATE(s.sale_date) >= ?";
                $params[] = $tgl_awal;
                $types .= 's';
            } elseif (!empty($tgl_akhir)) {
                $whereParts[] = "DATE(s.sale_date) <= ?";
                $params[] = $tgl_akhir;
                $types .= 's';
            }

            if ($bayar_id > 0) {
                $whereParts[] = "s.bayar_id = ?";
                $params[] = $bayar_id;
                $types .= 'i';
            }

            if ($pelanggan_id > 0) {
                $whereParts[] = "s.cust_id = ?";
                $params[] = $pelanggan_id;
                $types .= 'i';
            }

            if ($petugas_id > 0) {
                $whereParts[] = "s.user_id = ?";
                $params[] = $petugas_id;
                $types .= 'i';
            }

            if ($payment_method !== '') {
                $whereParts[] = "LOWER(COALESCE(s.payment_method, '')) = ?";
                $params[] = $payment_method;
                $types .= 's';
            }

            if (!empty($search)) {
                $whereParts[] = "(s.sale_invoice LIKE ? OR s.sale_nofak LIKE ? OR p.nama LIKE ? OR u.firstname LIKE ? OR u.username LIKE ? OR s.catatan LIKE ?)";
                $searchTerm = "%{$search}%";
                array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
                $types .= 'ssssss';
            }

            $where = " WHERE " . implode(" AND ", $whereParts);
            $baseWhere = " WHERE " . implode(" AND ", $baseWhereParts);

            $totalRecords = 0;
            $totalSql = "SELECT COUNT(*) AS cnt FROM sale s {$baseWhere}";
            if ($stmtTotal = $this->prepare($totalSql)) {
                if (!empty($baseParams)) {
                    $stmtTotal->bind_param($baseTypes, ...$baseParams);
                }
                $stmtTotal->execute();
                $totalRecords = $stmtTotal->get_result()->fetch_assoc()['cnt'] ?? 0;
                $stmtTotal->close();
            }

            $countSql = "SELECT COUNT(*) AS cnt
                         FROM sale s
                         LEFT JOIN pelanggan p ON p.id = s.cust_id
                         LEFT JOIN users u ON u.id = s.user_id
                         {$where}";

            $recordsFiltered = 0;
            if ($stmtCount = $this->prepare($countSql)) {
                if (!empty($params)) {
                    $stmtCount->bind_param($types, ...$params);
                }
                $stmtCount->execute();
                $recordsFiltered = $stmtCount->get_result()->fetch_assoc()['cnt'] ?? 0;
                $stmtCount->close();
            }

            $summary = [
                'jumlah_transaksi' => 0,
                'total_nominal' => 0,
                'total_sales' => 0,
                'total_online' => 0,
                'total_kasir' => 0
            ];
            $summarySql = "SELECT
                               COUNT(*) AS jumlah_transaksi,
                               COALESCE(SUM(s.sale_total), 0) AS total_nominal,
                               SUM(CASE WHEN s.sale_channel = 'sales' THEN 1 ELSE 0 END) AS total_sales,
                               SUM(CASE WHEN s.sale_channel = 'online' THEN 1 ELSE 0 END) AS total_online,
                               SUM(CASE WHEN s.sale_channel = 'kasir' THEN 1 ELSE 0 END) AS total_kasir
                           FROM sale s
                           LEFT JOIN pelanggan p ON p.id = s.cust_id
                           LEFT JOIN users u ON u.id = s.user_id
                           {$where}";
            if ($stmtSummary = $this->prepare($summarySql)) {
                if (!empty($params)) {
                    $stmtSummary->bind_param($types, ...$params);
                }
                $stmtSummary->execute();
                $summaryRow = $stmtSummary->get_result()->fetch_assoc();
                if ($summaryRow) {
                    $summary = [
                        'jumlah_transaksi' => (int)($summaryRow['jumlah_transaksi'] ?? 0),
                        'total_nominal' => (float)($summaryRow['total_nominal'] ?? 0),
                        'total_sales' => (int)($summaryRow['total_sales'] ?? 0),
                        'total_online' => (int)($summaryRow['total_online'] ?? 0),
                        'total_kasir' => (int)($summaryRow['total_kasir'] ?? 0)
                    ];
                }
                $stmtSummary->close();
            }

            $sql = "SELECT
                        s.saleid,
                        s.sale_date,
                        s.sale_invoice,
                        s.sale_channel,
                        s.sale_nofak,
                        s.bayar_id,
                        s.payment_method,
                        s.sale_total,
                        s.sale_status,
                        s.catatan,
                        p.nama AS pelanggan_name,
                        p.kode AS pelanggan_kode,
                        COALESCE(NULLIF(u.firstname, ''), u.username, '-') AS petugas_name,
                        CASE s.sale_channel
                            WHEN 'online' THEN 'SALES ONLINE'
                            WHEN 'kasir' THEN 'KASIR'
                            ELSE 'SALES'
                        END AS sumber_transaksi
                    FROM sale s
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    LEFT JOIN users u ON u.id = s.user_id
                    {$where}
                    ORDER BY {$orderColumn} {$orderDir}, s.saleid DESC
                    LIMIT ?, ?";

            $dataParams = $params;
            $dataTypes = $types . 'ii';
            $dataParams[] = $start;
            $dataParams[] = $length;

            $data = [];
            if ($stmt = $this->prepare($sql)) {
                $stmt->bind_param($dataTypes, ...$dataParams);
                $stmt->execute();
                $res = $stmt->get_result();
                $no = $start + 1;
                while ($row = $res->fetch_assoc()) {
                    $row['no'] = $no++;
                    $row['tanggal_display'] = !empty($row['sale_date']) ? date('d/m/Y', strtotime($row['sale_date'])) : '-';
                    if ((int)$row['bayar_id'] === 1) {
                        $row['jenis_bayar'] = 'Tunai';
                    } elseif ((int)$row['bayar_id'] === 2) {
                        $row['jenis_bayar'] = 'Kredit';
                    } else {
                        $row['jenis_bayar'] = 'Lainnya';
                    }
                    $row['pelanggan_display'] = $row['pelanggan_name'] ?: 'Umum';
                    $data[] = $row;
                }
                $stmt->close();
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $recordsFiltered,
                'summary' => $summary,
                'data' => $data
            ]);
        } catch (Exception $e) {
            error_log("[GET_RIWAYAT_TRANSAKSI ERROR] " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'draw' => $draw ?? 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'summary' => [
                    'jumlah_transaksi' => 0,
                    'total_nominal' => 0,
                    'total_sales' => 0,
                    'total_online' => 0,
                    'total_kasir' => 0
                ],
                'data' => [],
                'error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ SAVE PENJUALAN - SECURE ] ======================= **/
    function save_penjualan()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

            if (!in_array($this->currentUserLevel(), [1, 3, 4], true)) {
                throw new Exception("Anda tidak memiliki akses untuk membuat penjualan Sales");
            }

            $created_by = $this->currentUserId();
            $user_id = $this->resolveSalesIdFromRequest();

            // Sanitize input
            $sale_date    = $this->sanitizeDate($_POST['sale_date'] ?? date("Y-m-d"));
            $sale_invoice = $this->sanitizeString($_POST['sale_invoice'] ?? '');
            $sale_channel = $this->normalizeSaleChannel($_POST['sale_channel'] ?? 'sales');
            $sale_nofak   = $this->sanitizeString($_POST['sale_nofak'] ?? '');
            $cust_id      = $this->sanitizeInt($_POST['cust_id'] ?? 0);
            $bayar_id     = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $sale_jt      = !empty($_POST['sale_jt']) ? $this->sanitizeDate($_POST['sale_jt']) : null;
            $sale_jt      = $this->calculateDueDate($sale_date, $bayar_id, $sale_jt, $_POST['sale_jt_days'] ?? 0);
            $sale_disc    = $this->sanitizeFloat($_POST['sale_disc'] ?? 0);
            $sale_ppn     = 0.0;
            $sale_total   = $this->sanitizeFloat($_POST['sale_total'] ?? 0);
            $catatan      = $this->sanitizeString($_POST['catatan'] ?? '');
            $minus_password = (string)($_POST['minus_password'] ?? '');

            // Debug log untuk input
            error_log("Save Penjualan - Input: cust_id=$cust_id, sales_id=$user_id, created_by=$created_by, total=$sale_total");

            // Validasi input required
            if (empty($sale_date)) {
                throw new Exception("Tanggal faktur harus diisi");
            }

            if ($cust_id <= 0) {
                throw new Exception("Pilih pelanggan terlebih dahulu");
            }

            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("Tidak ada produk dalam penjualan");
            }

            [$normalizedDetails, $grossTotal] = $this->normalizeSalesDetails(
                $_POST['produk_id'],
                $_POST['qty'] ?? [],
                $_POST['harga'] ?? [],
                $_POST['sale_pdisc'] ?? [],
                $_POST['isi'] ?? []
            );
            $sale_total = $this->applySignedDiscount($grossTotal, $sale_disc, 'Diskon faktur');
            $sale_ppn = $this->applyInvoiceDiscountToPpn($normalizedDetails, $sale_disc);

            if ($sale_total == 0) {
                throw new Exception("Total penjualan tidak valid");
            }

            $isMinusTransaction = $this->hasMinusTransaction($normalizedDetails, $sale_total);
            $minusAuthorizer = null;
            if ($isMinusTransaction) {
                $minusAuthorizer = $this->findMinusAuthorizer($minus_password);
                if (!$minusAuthorizer) {
                    throw new Exception("Password Owner/Admin/Supervisor wajib dan harus benar untuk penjualan sales dengan quantity minus");
                }
            }

            if ($bayar_id === 2 && empty($sale_jt)) {
                throw new Exception("Jatuh tempo wajib diisi untuk penjualan kredit");
            }
            $sale_credit = $bayar_id === 2 && $sale_total > 0 ? $sale_total : 0.0;
            $sale_paid = $sale_credit > 0 ? 0 : 1;

            // ALWAYS generate fresh unique invoice on server (ignore form value to prevent duplicates)
            $sale_invoice = $this->generate_invoice_unique();
            error_log("Generated fresh invoice: $sale_invoice");

            // Mulai transaksi dengan pengecekan koneksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            /** ======================= INSERT HEADER PENJUALAN ======================= **/
            $sql = "INSERT INTO sale (
                        sale_date, sale_invoice, sale_channel, sale_nofak, cust_id, bayar_id, 
                        sale_jt, sale_disc, sale_ppn, sale_total, sale_credit, sale_paid, catatan, 
                        user_id, created_by, sale_status, status, date_created
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, NOW())";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement gagal: " . $this->conn->error);
            }

            // Bind parameters dengan type yang benar
            $stmt->bind_param(
                "ssssiisddddisii",
                $sale_date,
                $sale_invoice,
                $sale_channel,
                $sale_nofak,
                $cust_id,
                $bayar_id,
                $sale_jt,
                $sale_disc,
                $sale_ppn,
                $sale_total,
                $sale_credit,
                $sale_paid,
                $catatan,
                $user_id,
                $created_by
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute statement gagal: " . $stmt->error);
            }

            $saleid = $this->conn->insert_id;
            $stmt->close();

            /** ======================= INSERT DETAIL PRODUK DENGAN VALIDASI ======================= **/
            $detail_sql = "INSERT INTO sale_det 
                    (sale_id, produk_id, sale_qty, sale_harga, sale_pdisc, sale_capital, sale_purchase_cost, ppn_item, is_ppn, isi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $detail = $this->conn->prepare($detail_sql);
            if (!$detail) {
                throw new Exception("Prepare detail gagal: " . $this->conn->error);
            }

            $success_count = 0;

            foreach ($normalizedDetails as $detailRow) {
                $detail->bind_param(
                    "iiddddddii",
                    $saleid,
                    $detailRow['produk_id'],
                    $detailRow['qty'],
                    $detailRow['harga'],
                    $detailRow['disc'],
                    $detailRow['capital'],
                    $detailRow['purchase_cost'],
                    $detailRow['ppn_item'],
                    $detailRow['is_ppn'],
                    $detailRow['isi']
                );

                if (!$detail->execute()) {
                    throw new Exception("Execute detail gagal: " . $detail->error);
                }

                $success_count++;
            }

            $detail->close();

            // Validasi apakah ada produk yang berhasil disimpan
            if ($success_count === 0) {
                throw new Exception("Tidak ada produk yang valid untuk disimpan");
            }

            $stock_updates = $this->applyInventoryDetails($normalizedDetails);
            $this->markStockApplied($saleid, true);

            $receivable = $this->syncSalesReceivable(
                $sale_invoice,
                '',
                $cust_id,
                $sale_date,
                $sale_jt,
                $sale_total,
                $bayar_id,
                $catatan,
                $created_by
            );

            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $pointsResult = $rewardSystem->syncEarnPointsForTransaction($cust_id, $saleid, $sale_total);
            $points_earned = (int)($pointsResult['points_earned'] ?? 0);

            /** ======================= LOG AKTIVITAS ======================= **/
            $this->logActivity("Tambah Penjualan Baru", "sale", $saleid, "Transaksi Penjualan: {$sale_invoice}");
            if ($minusAuthorizer) {
                $this->logMinusAuthorization(
                    $minusAuthorizer,
                    $saleid,
                    $sale_invoice,
                    $normalizedDetails,
                    $sale_total,
                    "Penjualan Sales Baru"
                );
            }

            // Commit transaksi
            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("Save Penjualan SUCCESS - SaleID: $saleid, Invoice: $sale_invoice");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Penjualan berhasil disimpan" . ($points_earned > 0 ? " (+{$points_earned} poin)" : ""),
                "saleid" => $saleid,
                "sale_invoice" => $sale_invoice,
                "points_earned" => $points_earned,
                "stock_updates" => $stock_updates,
                "receivable" => $receivable
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            $error_msg = $e->getMessage();
            error_log("[SAVE_PENJUALAN ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    /** ======================= [ UPDATE PENJUALAN - FIXED ] ======================= **/
    function update_penjualan()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

            if (!in_array($this->currentUserLevel(), [1, 3, 4], true)) {
                throw new Exception("Anda tidak memiliki akses untuk mengedit penjualan Sales");
            }

            // ======================= VALIDASI INPUT =======================
            $saleid       = $this->sanitizeInt($_POST['saleid'] ?? 0);
            $sale_date    = $this->sanitizeDate($_POST['sale_date'] ?? date("Y-m-d"));
            $sale_invoice = $this->sanitizeString($_POST['sale_invoice'] ?? '');
            $sale_channel = $this->normalizeSaleChannel($_POST['sale_channel'] ?? 'sales');
            $sale_nofak   = $this->sanitizeString($_POST['sale_nofak'] ?? '');
            $cust_id      = $this->sanitizeInt($_POST['cust_id'] ?? 0);
            $bayar_id     = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $sale_jt      = !empty($_POST['sale_jt']) ? $this->sanitizeDate($_POST['sale_jt']) : null;
            $sale_jt      = $this->calculateDueDate($sale_date, $bayar_id, $sale_jt, $_POST['sale_jt_days'] ?? 0);
            $sale_disc    = $this->sanitizeFloat($_POST['sale_disc'] ?? 0);
            $sale_ppn     = 0.0;
            $sale_total   = $this->sanitizeFloat($_POST['sale_total'] ?? 0);
            $catatan      = $this->sanitizeString($_POST['catatan'] ?? '');
            $user_id      = $this->resolveSalesIdFromRequest();
            $minus_password = (string)($_POST['minus_password'] ?? '');

            if ($saleid <= 0) {
                throw new Exception("❌ ID Penjualan tidak valid!");
            }

            // ======================= VALIDASI DATA =======================
            if (empty($sale_date)) {
                throw new Exception("❌ Tanggal faktur harus diisi!");
            }

            if ($cust_id <= 0) {
                throw new Exception("❌ Pilih pelanggan terlebih dahulu!");
            }

            if ($sale_total == 0) {
                throw new Exception("❌ Total penjualan tidak valid!");
            }

            // ======================= VALIDASI PRODUK =======================
            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("❌ Tidak ada produk dalam penjualan!");
            }

            [$normalizedDetails, $grossTotal] = $this->normalizeSalesDetails(
                $_POST['produk_id'],
                $_POST['qty'] ?? [],
                $_POST['harga'] ?? [],
                $_POST['sale_pdisc'] ?? [],
                $_POST['isi'] ?? []
            );
            $sale_total = $this->applySignedDiscount($grossTotal, $sale_disc, 'Diskon faktur');
            $sale_ppn = $this->applyInvoiceDiscountToPpn($normalizedDetails, $sale_disc);

            if ($sale_total == 0) {
                throw new Exception("Total penjualan tidak valid");
            }

            $isMinusTransaction = $this->hasMinusTransaction($normalizedDetails, $sale_total);
            $minusAuthorizer = null;
            if ($isMinusTransaction) {
                $minusAuthorizer = $this->findMinusAuthorizer($minus_password);
                if (!$minusAuthorizer) {
                    throw new Exception("Password Owner/Admin/Supervisor wajib dan harus benar untuk penjualan sales dengan quantity minus");
                }
            }

            if ($bayar_id === 2 && empty($sale_jt)) {
                throw new Exception("Jatuh tempo wajib diisi untuk penjualan kredit");
            }

            $sale_credit = $bayar_id === 2 && $sale_total > 0 ? $sale_total : 0.0;
            $sale_paid = $sale_credit > 0 ? 0 : 1;

            // ======================= MULAI TRANSAKSI =======================
            if (!$this->conn->begin_transaction()) {
                throw new Exception("❌ Gagal memulai transaksi database: " . $this->conn->error);
            }

            // ======================= CEK STATUS TRANSAKSI =======================
            $check_sql = "SELECT sale_status, sale_invoice, sale_date, user_id, stock_applied FROM sale WHERE saleid = ? FOR UPDATE";
            $check_stmt = $this->conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("❌ Prepare check failed: " . $this->conn->error);
            }

            $check_stmt->bind_param("i", $saleid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();

            if (!$check_data) {
                throw new Exception("❌ Data penjualan tidak ditemukan!");
            }

            if ($check_data['sale_status'] !== 'ACTIVE') {
                throw new Exception("❌ Transaksi tidak aktif, tidak dapat diedit!");
            }

            $this->assertNoPostedReturns($saleid, 'diedit');

            // Invoice adalah identitas transaksi dari server dan tidak boleh diganti lewat form edit.
            $sale_invoice = (string)$check_data['sale_invoice'];

            error_log("🔄 UPDATE PENJUALAN - SaleID: $saleid, Invoice: " . $check_data['sale_invoice']);

            if ($this->currentUserLevel() === 3) {
                if ((int)$check_data['user_id'] !== $this->currentUserId()) {
                    throw new Exception("Anda hanya dapat mengedit transaksi milik Anda sendiri");
                }
                $saleTimestamp = strtotime($check_data['sale_date'] ?? '');
                if ($saleTimestamp && (time() - $saleTimestamp) > 86400) {
                    throw new Exception("Transaksi sales hanya dapat diedit maksimal 1 hari dari tanggal faktur");
                }
            }

            if ((int)($check_data['stock_applied'] ?? 0) === 1) {
                $this->restoreInventoryForSale($saleid);
            }

            // ======================= UPDATE HEADER PENJUALAN =======================
            /** PERBAIKAN 1: Sesuaikan jumlah parameter dengan query */
            $update_sql = "UPDATE sale SET 
                        sale_date = ?, 
                        sale_invoice = ?, 
                        sale_channel = ?,
                        sale_nofak = ?, 
                        cust_id = ?, 
                        bayar_id = ?, 
                        sale_jt = ?, 
                        sale_disc = ?, 
                        sale_ppn = ?, 
                        sale_total = ?, 
                        sale_credit = ?,
                        sale_paid = ?,
                        catatan = ?, 
                        user_id = ?, 
                        updated_at = NOW()
                    WHERE saleid = ? AND sale_status = 'ACTIVE'";

            $update_stmt = $this->conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("❌ Prepare update header failed: " . $this->conn->error);
            }

            /** PERBAIKAN 2: Sesuaikan type definition string dengan jumlah parameter */
            // "sssiisdddsii" = 12 karakter untuk 12 parameter
            $update_stmt->bind_param(
                "ssssiisddddisii",
                $sale_date,     // s - string
                $sale_invoice,  // s - string  
                $sale_channel,  // s - kanal transaksi
                $sale_nofak,    // s - string
                $cust_id,       // i - integer
                $bayar_id,      // i - integer
                $sale_jt,       // s - string (bisa NULL)
                $sale_disc,     // d - double
                $sale_ppn,      // d - double
                $sale_total,    // d - double
                $sale_credit,   // d - double
                $sale_paid,     // i - integer
                $catatan,       // s - string
                $user_id,       // i - integer
                $saleid         // i - integer
            );

            if (!$update_stmt->execute()) {
                throw new Exception("❌ Execute update header failed: " . $update_stmt->error);
            }

            $affected_rows = $update_stmt->affected_rows;
            $update_stmt->close();

            error_log("✅ Header updated - Affected rows: $affected_rows");

            // ======================= HAPUS DETAIL LAMA =======================
            $delete_sql = "DELETE FROM sale_det WHERE sale_id = ?";
            $delete_stmt = $this->conn->prepare($delete_sql);
            if (!$delete_stmt) {
                throw new Exception("❌ Prepare delete detail failed: " . $this->conn->error);
            }

            $delete_stmt->bind_param("i", $saleid);
            if (!$delete_stmt->execute()) {
                throw new Exception("❌ Execute delete detail failed: " . $delete_stmt->error);
            }

            $deleted_rows = $delete_stmt->affected_rows;
            $delete_stmt->close();

            error_log("🗑️ Detail deleted - Affected rows: $deleted_rows");

            // ======================= INSERT DETAIL BARU =======================
            /** PERBAIKAN 3: Sesuaikan query insert detail dengan struktur tabel */
            $detail_sql = "INSERT INTO sale_det 
                        (sale_id, produk_id, sale_qty, sale_harga, sale_pdisc, sale_capital, sale_purchase_cost, ppn_item, is_ppn, isi)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $detail_stmt = $this->conn->prepare($detail_sql);
            if (!$detail_stmt) {
                throw new Exception("❌ Prepare insert detail failed: " . $this->conn->error);
            }

            $success_count = 0;
            foreach ($normalizedDetails as $detailRow) {
                $detail_stmt->bind_param(
                    "iiddddddii",
                    $saleid,
                    $detailRow['produk_id'],
                    $detailRow['qty'],
                    $detailRow['harga'],
                    $detailRow['disc'],
                    $detailRow['capital'],
                    $detailRow['purchase_cost'],
                    $detailRow['ppn_item'],
                    $detailRow['is_ppn'],
                    $detailRow['isi']
                );

                if (!$detail_stmt->execute()) {
                    throw new Exception("❌ Execute insert detail failed: " . $detail_stmt->error);
                }

                $success_count++;
            }

            $detail_stmt->close();

            if ($success_count === 0) {
                throw new Exception("❌ Tidak ada produk yang valid untuk disimpan!");
            }

            error_log("✅ Detail inserted - Success count: $success_count");

            $stock_updates = $this->applyInventoryDetails($normalizedDetails);
            $this->markStockApplied($saleid, true);

            $receivable = $this->syncSalesReceivable(
                $sale_invoice,
                (string)$check_data['sale_invoice'],
                $cust_id,
                $sale_date,
                $sale_jt,
                $sale_total,
                $bayar_id,
                $catatan,
                $this->currentUserId()
            );

            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $pointsResult = $rewardSystem->syncEarnPointsForTransaction($cust_id, $saleid, $sale_total);
            $points_earned = (int)($pointsResult['points_earned'] ?? 0);

            // ======================= LOG AKTIVITAS =======================
            $this->logActivity(
                "Update Penjualan",
                "sale",
                $saleid,
                "Edit transaksi penjualan: {$sale_invoice}"
            );
            if ($minusAuthorizer) {
                $this->logMinusAuthorization(
                    $minusAuthorizer,
                    $saleid,
                    $sale_invoice,
                    $normalizedDetails,
                    $sale_total,
                    "Update Penjualan Sales"
                );
            }

            // ======================= COMMIT TRANSAKSI =======================
            if (!$this->conn->commit()) {
                throw new Exception("❌ Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("🎉 UPDATE PENJUALAN SUCCESS - SaleID: $saleid");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Penjualan berhasil diperbarui",
                "saleid" => $saleid,
                "points_earned" => $points_earned,
                "stock_updates" => $stock_updates,
                "receivable" => $receivable,
                "debug" => [
                    "header_updated" => $affected_rows,
                    "detail_deleted" => $deleted_rows,
                    "detail_inserted" => $success_count
                ]
            ]);
        } catch (Exception $e) {
            // ======================= ROLLBACK JIKA ERROR =======================
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            $error_msg = $e->getMessage();
            error_log("[UPDATE_PENJUALAN ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    /** ======================= [ DELETE PENJUALAN ] ======================= **/
    function delete_penjualan()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

            if (!in_array($this->currentUserLevel(), [1, 4], true)) {
                throw new Exception("Hanya Admin/Owner atau Supervisor yang dapat menghapus penjualan");
            }

            $saleid = $this->sanitizeInt($_POST['saleid'] ?? 0);

            if ($saleid <= 0) {
                throw new Exception("❌ ID Penjualan tidak valid!");
            }

            // Mulai transaksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            // Cek data sebelum dihapus
            $cek = $this->fetchOne("SELECT sale_invoice, stock_applied FROM sale WHERE saleid = ? AND sale_status != 'DELETED' FOR UPDATE", [$saleid]);
            if (!$cek) {
                throw new Exception("❌ Data penjualan tidak ditemukan atau sudah dihapus!");
            }

            $this->assertNoPostedReturns($saleid, 'dihapus');

            if ((int)($cek['stock_applied'] ?? 0) === 1) {
                $this->restoreInventoryForSale($saleid);
            }
            $this->removeSalesReceivable((string)$cek['sale_invoice'], true);

            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $rewardSystem->cancelPointsForTransaction($saleid);

            // Soft delete - update status menjadi DELETED
            $sql = "UPDATE sale 
                    SET sale_status = 'DELETED', status = 0, stock_applied = 0, updated_at = NOW() 
                    WHERE saleid = ? AND sale_status != 'DELETED'";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("❌ Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $saleid);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("❌ Gagal menghapus transaksi!");
            }
            $stmt->close();

            // Commit transaksi
            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            // Log aktivitas
            $this->logActivity(
                "Hapus Penjualan",
                "sale",
                $saleid,
                "Penghapusan transaksi penjualan No: {$cek['sale_invoice']}"
            );

            echo json_encode([
                "status" => "success",
                "msg" => "🗑️ Transaksi berhasil dihapus",
                "saleid" => $saleid
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            error_log("[DELETE_PENJUALAN ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ GET PENJUALAN BY ID ] ======================= **/
    function get_penjualan_by_id()
    {
        try {
            // $this->ensureConnection();

            $saleid = $this->sanitizeInt($_GET['id'] ?? 0);

            if ($saleid <= 0) {
                throw new Exception("❌ ID Penjualan tidak valid!");
            }

            // Get header
            $sql = "SELECT s.*, p.nama as pelanggan_nama, p.alamat as pelanggan_alamat
                    FROM sale s 
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    WHERE s.saleid = ? AND s.sale_status != 'DELETED'";

            $stmt = $this->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare header gagal: " . $this->error);
            }

            $stmt->bind_param("i", $saleid);
            if (!$stmt->execute()) {
                throw new Exception("Execute header gagal: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $header = $result->fetch_assoc();
            $stmt->close();

            if (!$header) {
                throw new Exception("❌ Data penjualan tidak ditemukan!");
            }

            if ($this->currentUserLevel() === 3 && (int)($header['user_id'] ?? 0) !== $this->currentUserId()) {
                throw new Exception("Anda tidak memiliki akses ke transaksi ini");
            }

            // Get details - FIXED: Changed to proper table reference for satuan
            $sql_detail = "SELECT sd.*, pr.nama_produk, pr.barcode, pr.is_ppn, pr.harga_newmodal, pr.id_satuan, sat.nama_satuan
                          FROM sale_det sd
                          LEFT JOIN produk pr ON pr.id = sd.produk_id
                          LEFT JOIN satuan sat ON sat.id = pr.id_satuan
                          WHERE sd.sale_id = ?";

            $stmt_detail = $this->prepare($sql_detail);
            if (!$stmt_detail) {
                throw new Exception("Prepare detail gagal: " . $this->error);
            }

            $stmt_detail->bind_param("i", $saleid);
            if (!$stmt_detail->execute()) {
                throw new Exception("Execute detail gagal: " . $stmt_detail->error);
            }

            $result_detail = $stmt_detail->get_result();
            $details = [];

            while ($row = $result_detail->fetch_assoc()) {
                $details[] = $row;
            }

            $stmt_detail->close();

            echo json_encode([
                "status" => "success",
                "header" => $header,
                "details" => $details
            ]);
        } catch (Exception $e) {
            error_log("[GET_PENJUALAN_BY_ID ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ EXPORT EXCEL ] ======================= **/
    function export_excel()
    {
        try {
            // $this->ensureConnection();

            // Filter
            $tgl_awal = $this->sanitizeDate($_GET['tgl_awal'] ?? date('Y-m-01'));
            $tgl_akhir = $this->sanitizeDate($_GET['tgl_akhir'] ?? date('Y-m-d'));
            $jenis = $this->sanitizeInt($_GET['jenis'] ?? '');
            $pelanggan_id = $this->sanitizeInt($_GET['pelanggan_id'] ?? 0);

            $whereParts = [
                "s.sale_status != 'DELETED'",
                "s.sale_channel IN ('sales', 'online')"
            ];
            $params = [];
            $types = "";
            $this->applySalesScope($whereParts, $params, $types);

            // Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(s.sale_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // Filter jenis transaksi
            if (!empty($jenis)) {
                $whereParts[] = "s.bayar_id = ?";
                $params[] = $jenis;
                $types .= "i";
            }

            // Filter pelanggan
            if ($pelanggan_id > 0) {
                $whereParts[] = "s.cust_id = ?";
                $params[] = $pelanggan_id;
                $types .= "i";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            $sql = "SELECT 
                        s.sale_date,
                        s.sale_invoice,
                        s.sale_nofak,
                        CASE s.bayar_id 
                            WHEN 1 THEN 'Tunai' 
                            WHEN 2 THEN 'Kredit' 
                            ELSE 'Lainnya' 
                        END as jenis_transaksi,
                        p.nama as pelanggan_name,
                        s.sale_total,
                        s.sale_status
                    FROM sale s
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    $where
                    ORDER BY s.sale_date DESC, s.saleid DESC";

            $stmt = $this->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            // Set header untuk download Excel
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo "LAPORAN PENJUALAN\n";
            echo "Periode: " . date('d/m/Y', strtotime($tgl_awal)) . " - " . date('d/m/Y', strtotime($tgl_akhir)) . "\n\n";

            echo "Tanggal\tNo Invoice\tNo Faktur\tJenis Transaksi\tPelanggan\tTotal\tStatus\n";

            $total_all = 0;
            while ($row = $res->fetch_assoc()) {
                echo date('d/m/Y', strtotime($row['sale_date'])) . "\t";
                echo $row['sale_invoice'] . "\t";
                echo $row['sale_nofak'] . "\t";
                echo $row['jenis_transaksi'] . "\t";
                echo $row['pelanggan_name'] . "\t";
                echo number_format($row['sale_total'], 0, ',', '.') . "\t";
                echo $row['sale_status'] . "\n";

                $total_all += $row['sale_total'];
            }

            echo "\n\nTOTAL: " . number_format($total_all, 0, ',', '.');

            $stmt->close();
            exit;
        } catch (Exception $e) {
            error_log("[EXPORT_EXCEL ERROR] " . $e->getMessage());
            echo "Error: " . $e->getMessage();
        }
        exit;
    }

    /** ======================= [ SEARCH PELANGGAN ] ======================= **/
    function search_pelanggan()
    {
        try {
            // $this->ensureConnection();

            $term = $this->sanitizeString($_GET['term'] ?? '');
            $sql = "SELECT id, nama, alamat, kota, kode 
                    FROM pelanggan 
                    WHERE status = 1 AND (nama LIKE ? OR kode LIKE ?)
                    LIMIT 20";
            $stmt = $this->prepare($sql);
            $like = "%{$term}%";
            $stmt->bind_param("ss", $like, $like);
            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    "id" => $row['id'],
                    "kode" => $row['kode'],
                    "nama" => $row['nama'],
                    "alamat" => $row['alamat'],
                    "kota" => $row['kota'],
                    "value" => $row['nama']
                ];
            }

            if (empty($data)) {
                $data[] = ["value" => "Data tidak ditemukan"];
            }

            echo json_encode($data);
            $stmt->close();
        } catch (Exception $e) {
            error_log("[SEARCH_PELANGGAN ERROR] " . $e->getMessage());
            echo json_encode([]);
        }
        exit;
    }

    /** ======================= [ SEARCH PRODUK ] ======================= **/
    function search_produk()
    {
        try {
            // $this->ensureConnection();

            $term = trim($_GET['term'] ?? '');

            // Pisahkan kata
            $keywords = $term === '' ? [] : preg_split('/\s+/', $term);

            // Base SQL
            $sql = "SELECT 
                        p.id, 
                        p.nama_produk, 
                        p.barcode, 
                        p.stok, 
                        p.harga_sales, 
                        p.harga_kasir,
                        p.harga_newmodal,
                        p.is_ppn,
                        p.gambar_produk,
                        s.nama_satuan,
                        k.nama_kategori
                    FROM produk p
                    LEFT JOIN satuan s ON s.id = p.id_satuan
                    LEFT JOIN kategori k ON k.id = p.id_kategori
                    WHERE p.status = 1";

            $params = [];
            $types  = '';

            // Tambahkan kondisi LIKE untuk setiap kata
            foreach ($keywords as $word) {
                $sql .= " AND (p.nama_produk LIKE ? OR p.barcode LIKE ?)";
                $like = '%' . $word . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= 'ss';
            }

            $sql .= " ORDER BY (p.stok > 0) DESC, (COALESCE(p.gambar_produk, '') <> '') DESC, p.nama_produk ASC LIMIT 50";

            $stmt = $this->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $text = "{$row['nama_produk']}" . (!empty($row['nama_satuan']) ? " ({$row['nama_satuan']})" : '');
                $isPpn = (int)$row['is_ppn'];
                $hargaSales = $this->priceWithItemPpn((float)$row['harga_sales'], $isPpn);
                $hargaKasir = $this->priceWithItemPpn((float)$row['harga_kasir'], $isPpn);

                $data[] = [
                    "id"           => $row['id'],
                    "nama_produk"  => $row['nama_produk'],
                    "barcode"      => $row['barcode'],
                    "satuan"       => $row['nama_satuan'],
                    "stok"         => (float)$row['stok'],
                    "harga_sales"  => $hargaSales,
                    "harga_kasir"  => $hargaKasir,
                    "harga_sales_dasar" => (float)$row['harga_sales'],
                    "harga_kasir_dasar" => (float)$row['harga_kasir'],
                    "harga_newmodal"  => $row['harga_newmodal'],
                    "is_ppn"       => $isPpn,
                    "kategori"     => $row['nama_kategori'] ?: 'Tanpa Kategori',
                    "gambar_produk" => $row['gambar_produk'] ?? '',
                    "gambar_url"   => !empty($row['gambar_produk'])
                        ? base_url . 'uploads/' . rawurlencode(basename($row['gambar_produk']))
                        : base_url . 'uploads/no-image-available.png',
                    "text"         => $text,
                    "value"        => $row['nama_produk']
                ];
            }

            if (empty($data)) {
                $data[] = [
                    "id" => "",
                    "text" => "❌ Tidak ditemukan produk yang cocok"
                ];
            }

            echo json_encode($data);
            $stmt->close();
            exit;
        } catch (Exception $e) {
            error_log("[SEARCH_PRODUK ERROR] " . $e->getMessage());
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Catat aktivitas user ke tabel log_aktivitas
     */
    private function logActivity($aksi, $tabel, $record_id, $keterangan = "", $userIdOverride = null)
    {
        try {
            // $this->ensureConnection();

            $user_id = $userIdOverride !== null
                ? intval($userIdOverride)
                : intval($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $sql = "INSERT INTO log_aktivitas 
                    (user_id, aksi, tabel, record_id, keterangan, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("❌ Prepare log error: " . $this->conn->error);
            }

            $stmt->bind_param(
                "ississs",
                $user_id,
                $aksi,
                $tabel,
                $record_id,
                $keterangan,
                $ip_address,
                $user_agent
            );
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("[LOG ACTIVITY ERROR] " . $e->getMessage());
        }
    }

    /** ======================= [ GENERATE INVOICE ] ======================= **/
    /** ======================= [ GENERATE UNIQUE INVOICE ] ======================= **/
    function generate_invoice()
    {
        try {
            $invoice = $this->generate_invoice_unique();
            echo json_encode([
                "status" => "success",
                "invoice" => $invoice
            ]);
        } catch (Exception $e) {
            error_log("[GENERATE_INVOICE ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => "Gagal membuat nomor invoice: " . $e->getMessage()
            ]);
        }
        exit;
    }

    private function generate_invoice_unique()
    {
        $tahun = date("Y");
        $bulan = date("m");
        $hari = date("d");
        $prefix = "INV-{$tahun}{$bulan}{$hari}-";

        // Get the max number for today's invoices
        $stmt = $this->prepare("SELECT MAX(CAST(SUBSTRING(sale_invoice, ?) AS UNSIGNED)) as max_num FROM sale WHERE sale_invoice LIKE ?");
        $prefix_len = strlen($prefix) + 1; // +1 because SUBSTRING is 1-indexed
        $like = "{$prefix}%";
        $stmt->bind_param("is", $prefix_len, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $next = ($row && $row['max_num']) ? intval($row['max_num']) + 1 : 1;

        // Double check - loop until we find a truly unique invoice
        $invoice = $prefix . str_pad($next, 4, "0", STR_PAD_LEFT);
        $max_attempts = 100;
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $check_stmt = $this->prepare("SELECT COUNT(*) as cnt FROM sale WHERE sale_invoice = ?");
            $check_stmt->bind_param("s", $invoice);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            $check_row = $check_res->fetch_assoc();
            $check_stmt->close();

            if ($check_row['cnt'] == 0) {
                // Invoice is unique, return it
                return $invoice;
            }

            // Invoice exists, try next number
            $next++;
            $invoice = $prefix . str_pad($next, 4, "0", STR_PAD_LEFT);
            $attempt++;
        }

        // If all else fails, add random suffix
        return $prefix . str_pad($next, 4, "0", STR_PAD_LEFT) . "-" . rand(100, 999);
    }

    /** ======================= [ HELPER METHOD - FIXED ACCESS LEVEL ] ======================= **/
    public function fetchOne($sql, $params = [])
    {
        // $this->ensureConnection();

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $data;
    }
}

/** ======================= [ ROUTER - SIMPLE & WORKING ] ======================= **/
if (is_direct_script(__FILE__)) {
try {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    // Clean output buffer to prevent BOM/extra output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Kirim headers ASAP
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Instantiate class
    $Penjualan = new Penjualan();
    $action = request_action('none');

    $allowed_actions = [
        'get_data_penjualan',
        'search_pelanggan',
        'get_riwayat_transaksi',
        'search_produk',
        'save_penjualan',
        'update_penjualan',
        'get_penjualan_by_id',
        'delete_penjualan',
        'export_excel',
        'generate_invoice'
    ];

    // Debug log untuk action
    error_log("[PENJUALAN ROUTER] Action received: '$action', GET params: " . json_encode($_GET));

    if (in_array($action, $allowed_actions)) {
        // Call method - method akan handle JSON output dan exit
        $Penjualan->$action();
        // Tidak akan mencapai sini karena method call exit()
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'failed',
            'msg' => 'Action not recognized: ' . $action,
            'debug' => ['received_action' => $action, 'allowed' => $allowed_actions]
        ]);
        exit(0);
    }
} catch (Exception $e) {
    error_log("[PENJUALAN ROUTER ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'failed',
        'msg' => 'Internal Server Error: ' . $e->getMessage()
    ]);
    exit(0);
}

// Failsafe exit jika somehow method tidak exit
exit(0);
}
