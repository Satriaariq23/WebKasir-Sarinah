<?php
// Prevent any output before JSON response
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors to browser

require_once('../config.php');
require_once('../classes/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

class PenjualanKasir extends DBConnection
{
    private $settings;

    public function __construct()
    {
        global $_settings;
        $this->settings = $_settings ?? null;
        parent::__construct();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function ensureConnection()
    {
        if (!$this->conn || $this->conn->connect_error) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $this->conn = $conn;
            } else {
                $this->conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
                if ($this->conn->connect_error) {
                    throw new Exception("Database connection failed: " . $this->conn->connect_error);
                }
            }
        }
    }

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

        return $amount < 0 ? $amount + $discount : $amount - $discount;
    }

    private function amountBeforeSignedDiscount($discountedAmount, $discount)
    {
        $discountedAmount = (float)$discountedAmount;
        $discount = (float)$discount;

        if ($discount < 0) {
            throw new Exception("Diskon faktur tidak boleh bernilai minus");
        }

        return $discountedAmount < 0
            ? $discountedAmount - $discount
            : $discountedAmount + $discount;
    }

    private function applyInvoiceDiscountToLine($lineAmount, $invoiceBase, $invoiceDiscount)
    {
        $lineAmount = (float)$lineAmount;
        $invoiceBase = (float)$invoiceBase;
        $invoiceDiscount = (float)$invoiceDiscount;

        if ($invoiceDiscount <= 0 || abs($invoiceBase) < 0.00001) {
            return $lineAmount;
        }

        $discountedInvoice = $this->applySignedDiscount($invoiceBase, $invoiceDiscount, 'Diskon faktur');
        $invoiceAdjustment = $discountedInvoice - $invoiceBase;

        return $lineAmount + ($invoiceAdjustment * ($lineAmount / $invoiceBase));
    }

    private function formatQty($qty)
    {
        $formatted = number_format((float)$qty, 4, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function normalizeLegacyUnitPrice($storedPrice, $masterPrice = 0, $detailTotal = 0, $saleTotal = 0)
    {
        $storedPrice = (float)$storedPrice;
        $masterPrice = (float)$masterPrice;
        $detailTotal = (float)$detailTotal;
        $saleTotal = (float)$saleTotal;

        if ($storedPrice <= 0) {
            return 0.0;
        }

        if ($saleTotal > 0 && $detailTotal >= ($saleTotal * 50)) {
            return $storedPrice / 100;
        }

        if ($masterPrice > 0 && $storedPrice >= ($masterPrice * 50)) {
            return $storedPrice / 100;
        }

        return $storedPrice;
    }

    private function normalizeSaleDetailPrices($details, $saleTotal = 0)
    {
        $detailTotal = 0.0;
        foreach ($details as $item) {
            $detailTotal += (float)($item['sale_qty'] ?? 0) * (float)($item['sale_harga'] ?? 0);
        }

        $subtotal = 0.0;
        foreach ($details as &$item) {
            $salePriceMaster = (float)($item['harga_kasir'] ?? 0);
            if ($salePriceMaster <= 0) {
                $salePriceMaster = (float)($item['harga_sales'] ?? 0);
            }

            $item['sale_harga_raw'] = $item['sale_harga'] ?? 0;
            $item['sale_capital_raw'] = $item['sale_capital'] ?? 0;
            $item['sale_harga'] = $this->normalizeLegacyUnitPrice($item['sale_harga'] ?? 0, $salePriceMaster, $detailTotal, $saleTotal);
            $item['sale_capital'] = $this->normalizeLegacyUnitPrice($item['sale_capital'] ?? 0, $item['harga_newmodal'] ?? 0, $detailTotal, $saleTotal);
            $lineBase = (float)($item['sale_qty'] ?? 0) * (float)$item['sale_harga'];
            $item['line_subtotal'] = $this->applySignedDiscount(
                $lineBase,
                (float)($item['sale_pdisc'] ?? 0),
                'Diskon item'
            );
            $subtotal += $item['line_subtotal'];
        }
        unset($item);

        return [$details, $subtotal];
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

    private function sanitizeDateTime($input)
    {
        if (empty($input)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$input)) {
            $input .= ' ' . date('H:i:s');
        }
        $timestamp = strtotime($input);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function findMinusAuthorizer($password)
    {
        $password = (string)$password;
        if ($password === '') {
            return null;
        }

        $stmt = $this->prepare(
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

    private function currentOperatorDescription()
    {
        $userId = intval($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return 'User tidak diketahui';
        }

        $stmt = $this->prepare(
            "SELECT u.firstname, u.username, COALESCE(ul.level_nama, '') AS level_nama
             FROM users u
             LEFT JOIN user_level ul ON ul.level_id = u.level_id
             WHERE u.id = ?
             LIMIT 1"
        );
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

    private function describeMinusItems(array $items)
    {
        $minusItems = [];
        foreach ($items as $item) {
            $qty = $this->sanitizeQuantity($item['qty'] ?? 0);
            if ($qty >= 0) {
                continue;
            }

            $produkId = $this->sanitizeInt($item['produk_id'] ?? 0);
            $name = 'Produk #' . $produkId;
            if ($produkId > 0) {
                $pricing = $this->getProductPricing($produkId);
                $name = trim((string)($pricing['nama_produk'] ?? '')) ?: $name;
            }

            $minusItems[] = $name . ' (Qty ' . $this->formatQty($qty) . ')';
        }

        return $minusItems ? implode(', ', $minusItems) : 'Total transaksi minus';
    }

    private function logMinusAuthorization(array $authorizer, $saleId, $invoice, array $items, $saleTotal, $transactionType)
    {
        $authorizerId = (int)($authorizer['id'] ?? 0);
        if ($authorizerId <= 0) {
            return;
        }

        $description = $transactionType
            . ' | Invoice: ' . $invoice
            . ' | Item minus: ' . $this->describeMinusItems($items)
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

    private function getProductPricing($produkId)
    {
        $stmt = $this->prepare("SELECT nama_produk, harga_beli, harga_newmodal, harga_kasir, harga_sales, is_ppn FROM produk WHERE id = ? AND status = 1 LIMIT 1");
        $stmt->bind_param("i", $produkId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception("Produk ID {$produkId} tidak ditemukan atau nonaktif");
        }

        return $row;
    }

    // Main methods
    function verify_minus_password()
    {
        try {
            $this->ensureConnection();

            $password = (string)($_POST['password'] ?? '');
            if ($password === '') {
                throw new Exception("Password Admin/Supervisor wajib diisi");
            }

            $authorizer = $this->findMinusAuthorizer($password);
            if (!$authorizer) {
                echo json_encode([
                    "status" => "failed",
                    "authorized" => false,
                    "msg" => "Password Admin/Supervisor salah atau akun tidak aktif"
                ]);
                return;
            }

            echo json_encode([
                "status" => "success",
                "authorized" => true,
                "msg" => "Otorisasi quantity minus berhasil"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                "status" => "failed",
                "authorized" => false,
                "msg" => $e->getMessage()
            ]);
        }
    }

    function get_data_penjualan_kasir()
    {
        try {
            $this->ensureConnection();

            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = trim($_POST['search']['value'] ?? '');

            $tgl = $this->sanitizeDate($_POST['tgl'] ?? date('Y-m-d'));
            $user_id = $this->sanitizeInt($_POST['user_id'] ?? 0);
            $payment_method = $this->sanitizeString($_POST['payment_method'] ?? '');
            $invoice = $this->sanitizeString($_POST['invoice'] ?? '');

            if (!strtotime($tgl)) {
                $tgl = date('Y-m-d');
            }

            $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
            $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc');
            $orderDir = ($orderDir === 'desc') ? 'DESC' : 'ASC';

            $columns = [
                0 => 's.saleid',
                1 => 's.sale_date',
                2 => 's.sale_invoice',
                3 => 'u.username',
                4 => 's.payment_method',
                5 => 's.sale_total',
                6 => 's.amount_paid',
                7 => 's.change_amount'
            ];

            $orderColumn = $columns[$orderColumnIndex] ?? 's.saleid';

            $whereParts = ["s.sale_status = 'ACTIVE' AND s.sale_invoice LIKE 'KSR-%'"];
            $params = [];
            $types = "";

            if (!empty($search)) {
                $whereParts[] = "(s.sale_invoice LIKE ? OR s.catatan LIKE ? OR u.username LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                $types .= "sss";
            }

            if (!empty($tgl)) {
                $whereParts[] = "DATE(s.sale_date) = ?";
                $params[] = $tgl;
                $types .= "s";
            }

            if ($user_id > 0) {
                $whereParts[] = "s.user_id = ?";
                $params[] = $user_id;
                $types .= "i";
            }

            if (!empty($payment_method)) {
                $whereParts[] = "s.payment_method = ?";
                $params[] = $payment_method;
                $types .= "s";
            }

            if (!empty($invoice)) {
                $whereParts[] = "s.sale_invoice LIKE ?";
                $params[] = "%{$invoice}%";
                $types .= "s";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            $totalQuery = $this->query("SELECT COUNT(*) AS cnt FROM sale s WHERE s.sale_status = 'ACTIVE' AND s.sale_invoice LIKE 'KSR-%'");
            $totalRecords = $totalQuery ? $totalQuery->fetch_assoc()['cnt'] ?? 0 : 0;

            $countSql = "SELECT COUNT(*) AS cnt FROM sale s LEFT JOIN users u ON u.id = s.user_id $where";
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

            $sql = "SELECT 
                        s.saleid,
                        s.sale_date,
                        s.sale_invoice,
                        s.catatan,
                        s.payment_method,
                        s.sale_total,
                        s.amount_paid,
                        s.change_amount,
                        s.sale_status,
                        u.username
                    FROM sale s
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

                $no = $start + 1;
                while ($r = $res->fetch_assoc()) {
                    $r['no'] = $no++;
                    $r['sale_date'] = date("d/m/Y H:i", strtotime($r['sale_date']));
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
            error_log("GET_DATA_PENJUALAN_KASIR ERROR: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "draw" => $draw ?? 1,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Terjadi kesalahan sistem"
            ]);
        }
        exit;
    }

    function save_penjualan_kasir()
    {
        try {
            $this->ensureConnection();

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Invalid JSON input");
            }

            // Debug logging
            if (!empty($input['items'])) {
                foreach ($input['items'] as $idx => $item) {
                    error_log("Item $idx: produk_id={$item['produk_id']}, qty={$item['qty']}, harga={$item['harga']} (type: " . gettype($item['harga']) . ")");
                }
            }

            $user_id = 0;
            if (isset($_SESSION['userdata']['id'])) {
                $user_id = $this->sanitizeInt($_SESSION['userdata']['id']);
            } elseif (isset($_SESSION['user_id'])) {
                $user_id = $this->sanitizeInt($_SESSION['user_id']);
            }

            if ($user_id <= 0) {
                throw new Exception("User tidak terautentikasi");
            }

            $sale_date = $this->sanitizeDateTime($input['sale_date'] ?? date("Y-m-d H:i:s"));
            $sale_invoice = $this->sanitizeString($input['sale_invoice'] ?? '');
            // Handle customer ID - allow NULL for general customers
            $cust_id_input = isset($input['cust_id']) ? $input['cust_id'] : null;
            $cust_id = null;
            if ($cust_id_input !== null && $cust_id_input !== 0 && $cust_id_input !== '0') {
                $cust_id = intval($cust_id_input);
            }
            $payment_method = $this->sanitizeString($input['payment_method'] ?? 'cash');
            $catatan = $this->sanitizeString($input['catatan'] ?? '');
            $sale_disc = $this->sanitizeFloat($input['sale_disc'] ?? 0);
            $sale_total = $this->sanitizeFloat($input['sale_total'] ?? 0);
            $amount_paid = $this->sanitizeFloat($input['amount_paid'] ?? 0);
            $change_amount = $this->sanitizeFloat($input['change_amount'] ?? 0);
            $items = $input['items'] ?? [];
            $minus_password = (string)($input['minus_password'] ?? '');
            $is_minus_transaction = $sale_total < 0;
            foreach ($items as $item) {
                if ($this->sanitizeQuantity($item['qty'] ?? 0) < 0) {
                    $is_minus_transaction = true;
                    break;
                }
            }

            error_log("Save Penjualan Kasir: user_id=$user_id, total=$sale_total, items=" . count($items));

            if (empty($sale_date)) {
                throw new Exception("Tanggal transaksi harus diisi");
            }

            if (empty($sale_invoice)) {
                throw new Exception("Nomor invoice tidak valid");
            }

            if ($sale_total == 0) {
                throw new Exception("Total penjualan tidak valid");
            }

            $minusAuthorizer = null;
            if ($is_minus_transaction) {
                $minusAuthorizer = $this->findMinusAuthorizer($minus_password);
                if (!$minusAuthorizer) {
                    throw new Exception("Password Owner/Admin/Supervisor wajib dan harus benar untuk transaksi kasir minus/retur");
                }
            }

            if ($amount_paid < 0) {
                throw new Exception("Jumlah pembayaran tidak valid");
            }

            if ($is_minus_transaction) {
                $payment_method = 'retur';
                $amount_paid = 0;
                $change_amount = 0;
                $is_receivable = false;
            } else {
                $is_receivable = ($amount_paid <= 0);
            }
            if ($is_receivable) {
                if ($cust_id === null || $cust_id <= 0) {
                    throw new Exception("Pilih member/pelanggan terlebih dahulu untuk transaksi piutang");
                }
                $payment_method = 'piutang';
                $change_amount = 0;
            } elseif ($amount_paid < $sale_total) {
                throw new Exception("Jumlah pembayaran kurang dari total");
            }

            if (empty($items) || !is_array($items)) {
                throw new Exception("Tidak ada produk dalam transaksi");
            }

            if (empty($sale_invoice)) {
                $sale_invoice = $this->generateInvoiceKasir();
            }

            if (strpos($sale_invoice, 'KSR-') !== 0) {
                $sale_invoice = $this->generateInvoiceKasir();
            }

            // Check if invoice already exists to prevent duplicate entries
            $checkInvoiceStmt = $this->conn->prepare("SELECT 1 FROM sale WHERE sale_invoice = ? LIMIT 1");
            if (!$checkInvoiceStmt) {
                throw new Exception("Gagal memprepare query check invoice: " . $this->conn->error);
            }
            $checkInvoiceStmt->bind_param("s", $sale_invoice);
            $checkInvoiceStmt->execute();
            $checkResult = $checkInvoiceStmt->get_result();

            // If invoice exists, generate a new one
            if ($checkResult->num_rows > 0) {
                error_log("Invoice sudah ada: $sale_invoice, membuat invoice baru");
                $sale_invoice = $this->generateInvoiceKasir();

                // Check again for the new invoice
                $checkInvoiceStmt->bind_param("s", $sale_invoice);
                $checkInvoiceStmt->execute();
                $checkResult = $checkInvoiceStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    throw new Exception("Gagal membuat nomor invoice unik. Silakan coba lagi.");
                }
            }
            $checkInvoiceStmt->close();

            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            $sale_ppn = 0;

            // Prepare SQL based on customer_id
            if ($cust_id === null || $cust_id == 0) {
                // Insert with NULL customer
                $sql = "INSERT INTO sale (
                            sale_date, sale_invoice, sale_channel, cust_id, payment_method, catatan,
                            sale_disc, sale_ppn, sale_total, amount_paid, change_amount,
                            user_id, sale_status, status, date_created
                        ) VALUES (?, ?, 'kasir', NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, NOW())";
            } else {
                // Insert with specific customer
                $sql = "INSERT INTO sale (
                            sale_date, sale_invoice, sale_channel, cust_id, payment_method, catatan,
                            sale_disc, sale_ppn, sale_total, amount_paid, change_amount,
                            user_id, sale_status, status, date_created
                        ) VALUES (?, ?, 'kasir', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, NOW())";
            }

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement gagal: " . $this->conn->error);
            }

            // Bind parameters based on which SQL query was used
            if ($cust_id === null || $cust_id == 0) {
                // No customer_id in query, bind 10 params
                $stmt->bind_param(
                    "ssssdddddi",
                    $sale_date,
                    $sale_invoice,
                    $payment_method,
                    $catatan,
                    $sale_disc,
                    $sale_ppn,
                    $sale_total,
                    $amount_paid,
                    $change_amount,
                    $user_id
                );
            } else {
                // With customer_id, bind 11 params
                $stmt->bind_param(
                    "ssissdddddi",
                    $sale_date,
                    $sale_invoice,
                    $cust_id,
                    $payment_method,
                    $catatan,
                    $sale_disc,
                    $sale_ppn,
                    $sale_total,
                    $amount_paid,
                    $change_amount,
                    $user_id
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Execute statement gagal: " . $stmt->error);
            }

            $saleid = $this->conn->insert_id;
            $stmt->close();

            $bayarId = $is_receivable ? 2 : 1;
            $saleCredit = $is_receivable ? $sale_total : 0;
            $salePaid = $is_receivable ? 0 : 1;
            $paymentFlagStmt = $this->prepare("UPDATE sale SET bayar_id = ?, sale_credit = ?, sale_paid = ? WHERE saleid = ?");
            $paymentFlagStmt->bind_param("idii", $bayarId, $saleCredit, $salePaid, $saleid);
            if (!$paymentFlagStmt->execute()) {
                throw new Exception("Gagal update status bayar transaksi: " . $paymentFlagStmt->error);
            }
            $paymentFlagStmt->close();

            $detail_sql = "INSERT INTO sale_det 
                    (sale_id, produk_id, sale_qty, sale_harga, sale_pdisc, sale_capital, sale_purchase_cost, ppn_item, is_ppn, isi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $detail = $this->conn->prepare($detail_sql);
            if (!$detail) {
                throw new Exception("Prepare detail gagal: " . $this->conn->error);
            }

            $success_count = 0;
            $salePpnTotal = 0.0;
            $calculatedSubtotal = 0.0;
            $invoiceGross = $this->amountBeforeSignedDiscount($sale_total, $sale_disc);

            foreach ($items as $item) {
                $produk_id = $this->sanitizeInt($item['produk_id'] ?? 0);
                $qty = $this->sanitizeQuantity($item['qty'] ?? 0);
                $harga = $this->sanitizeFloat($item['harga'] ?? 0);
                $disc = $this->sanitizeFloat($item['disc_item'] ?? 0);
                $capital = $this->sanitizeFloat($item['sale_capital'] ?? 0);
                $isi = $this->sanitizeInt($item['isi'] ?? 1);

                if ($produk_id <= 0) {
                    error_log("Produk ID tidak valid: " . $produk_id);
                    continue;
                }

                if ($qty == 0) {
                    throw new Exception("Quantity produk tidak valid untuk produk ID: " . $produk_id);
                }

                if ($harga < 0) {
                    throw new Exception("Harga produk tidak valid untuk produk ID: " . $produk_id);
                }

                if ($isi <= 0) {
                    throw new Exception("Isi kemasan tidak valid untuk produk ID: " . $produk_id);
                }

                // Validasi harga dalam range yang wajar (max 10 juta per unit)
                if ($harga > 10000000) {
                    throw new Exception("Harga produk terlalu tinggi (Rp " . number_format($harga, 0) . ") untuk produk ID: " . $produk_id . ". Mungkin ada kesalahan input harga.");
                }

                $produkPricing = $this->getProductPricing($produk_id);
                $isPpn = (int)($produkPricing['is_ppn'] ?? 0);
                $masterPrice = (float)($produkPricing['harga_kasir'] ?: $produkPricing['harga_sales'] ?: 0);
                if ($isPpn === 1 && $masterPrice > 0 && abs($harga - $masterPrice) < 0.01) {
                    $harga = $this->priceWithItemPpn($harga, 1);
                }

                $capital = (float)($produkPricing['harga_newmodal'] ?? 0);
                $purchase_cost = (float)($produkPricing['harga_beli'] ?? 0);
                if ($harga < $capital) {
                    throw new Exception("Harga jual " . ($produkPricing['nama_produk'] ?? 'produk') . " tidak boleh kurang dari New Modal Rp " . number_format($capital, 0, ',', '.'));
                }
                $lineGross = $this->applySignedDiscount(
                    $qty * $harga,
                    $disc,
                    'Diskon item ' . ($produkPricing['nama_produk'] ?? $produk_id)
                );
                $calculatedSubtotal += $lineGross;
                $lineAfterInvoiceDiscount = $this->applyInvoiceDiscountToLine($lineGross, $invoiceGross, $sale_disc);
                $ppn_item = $this->ppnFromGrossAmount($lineAfterInvoiceDiscount, $isPpn);

                $stockQty = $qty * $isi;
                $update_stok_sql = $stockQty > 0
                    ? "UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?"
                    : "UPDATE produk SET stok = stok - ? WHERE id = ?";
                $update_stmt = $this->conn->prepare($update_stok_sql);
                if ($update_stmt) {
                    if ($stockQty > 0) {
                        $update_stmt->bind_param("did", $stockQty, $produk_id, $stockQty);
                    } else {
                        $update_stmt->bind_param("di", $stockQty, $produk_id);
                    }
                    if (!$update_stmt->execute()) {
                        throw new Exception("Gagal update stok untuk produk ID: " . $produk_id);
                    }

                    if ($stockQty > 0 && $update_stmt->affected_rows === 0) {
                        throw new Exception("Stok tidak cukup untuk produk ID: " . $produk_id);
                    }
                    $update_stmt->close();
                }

                $detail->bind_param(
                    "iiddddddii",
                    $saleid,
                    $produk_id,
                    $qty,
                    $harga,
                    $disc,
                    $capital,
                    $purchase_cost,
                    $ppn_item,
                    $isPpn,
                    $isi
                );

                if (!$detail->execute()) {
                    throw new Exception("Execute detail gagal untuk produk ID: " . $produk_id . ": " . $detail->error);
                }
                $salePpnTotal += $ppn_item;

                $success_count++;
            }

            $detail->close();

            if ($success_count === 0) {
                throw new Exception("Tidak ada produk yang valid untuk disimpan");
            }

            $calculatedSaleTotal = round($this->applySignedDiscount($calculatedSubtotal, $sale_disc, 'Diskon faktur'));
            if (abs($sale_total - $calculatedSaleTotal) > 1) {
                throw new Exception("Total transaksi tidak sesuai hasil perhitungan server. Silakan hitung ulang transaksi");
            }
            $sale_total = $calculatedSaleTotal;
            $saleCredit = $is_receivable ? $sale_total : 0;
            if (!$is_minus_transaction && !$is_receivable && $amount_paid < $sale_total) {
                throw new Exception("Jumlah pembayaran kurang dari total hasil perhitungan server");
            }
            $change_amount = (!$is_minus_transaction && !$is_receivable)
                ? round(max($amount_paid - $sale_total, 0), 2)
                : 0.0;

            $ppnUpdateStmt = $this->prepare("UPDATE sale SET sale_ppn = ?, sale_total = ?, sale_credit = ?, change_amount = ?, stock_applied = 1 WHERE saleid = ?");
            $ppnUpdateStmt->bind_param("ddddi", $salePpnTotal, $sale_total, $saleCredit, $change_amount, $saleid);
            if (!$ppnUpdateStmt->execute()) {
                throw new Exception("Gagal update total PPN kasir: " . $ppnUpdateStmt->error);
            }
            $ppnUpdateStmt->close();

            $piutangData = $this->syncKasirReceivable($sale_invoice, '', $cust_id ?? 0, $sale_date, $sale_total, $amount_paid, $catatan, $user_id);

            $points_earned = 0;
            if (($cust_id ?? 0) > 0) {
                require_once(__DIR__ . '/RewardSystem.php');
                $rewardSystem = new RewardSystem($this->conn, false);
                $pointsResult = $rewardSystem->syncEarnPointsForTransaction($cust_id, $saleid, $sale_total);
                $points_earned = (int)($pointsResult['points_earned'] ?? 0);
            }

            $this->logActivity("Transaksi Kasir", "sale", $saleid, "Kasir: $sale_invoice - Total: Rp " . number_format($sale_total, 0, ',', '.'));
            if ($minusAuthorizer) {
                $this->logMinusAuthorization(
                    $minusAuthorizer,
                    $saleid,
                    $sale_invoice,
                    $items,
                    $sale_total,
                    "Penjualan Kasir Baru"
                );
            }

            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("Save Penjualan Kasir SUCCESS - SaleID: $saleid, Invoice: $sale_invoice");

            $successMessage = "Transaksi kasir berhasil disimpan";
            if ($piutangData) {
                $successMessage .= " dan masuk piutang pelanggan";
            }
            if ($points_earned > 0) {
                $successMessage .= " (+{$points_earned} poin)";
            }

            // Return success response dengan status yang konsisten
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "msg" => $successMessage,
                "success" => true,  // For backward compatibility
                "data" => [
                    "saleid" => $saleid,
                    "sale_invoice" => $sale_invoice,
                    "sale_total" => $sale_total,
                    "amount_paid" => $amount_paid,
                    "change_amount" => $change_amount,
                    "payment_method" => $payment_method,
                    "piutang" => $piutangData,
                    "points_earned" => $points_earned
                ]
            ]);
        } catch (Exception $e) {
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            $error_msg = $e->getMessage();
            error_log("SAVE_PENJUALAN_KASIR ERROR: " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "success" => false,  // For backward compatibility
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    function update_penjualan_kasir()
    {
        try {
            $this->ensureConnection();

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Invalid JSON input");
            }

            $saleid = $this->sanitizeInt($input['saleid'] ?? 0);
            $user_id = 0;
            if (isset($_SESSION['userdata']['id'])) {
                $user_id = $this->sanitizeInt($_SESSION['userdata']['id']);
            } elseif (isset($_SESSION['user_id'])) {
                $user_id = $this->sanitizeInt($_SESSION['user_id']);
            }

            if ($saleid <= 0) {
                throw new Exception("ID transaksi tidak valid");
            }

            if ($user_id <= 0) {
                throw new Exception("User tidak terautentikasi");
            }

            $sale_date = $this->sanitizeDateTime($input['sale_date'] ?? date("Y-m-d H:i:s"));
            $sale_invoice = $this->sanitizeString($input['sale_invoice'] ?? '');
            $cust_id_input = $input['cust_id'] ?? null;
            $cust_id = null;
            if ($cust_id_input !== null && $cust_id_input !== 0 && $cust_id_input !== '0') {
                $cust_id = intval($cust_id_input);
            }
            $payment_method = $this->sanitizeString($input['payment_method'] ?? 'cash');
            $catatan = $this->sanitizeString($input['catatan'] ?? '');
            $sale_disc = $this->sanitizeFloat($input['sale_disc'] ?? 0);
            $sale_total = $this->sanitizeFloat($input['sale_total'] ?? 0);
            $amount_paid = $this->sanitizeFloat($input['amount_paid'] ?? 0);
            $change_amount = $this->sanitizeFloat($input['change_amount'] ?? 0);
            $items = $input['items'] ?? [];
            $minus_password = (string)($input['minus_password'] ?? '');
            $is_minus_transaction = $sale_total < 0;
            foreach ($items as $item) {
                if ($this->sanitizeQuantity($item['qty'] ?? 0) < 0) {
                    $is_minus_transaction = true;
                    break;
                }
            }

            if (empty($sale_date)) {
                throw new Exception("Tanggal transaksi harus diisi");
            }

            if (empty($sale_invoice) || strpos($sale_invoice, 'KSR-') !== 0) {
                throw new Exception("Nomor invoice kasir tidak valid");
            }

            if ($sale_total == 0) {
                throw new Exception("Total penjualan tidak valid");
            }

            $minusAuthorizer = null;
            if ($is_minus_transaction) {
                $minusAuthorizer = $this->findMinusAuthorizer($minus_password);
                if (!$minusAuthorizer) {
                    throw new Exception("Password Owner/Admin/Supervisor wajib dan harus benar untuk transaksi kasir minus/retur");
                }
            }

            if ($amount_paid < 0) {
                throw new Exception("Jumlah pembayaran tidak valid");
            }

            if ($is_minus_transaction) {
                $payment_method = 'retur';
                $amount_paid = 0;
                $change_amount = 0;
                $is_receivable = false;
            } else {
                $is_receivable = ($amount_paid <= 0);
            }
            if ($is_receivable) {
                if ($cust_id === null || $cust_id <= 0) {
                    throw new Exception("Pilih member/pelanggan terlebih dahulu untuk transaksi piutang");
                }
                $payment_method = 'piutang';
                $change_amount = 0;
            } elseif ($amount_paid < $sale_total) {
                throw new Exception("Jumlah pembayaran kurang dari total");
            }

            if (empty($items) || !is_array($items)) {
                throw new Exception("Tidak ada produk dalam transaksi");
            }

            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            $checkStmt = $this->prepare("SELECT saleid, sale_invoice, sale_status, stock_applied FROM sale WHERE saleid = ? AND sale_invoice LIKE 'KSR-%' LIMIT 1 FOR UPDATE");
            $checkStmt->bind_param("i", $saleid);
            $checkStmt->execute();
            $sale = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$sale) {
                throw new Exception("Transaksi kasir tidak ditemukan");
            }

            if ($sale['sale_status'] !== 'ACTIVE') {
                throw new Exception("Transaksi tidak aktif, tidak dapat diedit");
            }

            $this->assertNoPostedReturns($saleid, 'diedit');

            $sale_invoice = (string)$sale['sale_invoice'];

            $oldDetailStmt = $this->prepare("SELECT produk_id, sale_qty, COALESCE(NULLIF(isi, 0), 1) AS isi FROM sale_det WHERE sale_id = ?");
            $oldDetailStmt->bind_param("i", $saleid);
            $oldDetailStmt->execute();
            $oldDetails = $oldDetailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $oldDetailStmt->close();

            if ((int)($sale['stock_applied'] ?? 0) === 1) {
                foreach ($oldDetails as $old) {
                    $restoreStmt = $this->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
                    $oldQty = $this->sanitizeQuantity($old['sale_qty'] ?? 0) * max(1, (int)($old['isi'] ?? 1));
                    $oldProductId = $this->sanitizeInt($old['produk_id'] ?? 0);
                    $restoreStmt->bind_param("di", $oldQty, $oldProductId);
                    if (!$restoreStmt->execute()) {
                        throw new Exception("Gagal mengembalikan stok lama");
                    }
                    $restoreStmt->close();
                }
            }

            $sale_ppn = 0;

            if ($cust_id === null || $cust_id == 0) {
                $updateSql = "UPDATE sale SET
                                sale_date = ?,
                                sale_invoice = ?,
                                sale_channel = 'kasir',
                                cust_id = NULL,
                                payment_method = ?,
                                catatan = ?,
                                sale_disc = ?,
                                sale_ppn = ?,
                                sale_total = ?,
                                amount_paid = ?,
                                change_amount = ?,
                                user_id = ?,
                                updated_at = NOW()
                              WHERE saleid = ? AND sale_status = 'ACTIVE'";
                $updateStmt = $this->prepare($updateSql);
                $updateStmt->bind_param(
                    "ssssdddddii",
                    $sale_date,
                    $sale_invoice,
                    $payment_method,
                    $catatan,
                    $sale_disc,
                    $sale_ppn,
                    $sale_total,
                    $amount_paid,
                    $change_amount,
                    $user_id,
                    $saleid
                );
            } else {
                $updateSql = "UPDATE sale SET
                                sale_date = ?,
                                sale_invoice = ?,
                                sale_channel = 'kasir',
                                cust_id = ?,
                                payment_method = ?,
                                catatan = ?,
                                sale_disc = ?,
                                sale_ppn = ?,
                                sale_total = ?,
                                amount_paid = ?,
                                change_amount = ?,
                                user_id = ?,
                                updated_at = NOW()
                              WHERE saleid = ? AND sale_status = 'ACTIVE'";
                $updateStmt = $this->prepare($updateSql);
                $updateStmt->bind_param(
                    "ssissdddddii",
                    $sale_date,
                    $sale_invoice,
                    $cust_id,
                    $payment_method,
                    $catatan,
                    $sale_disc,
                    $sale_ppn,
                    $sale_total,
                    $amount_paid,
                    $change_amount,
                    $user_id,
                    $saleid
                );
            }

            if (!$updateStmt->execute()) {
                throw new Exception("Gagal update header transaksi: " . $updateStmt->error);
            }
            $updateStmt->close();

            $bayarId = $is_receivable ? 2 : 1;
            $saleCredit = $is_receivable ? $sale_total : 0;
            $salePaid = $is_receivable ? 0 : 1;
            $paymentFlagStmt = $this->prepare("UPDATE sale SET bayar_id = ?, sale_credit = ?, sale_paid = ? WHERE saleid = ?");
            $paymentFlagStmt->bind_param("idii", $bayarId, $saleCredit, $salePaid, $saleid);
            if (!$paymentFlagStmt->execute()) {
                throw new Exception("Gagal update status bayar transaksi: " . $paymentFlagStmt->error);
            }
            $paymentFlagStmt->close();

            $deleteStmt = $this->prepare("DELETE FROM sale_det WHERE sale_id = ?");
            $deleteStmt->bind_param("i", $saleid);
            if (!$deleteStmt->execute()) {
                throw new Exception("Gagal menghapus detail lama: " . $deleteStmt->error);
            }
            $deleteStmt->close();

            $detailStmt = $this->prepare("INSERT INTO sale_det
                    (sale_id, produk_id, sale_qty, sale_harga, sale_pdisc, sale_capital, sale_purchase_cost, ppn_item, is_ppn, isi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $success_count = 0;
            $salePpnTotal = 0.0;
            $calculatedSubtotal = 0.0;
            $invoiceGross = $this->amountBeforeSignedDiscount($sale_total, $sale_disc);
            foreach ($items as $item) {
                $produk_id = $this->sanitizeInt($item['produk_id'] ?? 0);
                $qty = $this->sanitizeQuantity($item['qty'] ?? 0);
                $harga = $this->sanitizeFloat($item['harga'] ?? 0);
                $disc = $this->sanitizeFloat($item['disc_item'] ?? 0);
                $capital = $this->sanitizeFloat($item['sale_capital'] ?? 0);
                $isi = $this->sanitizeInt($item['isi'] ?? 1);

                if ($produk_id <= 0) {
                    continue;
                }

                if ($qty == 0) {
                    throw new Exception("Quantity produk tidak valid");
                }

                if ($harga < 0 || $harga > 10000000) {
                    throw new Exception("Harga produk tidak valid");
                }

                if ($isi <= 0) {
                    throw new Exception("Isi kemasan tidak valid");
                }

                $produkPricing = $this->getProductPricing($produk_id);
                $isPpn = (int)($produkPricing['is_ppn'] ?? 0);
                $masterPrice = (float)($produkPricing['harga_kasir'] ?: $produkPricing['harga_sales'] ?: 0);
                if ($isPpn === 1 && $masterPrice > 0 && abs($harga - $masterPrice) < 0.01) {
                    $harga = $this->priceWithItemPpn($harga, 1);
                }

                $capital = (float)($produkPricing['harga_newmodal'] ?? 0);
                $purchase_cost = (float)($produkPricing['harga_beli'] ?? 0);
                if ($harga < $capital) {
                    throw new Exception("Harga jual " . ($produkPricing['nama_produk'] ?? 'produk') . " tidak boleh kurang dari New Modal Rp " . number_format($capital, 0, ',', '.'));
                }

                $stockQty = $qty * $isi;
                $stockStmt = $stockQty > 0
                    ? $this->prepare("UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?")
                    : $this->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                if ($stockQty > 0) {
                    $stockStmt->bind_param("did", $stockQty, $produk_id, $stockQty);
                } else {
                    $stockStmt->bind_param("di", $stockQty, $produk_id);
                }
                if (!$stockStmt->execute()) {
                    throw new Exception("Gagal update stok produk ID: " . $produk_id);
                }
                if ($stockQty > 0 && $stockStmt->affected_rows === 0) {
                    throw new Exception("Stok tidak cukup untuk produk ID: " . $produk_id);
                }
                $stockStmt->close();

                $lineGross = $this->applySignedDiscount(
                    $qty * $harga,
                    $disc,
                    'Diskon item ' . ($produkPricing['nama_produk'] ?? $produk_id)
                );
                $calculatedSubtotal += $lineGross;
                $lineAfterInvoiceDiscount = $this->applyInvoiceDiscountToLine($lineGross, $invoiceGross, $sale_disc);
                $ppn_item = $this->ppnFromGrossAmount($lineAfterInvoiceDiscount, $isPpn);

                $detailStmt->bind_param(
                    "iiddddddii",
                    $saleid,
                    $produk_id,
                    $qty,
                    $harga,
                    $disc,
                    $capital,
                    $purchase_cost,
                    $ppn_item,
                    $isPpn,
                    $isi
                );

                if (!$detailStmt->execute()) {
                    throw new Exception("Gagal menyimpan detail produk ID: " . $produk_id . ": " . $detailStmt->error);
                }
                $salePpnTotal += $ppn_item;
                $success_count++;
            }
            $detailStmt->close();

            if ($success_count === 0) {
                throw new Exception("Tidak ada produk yang valid untuk disimpan");
            }

            $calculatedSaleTotal = round($this->applySignedDiscount($calculatedSubtotal, $sale_disc, 'Diskon faktur'));
            if (abs($sale_total - $calculatedSaleTotal) > 1) {
                throw new Exception("Total transaksi tidak sesuai hasil perhitungan server. Silakan hitung ulang transaksi");
            }
            $sale_total = $calculatedSaleTotal;
            $saleCredit = $is_receivable ? $sale_total : 0;
            if (!$is_minus_transaction && !$is_receivable && $amount_paid < $sale_total) {
                throw new Exception("Jumlah pembayaran kurang dari total hasil perhitungan server");
            }
            $change_amount = (!$is_minus_transaction && !$is_receivable)
                ? round(max($amount_paid - $sale_total, 0), 2)
                : 0.0;

            $ppnUpdateStmt = $this->prepare("UPDATE sale SET sale_ppn = ?, sale_total = ?, sale_credit = ?, change_amount = ?, stock_applied = 1 WHERE saleid = ?");
            $ppnUpdateStmt->bind_param("ddddi", $salePpnTotal, $sale_total, $saleCredit, $change_amount, $saleid);
            if (!$ppnUpdateStmt->execute()) {
                throw new Exception("Gagal update total PPN kasir: " . $ppnUpdateStmt->error);
            }
            $ppnUpdateStmt->close();

            $points_earned = 0;
            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            if (($cust_id ?? 0) > 0) {
                $pointsResult = $rewardSystem->syncEarnPointsForTransaction($cust_id, $saleid, $sale_total);
                $points_earned = (int)($pointsResult['points_earned'] ?? 0);
            } else {
                $rewardSystem->cancelPointsForTransaction($saleid);
            }

            $piutangData = $this->syncKasirReceivable($sale_invoice, $sale['sale_invoice'] ?? '', $cust_id ?? 0, $sale_date, $sale_total, $amount_paid, $catatan, $user_id);

            $this->logActivity("Update Transaksi Kasir", "sale", $saleid, "Edit kasir: $sale_invoice - Total: Rp " . number_format($sale_total, 0, ',', '.'));
            if ($minusAuthorizer) {
                $this->logMinusAuthorization(
                    $minusAuthorizer,
                    $saleid,
                    $sale_invoice,
                    $items,
                    $sale_total,
                    "Update Penjualan Kasir"
                );
            }

            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            $successMessage = "Transaksi kasir berhasil diperbarui";
            if ($piutangData) {
                $successMessage .= " dan masuk piutang pelanggan";
            }
            if ($points_earned > 0) {
                $successMessage .= " (+{$points_earned} poin)";
            }

            echo json_encode([
                "status" => "success",
                "msg" => $successMessage,
                "success" => true,
                "data" => [
                    "saleid" => $saleid,
                    "sale_invoice" => $sale_invoice,
                    "sale_total" => $sale_total,
                    "amount_paid" => $amount_paid,
                    "change_amount" => $change_amount,
                    "payment_method" => $payment_method,
                    "piutang" => $piutangData,
                    "points_earned" => $points_earned
                ]
            ]);
        } catch (Exception $e) {
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            error_log("UPDATE_PENJUALAN_KASIR ERROR: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "success" => false,
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    function search_produk_kasir()
    {
        try {
            $term = trim($_GET['term'] ?? '');

            if ($term === '') {
                echo json_encode(["status" => "success", "data" => null]);
                exit;
            }

            $sql = "SELECT 
                        p.id, 
                        p.nama_produk, 
                        p.barcode, 
                        p.stok, 
                        p.harga_kasir as harga_jual,
                        p.harga_newmodal as harga_modal,
                        p.is_ppn,
                        p.gambar_produk,
                        s.nama_satuan 
                    FROM produk p
                    LEFT JOIN satuan s ON s.id = p.id_satuan
                    WHERE p.status = 1 
                    AND (p.barcode = ? OR p.nama_produk LIKE ?)
                    LIMIT 1";

            $stmt = $this->prepare($sql);
            $like = '%' . $term . '%';
            $stmt->bind_param("ss", $term, $like);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $row = $res->fetch_assoc()) {
                $isPpn = (int)$row['is_ppn'];
                $hargaJualDasar = (float)$row['harga_jual'];
                $hargaJual = $this->priceWithItemPpn($hargaJualDasar, $isPpn);
                $data = [
                    "id" => $row['id'],
                    "nama_produk" => $row['nama_produk'],
                    "barcode" => $row['barcode'],
                    "satuan" => $row['nama_satuan'],
                    "stok" => (float)$row['stok'],
                    "harga_jual" => $hargaJual,
                    "harga_jual_dasar" => $hargaJualDasar,
                    "harga_modal" => $row['harga_modal'],
                    "is_ppn" => $isPpn,
                    "gambar_produk" => $row['gambar_produk'] ?? '',
                    "gambar_url" => !empty($row['gambar_produk'])
                        ? base_url . 'uploads/' . rawurlencode(basename($row['gambar_produk']))
                        : base_url . 'uploads/no-image-available.png'
                ];

                echo json_encode([
                    "status" => "success",
                    "data" => $data
                ]);
            } else {
                echo json_encode([
                    "status" => "failed",
                    "msg" => "Produk tidak ditemukan"
                ]);
            }

            $stmt->close();
            exit;
        } catch (Exception $e) {
            error_log("SEARCH_PRODUK_KASIR ERROR: " . $e->getMessage());
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
            exit;
        }
    }

    function get_today_summary()
    {
        try {
            $user_id = 0;
            if (isset($_SESSION['userdata']['id'])) {
                $user_id = $this->sanitizeInt($_SESSION['userdata']['id']);
            }

            $today = date('Y-m-d');

            $sql_total = "SELECT 
                            COUNT(*) as transaction_count,
                            COALESCE(SUM(sale_total), 0) as total_sales,
                            COALESCE(AVG(sale_total), 0) as avg_transaction
                          FROM sale 
                          WHERE DATE(sale_date) = ? 
                          AND sale_status = 'ACTIVE' 
                          AND sale_invoice LIKE 'KSR-%'
                          AND user_id = ?";

            $stmt = $this->prepare($sql_total);
            $stmt->bind_param("si", $today, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_assoc();

            echo json_encode([
                "status" => "success",
                "data" => [
                    "transaction_count" => $data['transaction_count'] ?? 0,
                    "total_sales" => $data['total_sales'] ?? 0,
                    "avg_transaction" => $data['avg_transaction'] ?? 0
                ]
            ]);

            $stmt->close();
        } catch (Exception $e) {
            error_log("GET_TODAY_SUMMARY ERROR: " . $e->getMessage());
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    function get_pelanggan_kasir()
    {
        try {
            // Saldo poin kanonis berasal dari customer_points untuk semua kanal.
            $sql = "SELECT 
                        p.id, 
                        p.nama,
                        p.alamat,
                        p.kode,
                        COALESCE(cp.points_total, 0) as points_total,
                        COALESCE(sc.transaksi_count, 0) as transaksi_count
                    FROM pelanggan p
                    LEFT JOIN (
                        SELECT pelanggan_id, SUM(points) AS points_total
                        FROM customer_points
                        WHERE expired_at IS NULL OR expired_at > NOW() OR points < 0
                        GROUP BY pelanggan_id
                    ) cp ON cp.pelanggan_id = p.id
                    LEFT JOIN (
                        SELECT cust_id, COUNT(*) AS transaksi_count
                        FROM sale
                        WHERE sale_status = 'ACTIVE'
                        GROUP BY cust_id
                    ) sc ON sc.cust_id = p.id
                    WHERE p.status = 1
                    ORDER BY points_total DESC, transaksi_count DESC, p.nama ASC
                    LIMIT 100";

            $stmt = $this->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    "id" => (int)$row['id'],
                    "kode" => $row['kode'],
                    "nama" => $row['nama'],
                    "alamat" => $row['alamat'],
                    "points" => max(0, (int)$row['points_total']),  // Ensure non-negative
                    "transaksi_count" => (int)$row['transaksi_count']
                ];
            }

            error_log("GET_PELANGGAN_KASIR: Total members returned: " . count($data));

            echo json_encode([
                "status" => "success",
                "data" => $data,
                "total" => count($data)
            ]);

            $stmt->close();
        } catch (Exception $e) {
            error_log("GET_PELANGGAN_KASIR ERROR: " . $e->getMessage());
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage(),
                "data" => []
            ]);
        }
        exit;
    }

    function get_transaction_detail()
    {
        try {
            $saleid = $this->sanitizeInt($_GET['id'] ?? 0);

            if ($saleid <= 0) {
                throw new Exception("ID Transaksi tidak valid");
            }

            $sql = "SELECT 
                        s.*,
                        p.nama as pelanggan_nama,
                        u.username
                    FROM sale s 
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    LEFT JOIN users u ON u.id = s.user_id
                    WHERE s.saleid = ? AND s.sale_status = 'ACTIVE'";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $saleid);
            $stmt->execute();
            $result = $stmt->get_result();
            $header = $result->fetch_assoc();

            if (!$header) {
                throw new Exception("Data transaksi tidak ditemukan");
            }

            $sql_detail = "SELECT 
                            sd.*, 
                            pr.nama_produk, 
                            pr.barcode,
                            pr.stok,
                            pr.is_ppn,
                            pr.id_satuan,
                            pr.harga_kasir,
                            pr.harga_sales,
                            pr.harga_newmodal,
                            pr.gambar_produk,
                            s2.nama_satuan
                          FROM sale_det sd
                          LEFT JOIN produk pr ON pr.id = sd.produk_id
                          LEFT JOIN satuan s2 ON s2.id = pr.id_satuan
                          WHERE sd.sale_id = ?";

            $stmt_detail = $this->prepare($sql_detail);
            $stmt_detail->bind_param("i", $saleid);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            $details = [];

            while ($row = $result_detail->fetch_assoc()) {
                $row['gambar_url'] = !empty($row['gambar_produk'])
                    ? base_url . 'uploads/' . rawurlencode(basename($row['gambar_produk']))
                    : base_url . 'uploads/no-image-available.png';
                $details[] = $row;
            }

            [$details, $subtotal] = $this->normalizeSaleDetailPrices($details, $header['sale_total'] ?? 0);

            echo json_encode([
                "status" => "success",
                "data" => [
                    "header" => $header,
                    "items" => $details,
                    "subtotal" => $subtotal,
                    "discount" => $header['sale_disc'],
                    "ppn" => $header['sale_ppn'],
                    "total" => $header['sale_total'],
                    "amount_paid" => $header['amount_paid'],
                    "change_amount" => $header['change_amount']
                ]
            ]);

            $stmt->close();
            $stmt_detail->close();
        } catch (Exception $e) {
            error_log("GET_TRANSACTION_DETAIL ERROR: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    function void_transaction()
    {
        try {
            $this->ensureConnection();

            $saleid = $this->sanitizeInt($_POST['saleid'] ?? 0);

            if ($saleid <= 0) {
                throw new Exception("ID Transaksi tidak valid");
            }

            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            $cek = $this->fetchOne("SELECT sale_invoice, sale_total, stock_applied FROM sale WHERE saleid = ? AND sale_status = 'ACTIVE' AND sale_invoice LIKE 'KSR-%' FOR UPDATE", [$saleid]);
            if (!$cek) {
                throw new Exception("Data transaksi tidak ditemukan atau sudah di-void");
            }

            $this->assertNoPostedReturns($saleid, 'di-void');

            if ((int)($cek['stock_applied'] ?? 0) === 1) {
                $detail_sql = "SELECT produk_id, sale_qty, COALESCE(NULLIF(isi, 0), 1) AS isi FROM sale_det WHERE sale_id = ?";
                $detail_stmt = $this->conn->prepare($detail_sql);
                $detail_stmt->bind_param("i", $saleid);
                $detail_stmt->execute();
                $detail_result = $detail_stmt->get_result();

                while ($detail = $detail_result->fetch_assoc()) {
                    $update_stok_sql = "UPDATE produk SET stok = stok + ? WHERE id = ?";
                    $update_stmt = $this->conn->prepare($update_stok_sql);
                    $stockQty = (float)$detail['sale_qty'] * max(1, (int)$detail['isi']);
                    $update_stmt->bind_param("di", $stockQty, $detail['produk_id']);
                    if (!$update_stmt->execute()) {
                        throw new Exception('Gagal mengembalikan stok transaksi void');
                    }
                    $update_stmt->close();
                }
                $detail_stmt->close();
            }

            $update_sql = "UPDATE sale 
                          SET sale_status = 'VOID', stock_applied = 0, updated_at = NOW(), catatan = CONCAT(COALESCE(catatan, ''), ' [VOIDED BY KASIR]')
                          WHERE saleid = ? AND sale_status = 'ACTIVE'";

            $update_stmt = $this->conn->prepare($update_sql);
            $update_stmt->bind_param("i", $saleid);
            $update_stmt->execute();

            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Gagal melakukan void transaksi");
            }
            $update_stmt->close();

            $this->removeKasirReceivable($cek['sale_invoice'] ?? '', true);

            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $rewardSystem->cancelPointsForTransaction($saleid);

            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            $this->logActivity(
                "Void Transaksi Kasir",
                "sale",
                $saleid,
                "Void transaksi kasir: {$cek['sale_invoice']} - Total: Rp " . number_format($cek['sale_total'], 0, ',', '.')
            );

            echo json_encode([
                "status" => "success",
                "msg" => "Transaksi berhasil di-void",
                "saleid" => $saleid
            ]);
        } catch (Exception $e) {
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            error_log("VOID_TRANSACTION ERROR: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    function print_receipt()
    {
        try {
            $saleid = $this->sanitizeInt($_GET['id'] ?? 0);

            if ($saleid <= 0) {
                throw new Exception("ID Transaksi tidak valid");
            }

            $sql = "SELECT 
                        s.*,
                        p.nama as pelanggan_nama,
                        u.username as kasir_name
                    FROM sale s 
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    LEFT JOIN users u ON u.id = s.user_id
                    WHERE s.saleid = ?";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $saleid);
            $stmt->execute();
            $result = $stmt->get_result();
            $header = $result->fetch_assoc();

            if (!$header) {
                throw new Exception("Data transaksi tidak ditemukan");
            }

            $sql_detail = "SELECT 
                            sd.*, 
                            pr.nama_produk,
                            pr.harga_kasir,
                            pr.harga_sales,
                            pr.harga_newmodal,
                            s2.nama_satuan
                          FROM sale_det sd
                          LEFT JOIN produk pr ON pr.id = sd.produk_id
                          LEFT JOIN satuan s2 ON s2.id = pr.id_satuan
                          WHERE sd.sale_id = ?";

            $stmt_detail = $this->prepare($sql_detail);
            $stmt_detail->bind_param("i", $saleid);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            $details = [];

            while ($row = $result_detail->fetch_assoc()) {
                $details[] = $row;
            }

            [$details] = $this->normalizeSaleDetailPrices($details, $header['sale_total'] ?? 0);
            $html = $this->generateReceiptHTML($header, $details);

            header('Content-Type: text/html; charset=utf-8');
            echo $html;

            $stmt->close();
            $stmt_detail->close();
        } catch (Exception $e) {
            error_log("PRINT_RECEIPT ERROR: " . $e->getMessage());
            echo "<h3>Error: " . $e->getMessage() . "</h3>";
        }
        exit;
    }

    function export_excel_kasir()
    {
        try {
            $tgl = $this->sanitizeDate($_GET['tgl'] ?? date('Y-m-d'));
            $user_id = $this->sanitizeInt($_GET['user_id'] ?? 0);
            $payment_method = $this->sanitizeString($_GET['payment_method'] ?? '');
            $invoice = $this->sanitizeString($_GET['invoice'] ?? '');

            $whereParts = ["s.sale_status = 'ACTIVE'", "s.sale_invoice LIKE 'KSR-%'"];
            $params = [];
            $types = "";

            if (!empty($tgl)) {
                $whereParts[] = "DATE(s.sale_date) = ?";
                $params[] = $tgl;
                $types .= "s";
            }

            if ($user_id > 0) {
                $whereParts[] = "s.user_id = ?";
                $params[] = $user_id;
                $types .= "i";
            }

            if (!empty($payment_method)) {
                $whereParts[] = "s.payment_method = ?";
                $params[] = $payment_method;
                $types .= "s";
            }

            if (!empty($invoice)) {
                $whereParts[] = "s.sale_invoice LIKE ?";
                $params[] = "%{$invoice}%";
                $types .= "s";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            $sql = "SELECT 
                        s.sale_date,
                        s.sale_invoice,
                        u.username as kasir,
                        CASE s.payment_method 
                            WHEN 'cash' THEN 'Tunai'
                            WHEN 'debit_card' THEN 'Kartu Debit'
                            WHEN 'credit_card' THEN 'Kartu Kredit'
                            WHEN 'qris' THEN 'QRIS'
                            WHEN 'e_wallet' THEN 'E-Wallet'
                            WHEN 'piutang' THEN 'Piutang'
                            ELSE s.payment_method
                        END as metode_bayar,
                        s.sale_total,
                        s.amount_paid,
                        s.change_amount,
                        s.catatan
                    FROM sale s
                    LEFT JOIN users u ON u.id = s.user_id
                    $where
                    ORDER BY s.sale_date DESC";

            $stmt = $this->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="laporan_kasir_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo "LAPORAN TRANSAKSI KASIR\n";
            echo "Tanggal: " . date('d/m/Y', strtotime($tgl)) . "\n\n";

            echo "Waktu\tNo Invoice\tKasir\tMetode Bayar\tTotal\tBayar\tKembali\tCatatan\n";

            $total_all = 0;
            $total_paid = 0;
            $total_change = 0;

            while ($row = $res->fetch_assoc()) {
                echo date('d/m/Y H:i', strtotime($row['sale_date'])) . "\t";
                echo $row['sale_invoice'] . "\t";
                echo $row['kasir'] . "\t";
                echo $row['metode_bayar'] . "\t";
                echo number_format($row['sale_total'], 0, ',', '.') . "\t";
                echo number_format($row['amount_paid'], 0, ',', '.') . "\t";
                echo number_format($row['change_amount'], 0, ',', '.') . "\t";
                echo $row['catatan'] . "\n";

                $total_all += $row['sale_total'];
                $total_paid += $row['amount_paid'];
                $total_change += $row['change_amount'];
            }

            echo "\n\nSUMMARY:\n";
            echo "Total Transaksi: " . number_format($total_all, 0, ',', '.') . "\n";
            echo "Total Uang Masuk: " . number_format($total_paid, 0, ',', '.') . "\n";
            echo "Total Kembalian: " . number_format($total_change, 0, ',', '.') . "\n";

            $stmt->close();
            exit;
        } catch (Exception $e) {
            error_log("EXPORT_EXCEL_KASIR ERROR: " . $e->getMessage());
            echo "Error: " . $e->getMessage();
        }
        exit;
    }

    function today_report()
    {
        try {
            $user_id = $this->sanitizeInt($_GET['user_id'] ?? 0);
            $today = date('Y-m-d');

            $sql = "SELECT 
                        s.sale_date,
                        s.sale_invoice,
                        s.payment_method,
                        s.sale_total,
                        s.amount_paid,
                        s.change_amount,
                        COUNT(sd.id) as item_count
                    FROM sale s
                    LEFT JOIN sale_det sd ON sd.sale_id = s.saleid
                    WHERE DATE(s.sale_date) = ? 
                    AND s.sale_status = 'ACTIVE' 
                    AND s.sale_invoice LIKE 'KSR-%'
                    AND s.user_id = ?
                    GROUP BY s.saleid
                    ORDER BY s.sale_date ASC";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("si", $today, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();

            $html = $this->generateDailyReportHTML($res, $today, $user_id);

            header('Content-Type: text/html; charset=utf-8');
            echo $html;

            $stmt->close();
        } catch (Exception $e) {
            error_log("TODAY_REPORT ERROR: " . $e->getMessage());
            echo "<h3>Error: " . $e->getMessage() . "</h3>";
        }
        exit;
    }

    function print_daily_report()
    {
        try {
            $tgl = $this->sanitizeDate($_GET['tgl'] ?? date('Y-m-d'));
            $user_id = $this->sanitizeInt($_GET['user_id'] ?? 0);

            $sql = "SELECT 
                        s.*,
                        u.username,
                        p.nama as pelanggan_nama,
                        (SELECT COUNT(*) FROM sale_det WHERE sale_id = s.saleid) as item_count
                    FROM sale s
                    LEFT JOIN users u ON u.id = s.user_id
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    WHERE DATE(s.sale_date) = ? 
                    AND s.sale_status = 'ACTIVE' 
                    AND s.sale_invoice LIKE 'KSR-%'
                    " . ($user_id > 0 ? " AND s.user_id = ?" : "") . "
                    ORDER BY s.sale_date ASC";

            $stmt = $this->prepare($sql);
            if ($user_id > 0) {
                $stmt->bind_param("si", $tgl, $user_id);
            } else {
                $stmt->bind_param("s", $tgl);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            $html = $this->generatePrintReportHTML($res, $tgl, $user_id);

            header('Content-Type: text/html; charset=utf-8');
            echo $html;

            $stmt->close();
        } catch (Exception $e) {
            error_log("PRINT_DAILY_REPORT ERROR: " . $e->getMessage());
            echo "<h3>Error: " . $e->getMessage() . "</h3>";
        }
        exit;
    }

    // Helper methods
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
        $stmt = $this->conn->prepare("
            SELECT no_piutang
            FROM piutang_pelanggan
            WHERE no_piutang LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare nomor piutang gagal: " . $this->conn->error);
        }

        $stmt->bind_param("s", $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $next = 1;
        if (!empty($row['no_piutang']) && preg_match('/(\d+)$/', $row['no_piutang'], $matches)) {
            $next = ((int)$matches[1]) + 1;
        }

        return $prefix . str_pad($next, 4, "0", STR_PAD_LEFT);
    }

    private function piutangStatus($total, $received, $dueDate)
    {
        $remaining = max((float)$total - (float)$received, 0);
        if ($remaining <= 0) {
            return "LUNAS";
        }

        if (!empty($dueDate) && strtotime($dueDate) < strtotime(date("Y-m-d"))) {
            return "OVERDUE";
        }

        return ((float)$received > 0) ? "SEBAGIAN" : "BELUM";
    }

    private function syncKasirReceivable($invoice, $oldInvoice, $custId, $saleDate, $saleTotal, $amountPaid, $catatan, $userId)
    {
        $invoice = trim((string)$invoice);
        $oldInvoice = trim((string)$oldInvoice);
        $shouldCreatePiutang = ((float)$amountPaid <= 0 && (float)$saleTotal > 0);

        if (!$shouldCreatePiutang) {
            $this->removeKasirReceivable($oldInvoice ?: $invoice, true);
            if ($oldInvoice !== '' && $oldInvoice !== $invoice) {
                $this->removeKasirReceivable($invoice, true);
            }
            return null;
        }

        if ((int)$custId <= 0) {
            throw new Exception("Pelanggan wajib dipilih jika pembayaran 0/piutang");
        }

        $tanggalPiutang = date('Y-m-d', strtotime($saleDate ?: date('Y-m-d')));
        $tanggalJatuhTempo = date('Y-m-d', strtotime($tanggalPiutang . ' +30 days'));
        $lookupOld = $oldInvoice !== '' ? $oldInvoice : $invoice;

        $stmt = $this->conn->prepare("
            SELECT id, total_diterima
            FROM piutang_pelanggan
            WHERE ref_penjualan = ? OR ref_penjualan = ?
            ORDER BY id ASC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare cek piutang gagal: " . $this->conn->error);
        }

        $stmt->bind_param("ss", $invoice, $lookupOld);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $totalDiterima = (float)($existing['total_diterima'] ?? 0);
        if ($totalDiterima > (float)$saleTotal) {
            throw new Exception("Total transaksi tidak boleh lebih kecil dari penerimaan piutang yang sudah tercatat");
        }

        $sisaPiutang = max((float)$saleTotal - $totalDiterima, 0);
        $status = $this->piutangStatus($saleTotal, $totalDiterima, $tanggalJatuhTempo);
        $keterangan = trim($catatan) !== '' ? $catatan : "Piutang otomatis dari kasir {$invoice}";

        if ($existing) {
            $piutangId = (int)$existing['id'];
            $stmt = $this->conn->prepare("
                UPDATE piutang_pelanggan
                   SET tanggal_piutang = ?,
                       tanggal_jatuh_tempo = ?,
                       pelanggan_id = ?,
                       total_piutang = ?,
                       sisa_piutang = ?,
                       status = ?,
                       keterangan = ?,
                       ref_penjualan = ?,
                       updated_at = NOW()
                 WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception("Prepare update piutang gagal: " . $this->conn->error);
            }
            $stmt->bind_param("ssiddsssi", $tanggalPiutang, $tanggalJatuhTempo, $custId, $saleTotal, $sisaPiutang, $status, $keterangan, $invoice, $piutangId);
            if (!$stmt->execute()) {
                throw new Exception("Update piutang kasir gagal: " . $stmt->error);
            }
            $stmt->close();

            return [
                'id' => $piutangId,
                'no_piutang' => null,
                'sisa_piutang' => $sisaPiutang,
            ];
        }

        $noPiutang = $this->nextPiutangNumber();
        $stmt = $this->conn->prepare("
            INSERT INTO piutang_pelanggan
                (no_piutang, tanggal_piutang, tanggal_jatuh_tempo, pelanggan_id, total_piutang,
                 total_diterima, sisa_piutang, status, keterangan, ref_penjualan, user_id, tgl_input)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception("Prepare insert piutang gagal: " . $this->conn->error);
        }

        $stmt->bind_param("sssiddsssi", $noPiutang, $tanggalPiutang, $tanggalJatuhTempo, $custId, $saleTotal, $sisaPiutang, $status, $keterangan, $invoice, $userId);
        if (!$stmt->execute()) {
            throw new Exception("Insert piutang kasir gagal: " . $stmt->error);
        }
        $piutangId = $stmt->insert_id;
        $stmt->close();

        return [
            'id' => $piutangId,
            'no_piutang' => $noPiutang,
            'sisa_piutang' => $sisaPiutang,
        ];
    }

    private function removeKasirReceivable($invoice, $ignoreMissing = false)
    {
        $invoice = trim((string)$invoice);
        if ($invoice === '') {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT p.id, COUNT(r.id) AS total_penerimaan
            FROM piutang_pelanggan p
            LEFT JOIN penerimaan_piutang r ON r.piutang_id = p.id
            WHERE p.ref_penjualan = ?
            GROUP BY p.id
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare cek piutang kasir gagal: " . $this->conn->error);
        }

        $stmt->bind_param("s", $invoice);
        $stmt->execute();
        $piutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$piutang) {
            if (!$ignoreMissing) {
                return;
            }
            return;
        }

        if ((int)$piutang['total_penerimaan'] > 0) {
            throw new Exception("Transaksi tidak dapat diubah menjadi lunas karena piutangnya sudah memiliki penerimaan");
        }

        $stmt = $this->conn->prepare("DELETE FROM piutang_pelanggan WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare hapus piutang kasir gagal: " . $this->conn->error);
        }
        $piutangId = (int)$piutang['id'];
        $stmt->bind_param("i", $piutangId);
        if (!$stmt->execute()) {
            throw new Exception("Hapus piutang kasir gagal: " . $stmt->error);
        }
        $stmt->close();
    }

    private function generateInvoiceKasir()
    {
        $date = date('Ymd');
        $time = date('His');
        $random = rand(100, 999);
        return "KSR-{$date}-{$time}-{$random}";
    }

    private function getPaymentMethodText($method)
    {
        $methods = [
            'cash' => 'Tunai',
            'debit_card' => 'Kartu Debit',
            'credit_card' => 'Kartu Kredit',
            'qris' => 'QRIS',
            'e_wallet' => 'E-Wallet',
            'piutang' => 'Piutang'
        ];
        return $methods[$method] ?? $method;
    }

    private function formatRupiah($amount)
    {
        return number_format($amount, 0, ',', '.');
    }

    private function receiptDateTime($header)
    {
        $saleDate = $header['sale_date'] ?? '';
        $timestamp = strtotime($saleDate);

        if ($timestamp === false) {
            $timestamp = time();
        }

        if (date('H:i:s', $timestamp) === '00:00:00' && !empty($header['updated_at'])) {
            $updatedTimestamp = strtotime($header['updated_at']);
            if ($updatedTimestamp !== false) {
                $timestamp = $updatedTimestamp;
            }
        }

        return date('d/m/Y H:i:s', $timestamp);
    }

    private function generateReceiptHTML($header, $details)
    {
        $company_name = $this->settings ? trim((string)$this->settings->info('name')) : '';
        $company_address = $this->settings ? trim((string)$this->settings->info('alamat')) : '';
        $company_phone = $this->settings ? trim((string)$this->settings->info('telpon')) : '';

        // Tetap dukung nama field lama bila data berasal dari instalasi versi sebelumnya.
        if ($company_address === '' && $this->settings) {
            $company_address = trim((string)$this->settings->info('address'));
        }
        if ($company_phone === '' && $this->settings) {
            $company_phone = trim((string)$this->settings->info('contact'));
        }
        if ($company_phone === '' && $this->settings) {
            $company_phone = trim((string)$this->settings->info('phone'));
        }

        $company_name = htmlspecialchars($company_name !== '' ? $company_name : 'TOKO KITA', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $company_address = htmlspecialchars($company_address, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $company_phone = htmlspecialchars($company_phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $companyInfoHtml = $company_address !== ''
            ? '<div class="company-info">' . $company_address . '</div>'
            : '';
        if ($company_phone !== '') {
            $companyInfoHtml .= '<div class="company-info">Telp: ' . $company_phone . '</div>';
        }

        $date_time = $this->receiptDateTime($header);
        $invoice = htmlspecialchars((string)($header['sale_invoice'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cashier = htmlspecialchars((string)($header['kasir_name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $customer = htmlspecialchars((string)($header['pelanggan_nama'] ?: 'Pelanggan Umum'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $invoiceDiscount = max(0, (float)($header['sale_disc'] ?? 0));
        $discountedItemCount = 0;
        foreach ($details as $detail) {
            if ((float)($detail['sale_qty'] ?? 0) > 0 && (float)($detail['sale_pdisc'] ?? 0) > 0) {
                $discountedItemCount++;
            }
        }
        $paperHeight = max(150, 112 + (count($details) * 7) + ($discountedItemCount * 4));

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Struk - ' . $invoice . '</title>
    <style>
        @page { size: 80mm ' . $paperHeight . 'mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Courier New", monospace; }
        html, body { width: 80mm; margin: 0; padding: 0; background: #fff; }
        body { padding: 4mm; font-size: 11px; line-height: 1.3; }
        .header { text-align: center; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #000; }
        .company-name { font-weight: bold; font-size: 13px; margin-bottom: 3px; }
        .company-info { font-size: 10px; margin-bottom: 2px; overflow-wrap: anywhere; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 10px; }
        .item-row { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 2px; padding-bottom: 3px; border-bottom: 1px dotted #ccc; font-size: 10px; }
        .item-product { flex: 2; min-width: 0; padding-right: 3px; }
        .item-name { overflow-wrap: anywhere; }
        .item-saving { margin-top: 1px; font-size: 9px; line-height: 1.2; font-weight: bold; }
        .item-header { display: flex; justify-content: space-between; margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px solid #000; font-weight: bold; font-size: 10px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 2px; font-size: 10px; }
        .total-row { font-weight: bold; font-size: 11px; margin-top: 4px; padding-top: 4px; border-top: 1px solid #000; }
        .footer { text-align: center; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #000; font-size: 9px; line-height: 1.4; }
        @media print { 
            .no-print { display: none; } 
            html, body { width: 80mm; margin: 0; padding: 0; }
            body { padding: 4mm; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">' . $company_name . '</div>
        ' . $companyInfoHtml . '
    </div>
    
    <div>
        <div class="info-row">
            <span>No. Invoice:</span>
            <span><strong>' . $invoice . '</strong></span>
        </div>
        <div class="info-row">
            <span>Tanggal:</span>
            <span>' . $date_time . '</span>
        </div>
        <div class="info-row">
            <span>Kasir:</span>
            <span>' . $cashier . '</span>
        </div>
        <div class="info-row">
            <span>Pelanggan:</span>
            <span>' . $customer . '</span>
        </div>
        <div class="info-row">
            <span>Metode Bayar:</span>
            <span>' . $this->getPaymentMethodText($header['payment_method']) . '</span>
        </div>
    </div>
    
    <div style="margin-top: 8px;">
        <div class="item-header">
            <div style="flex: 2;">PRODUK</div>
            <div style="flex: 1; text-align: center;">QTY</div>
            <div style="flex: 2; text-align: right;">TOTAL</div>
        </div>';

        foreach ($details as $item) {
            $name = htmlspecialchars((string)($item['nama_produk'] ?? 'Produk'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $qty = $this->formatQty($item['sale_qty']);
            $lineBase = (float)$item['sale_qty'] * (float)$item['sale_harga'];
            $itemDiscount = max(0, (float)($item['sale_pdisc'] ?? 0));
            $lineTotal = $this->applySignedDiscount(
                $lineBase,
                $itemDiscount,
                'Diskon item'
            );
            $total = number_format($lineTotal, 0, ',', '.');
            $savingHtml = $lineBase > 0 && $itemDiscount > 0
                ? '<div class="item-saving">Anda hemat Rp. ' . $this->formatRupiah($itemDiscount) . '</div>'
                : '';

            $html .= '
        <div class="item-row">
            <div class="item-product">
                <div class="item-name">' . $name . '</div>
                ' . $savingHtml . '
            </div>
            <div style="flex: 1; text-align: center;">' . $qty . '</div>
            <div style="flex: 2; text-align: right;">Rp ' . $total . '</div>
        </div>';
        }

        $subtotal = $this->amountBeforeSignedDiscount($header['sale_total'], $invoiceDiscount);
        $html .= '
    </div>
    
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #000;">
        <div class="summary-row">
            <span>Subtotal:</span>
            <span>Rp ' . $this->formatRupiah($subtotal) . '</span>
        </div>';

        if ($invoiceDiscount > 0) {
            $html .= '
        <div class="summary-row">
            <span>Diskon:</span>
            <span>-Rp ' . $this->formatRupiah($invoiceDiscount) . '</span>
        </div>';
        }

        $html .= '
        <div class="summary-row total-row">
            <span>TOTAL:</span>
            <span>Rp ' . $this->formatRupiah($header['sale_total']) . '</span>
        </div>
        
        <div style="margin-top: 5px; padding-top: 5px; border-top: 1px dashed #000;">
            <div class="summary-row">
                <span>BAYAR:</span>
                <span>Rp ' . $this->formatRupiah($header['amount_paid']) . '</span>
            </div>
            <div class="summary-row total-row">
                <span>KEMBALI:</span>
                <span>Rp ' . $this->formatRupiah($header['change_amount']) . '</span>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin: 8px 0; font-weight: bold; font-size: 12px;">
        TERIMA KASIH
    </div>
    
    <div class="footer">
        <div>Barang sudah dibeli</div>
        <div>tidak dapat ditukar</div>
        <div style="margin-top: 4px;">' . $date_time . '</div>
    </div>
    
    <div class="no-print" style="text-align: center; margin: 15px 0; display: flex; gap: 10px; justify-content: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">CETAK</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">TUTUP</button>
    </div>
    
    <script>
        setTimeout(function() {
            window.print();
        }, 1000);
    </script>
</body>
</html>';

        return $html;
    }

    private function generateDailyReportHTML($result, $date, $user_id)
    {
        $user_name = $_SESSION['userdata']['username'] ?? 'Kasir';
        $total_transaksi = 0;
        $total_uang_masuk = 0;
        $total_kembalian = 0;

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Kasir - ' . $date . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2, h3 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
        .text-right { text-align: right; }
        .print-btn { text-align: center; margin: 20px; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <h2>Laporan Harian Kasir</h2>
    <h3>Tanggal: ' . $date . '</h3>
    <h3>Kasir: ' . $user_name . '</h3>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Waktu</th>
                <th>Invoice</th>
                <th>Metode Bayar</th>
                <th>Jumlah Item</th>
                <th class="text-right">Total</th>
                <th class="text-right">Bayar</th>
                <th class="text-right">Kembali</th>
            </tr>
        </thead>
        <tbody>';

        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $waktu = date('H:i:s', strtotime($row['sale_date']));
            $total_transaksi += $row['sale_total'];
            $total_uang_masuk += $row['amount_paid'];
            $total_kembalian += $row['change_amount'];

            $html .= '
            <tr>
                <td>' . $no . '</td>
                <td>' . $waktu . '</td>
                <td>' . $row['sale_invoice'] . '</td>
                <td>' . $this->getPaymentMethodText($row['payment_method']) . '</td>
                <td>' . $row['item_count'] . '</td>
                <td class="text-right">Rp ' . $this->formatRupiah($row['sale_total']) . '</td>
                <td class="text-right">Rp ' . $this->formatRupiah($row['amount_paid']) . '</td>
                <td class="text-right">Rp ' . $this->formatRupiah($row['change_amount']) . '</td>
            </tr>';
            $no++;
        }

        $html .= '
            <tr class="total-row">
                <td colspan="5"><strong>TOTAL</strong></td>
                <td class="text-right"><strong>Rp ' . $this->formatRupiah($total_transaksi) . '</strong></td>
                <td class="text-right"><strong>Rp ' . $this->formatRupiah($total_uang_masuk) . '</strong></td>
                <td class="text-right"><strong>Rp ' . $this->formatRupiah($total_kembalian) . '</strong></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background-color: #f8f9fa;">
        <h4>Ringkasan</h4>
        <p>Jumlah Transaksi: <strong>' . ($no - 1) . '</strong></p>
        <p>Total Penjualan: <strong>Rp ' . $this->formatRupiah($total_transaksi) . '</strong></p>
        <p>Total Uang Masuk: <strong>Rp ' . $this->formatRupiah($total_uang_masuk) . '</strong></p>
        <p>Total Kembalian: <strong>Rp ' . $this->formatRupiah($total_kembalian) . '</strong></p>
    </div>
    
    <div class="print-btn">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Laporan</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Tutup</button>
    </div>
    
    <script>
        setTimeout(function() {
            window.print();
        }, 1000);
    </script>
</body>
</html>';

        return $html;
    }

    private function generatePrintReportHTML($result, $date, $user_id)
    {
        return $this->generateDailyReportHTML($result, $date, $user_id);
    }

    private function logActivity($aksi, $tabel, $record_id, $keterangan = "", $userIdOverride = null)
    {
        try {
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
                throw new Exception("Prepare log error: " . $this->conn->error);
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
            error_log("LOG ACTIVITY ERROR: " . $e->getMessage());
        }
    }

    public function fetchOne($sql, $params = [])
    {
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

// Router
if (is_direct_script(__FILE__)) {
try {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    // Clean output buffer to prevent any accidental output
    if (ob_get_level() > 0) {
        ob_clean();
    }

    $PenjualanKasir = new PenjualanKasir();
    $action = request_action('none');

    $json_actions = [
        'verify_minus_password',
        'get_data_penjualan_kasir',
        'save_penjualan_kasir',
        'update_penjualan_kasir',
        'search_produk_kasir',
        'get_today_summary',
        'get_pelanggan_kasir',
        'get_transaction_detail',
        'void_transaction'
    ];

    // Set JSON header for JSON responses only
    if (in_array($action, $json_actions) && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    $allowed_actions = [
        'verify_minus_password',
        'get_data_penjualan_kasir',
        'save_penjualan_kasir',
        'update_penjualan_kasir',
        'search_produk_kasir',
        'get_today_summary',
        'get_pelanggan_kasir',
        'get_transaction_detail',
        'void_transaction',
        'print_receipt',
        'export_excel_kasir',
        'today_report',
        'print_daily_report'
    ];

    if (in_array($action, $allowed_actions)) {
        $PenjualanKasir->$action();
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'failed', 'msg' => 'Action not allowed']);
    }
} catch (Exception $e) {
    error_log("PENJUALAN KASIR ROUTER ERROR: " . $e->getMessage());
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'failed',
        'msg' => $e->getMessage()
    ]);
}
}
