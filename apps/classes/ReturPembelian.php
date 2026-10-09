<?php
require_once('../config.php');
require_once('../classes/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

class ReturPembelian extends DBConnection
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
                $this->conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

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
        return NumericInput::quantity($input, 6);
    }

    private function sanitizeMoney($input)
    {
        return NumericInput::money($input);
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

    /** ======================= [ GET DATA RETUR PEMBELIAN ] ======================= **/
    function get_data_retur()
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
            $jenis_retur = $this->sanitizeInt($_POST['jenis_retur'] ?? '');
            $supplier_id = $this->sanitizeInt($_POST['supplier_id'] ?? 0);

            // Validasi tanggal
            if (!strtotime($tgl_awal) || !strtotime($tgl_akhir)) {
                throw new Exception("Format tanggal tidak valid");
            }

            // Sorting
            $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
            $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc');
            $orderDir = ($orderDir === 'desc') ? 'DESC' : 'ASC';
            $columns = [
                0 => 'pr.returid',
                1 => 'pr.retur_date',
                2 => 'pr.retur_invoice',
                3 => 'pr.bayar_id',
                4 => 's.nama',
                5 => 'pr.retur_total',
                6 => 'pr.status'
            ];
            $orderColumn = $columns[$orderColumnIndex] ?? 'pr.returid';

            /* =====================================================
               WHERE builder
               ===================================================== */
            $whereParts = ["pr.status != 'DELETED'"]; // Hanya tampilkan yang tidak dihapus
            $params = [];
            $types = "";

            // 🔍 Filter global pencarian
            if (!empty($search)) {
                $whereParts[] = "(pr.retur_invoice LIKE ? OR pr.retur_nofak LIKE ? OR s.nama LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                $types .= "sss";
            }

            // 📅 Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(pr.retur_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // 🔄 Filter jenis transaksi (bayar_id)
            if (!empty($jenis_retur)) {
                $whereParts[] = "pr.bayar_id = ?";
                $params[] = $jenis_retur;
                $types .= "i";
            }

            // 👤 Filter supplier
            if ($supplier_id > 0) {
                $whereParts[] = "pr.supplier_id = ?";
                $params[] = $supplier_id;
                $types .= "i";
            }

            // Gabungkan kondisi WHERE
            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            /* =====================================================
               Hitung total record
               ===================================================== */
            $totalQuery = $this->query("SELECT COUNT(*) AS cnt FROM purc_retur pr WHERE pr.status != 'DELETED'");
            $totalRecords = $totalQuery ? $totalQuery->fetch_assoc()['cnt'] ?? 0 : 0;

            /* =====================================================
               Hitung filtered record
               ===================================================== */
            $countSql = "SELECT COUNT(*) AS cnt 
                         FROM purc_retur pr 
                         LEFT JOIN supplier s ON s.id = pr.supplier_id
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
                        pr.returid,
                        pr.returid AS retur_purc_id,
                        pr.retur_date,
                        pr.retur_invoice,
                        pr.retur_invoice AS retur_no,
                        pr.retur_nofak,
                        pr.retur_datefak,
                        pr.bayar_id,
                        pr.bayar_id AS jenis_retur,
                        pr.retur_total,
                        pr.retur_total AS total_retur,
                        pr.retur_disc,
                        pr.ppn,
                        pr.status,
                        pr.catatan,
                        pr.supplier_id,
                        p.purc_invoice,
                        s.nama AS supplier_name,
                        s.alamat AS supplier_alamat
                    FROM purc_retur pr
                    LEFT JOIN purc p ON p.purcid = pr.purc_id
                    LEFT JOIN supplier s ON s.id = pr.supplier_id
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
                    $r['retur_date'] = date("d/m/Y", strtotime($r['retur_date']));
                    if ($r['retur_datefak']) {
                        $r['retur_datefak'] = date("d/m/Y", strtotime($r['retur_datefak']));
                    }
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
            error_log("[GET_DATA_RETUR ERROR] " . $e->getMessage());
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

    /** ======================= [ PILIH PEMBELIAN ASAL ] ======================= **/
    function search_pembelian()
    {
        try {
            $term = trim($_GET['term'] ?? '');
            $supplier_id = $this->sanitizeInt($_GET['supplier_id'] ?? 0);

            if ($supplier_id <= 0) {
                echo json_encode([]);
                exit;
            }

            $like = "%{$term}%";
            $sql = "SELECT p.purcid, p.purc_invoice, p.purc_nofak, p.purc_date,
                           p.supplier_id, s.nama AS supplier_nama
                    FROM purc p
                    INNER JOIN supplier s ON s.id = p.supplier_id
                    WHERE p.purc_status <> 0
                      AND p.supplier_id = ?
                      AND (p.purc_invoice LIKE ? OR p.purc_nofak LIKE ?)
                      AND EXISTS (
                          SELECT 1
                          FROM purc_det pd
                          WHERE pd.purc_id = p.purcid
                            AND pd.purc_qty > COALESCE((
                                SELECT SUM(prd.retur_qty)
                                FROM purc_retur_det prd
                                INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                                WHERE prd.purc_detail_id = pd.noid
                                  AND pr.status = 'ACTIVE'
                            ), 0)
                      )
                    ORDER BY p.purc_date DESC, p.purcid DESC
                    LIMIT 30";
            $stmt = $this->prepare($sql);
            $stmt->bind_param('iss', $supplier_id, $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();

            echo json_encode($data);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    function get_pembelian_details()
    {
        try {
            $purc_id = $this->sanitizeInt($_GET['purc_id'] ?? 0);
            $retur_id = $this->sanitizeInt($_GET['retur_id'] ?? 0);
            if ($purc_id <= 0) {
                throw new Exception('Pembelian asal tidak valid');
            }

            $purchase = $this->fetchOne(
                "SELECT p.purcid, p.purc_invoice, p.purc_nofak, p.purc_date,
                        p.supplier_id, s.nama AS supplier_nama
                 FROM purc p
                 INNER JOIN supplier s ON s.id = p.supplier_id
                 WHERE p.purcid = ? AND p.purc_status <> 0",
                [$purc_id]
            );
            if (!$purchase) {
                throw new Exception('Pembelian asal tidak ditemukan atau sudah dibatalkan');
            }

            $details = $this->getPurchaseReturnableDetails($purc_id, $retur_id);
            echo json_encode([
                'status' => 'success',
                'purchase' => $purchase,
                'details' => $details
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    function get_next_retur_no()
    {
        try {
            echo json_encode(['status' => 'success', 'retur_no' => $this->generate_retur_invoice()]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    /** ======================= [ SAVE RETUR PEMBELIAN ] ======================= **/
    function save_retur()
    {
        $invoice_lock = false;
        try {
            $this->ensureConnection();
            $user_id = $this->sanitizeInt($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
            if ($user_id <= 0) {
                throw new Exception('User tidak terautentikasi');
            }

            $lock_result = $this->query("SELECT GET_LOCK('sarinah_purc_return_invoice', 10) AS acquired");
            $invoice_lock = (int) $lock_result->fetch_assoc()['acquired'] === 1;
            if (!$invoice_lock) {
                throw new Exception('Nomor retur sedang diproses. Silakan coba kembali.');
            }

            $this->conn->begin_transaction();
            $request = $this->readReturnRequest();
            $prepared = $this->prepareReturnData($request, 0);
            $retur_invoice = $this->generate_retur_invoice();
            $retur_paid = $request['jenis_retur'] === 2 ? 1 : 0;

            $sql = "INSERT INTO purc_retur (
                        purc_id, retur_date, retur_invoice, supplier_id, retur_datefak,
                        retur_nofak, retur_total, retur_disc, bayar_id, retur_paid,
                        user_id, ppn, tax_perc, catatan, status, date_created
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Gagal menyiapkan header retur: ' . $this->conn->error);
            }
            $stmt->bind_param(
                'ississddiiidds',
                $request['purc_id'],
                $request['retur_date'],
                $retur_invoice,
                $prepared['purchase']['supplier_id'],
                $prepared['purchase']['purc_date'],
                $prepared['purchase']['purc_invoice'],
                $prepared['total'],
                $request['discount'],
                $request['jenis_retur'],
                $retur_paid,
                $user_id,
                $prepared['ppn'],
                $prepared['tax_percent'],
                $request['reason']
            );
            if (!$stmt->execute()) {
                throw new Exception('Gagal menyimpan header retur: ' . $stmt->error);
            }
            $returid = (int) $this->conn->insert_id;
            $stmt->close();

            $this->replaceReturnDetails($returid, $prepared['items']);
            if ($request['jenis_retur'] === 2) {
                $this->adjustSupplierDebt($prepared['purchase']['purc_invoice'], $prepared['total'], 'apply');
            }

            $this->logActivity('Tambah Retur Pembelian', 'purc_retur', $returid, "Retur {$retur_invoice} dari {$prepared['purchase']['purc_invoice']}");
            $this->conn->commit();

            echo json_encode([
                'status' => 'success',
                'msg' => "Retur pembelian {$retur_invoice} berhasil disimpan dan stok telah dikurangi",
                'returid' => $returid,
                'retur_invoice' => $retur_invoice
            ]);
        } catch (Exception $e) {
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }
            error_log('[SAVE_RETUR ERROR] ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        } finally {
            if ($invoice_lock && $this->conn) {
                $this->conn->query("SELECT RELEASE_LOCK('sarinah_purc_return_invoice')");
            }
        }
        exit;
    }

    /** Kode lama disimpan sementara sebagai referensi dan tidak dipanggil router. */
    private function save_retur_legacy()
    {
        try {
            $this->ensureConnection();

            // Validasi user session
            $user_id = 0;
            if (isset($_SESSION['userdata']['id'])) {
                $user_id = $this->sanitizeInt($_SESSION['userdata']['id']);
            } elseif (isset($_SESSION['user_id'])) {
                $user_id = $this->sanitizeInt($_SESSION['user_id']);
            }

            if ($user_id <= 0) {
                throw new Exception("User tidak terautentikasi");
            }

            // Sanitize input
            $retur_date     = $this->sanitizeDate($_POST['retur_date'] ?? date("Y-m-d"));
            $retur_invoice  = $this->sanitizeString($_POST['retur_invoice'] ?? '');
            $retur_nofak    = $this->sanitizeString($_POST['retur_nofak'] ?? '');
            $retur_datefak  = !empty($_POST['retur_datefak']) ? $this->sanitizeDate($_POST['retur_datefak']) : null;
            $supplier_id    = $this->sanitizeInt($_POST['supplier_id'] ?? 0);
            $bayar_id       = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $retur_disc     = $this->sanitizeFloat($_POST['retur_disc'] ?? 0);
            $retur_ppn      = $this->sanitizeFloat($_POST['retur_ppn'] ?? 0);
            $retur_total    = $this->sanitizeFloat($_POST['retur_total'] ?? 0);
            $catatan        = $this->sanitizeString($_POST['catatan'] ?? '');

            // Debug log untuk input
            error_log("Save Retur - Input: supplier_id=$supplier_id, user_id=$user_id, total=$retur_total");

            // Validasi input required
            if (empty($retur_date)) {
                throw new Exception("Tanggal retur harus diisi");
            }

            if ($supplier_id <= 0) {
                throw new Exception("Pilih supplier terlebih dahulu");
            }

            // Validasi total
            if ($retur_total <= 0) {
                throw new Exception("Total retur tidak valid");
            }

            // Generate invoice retur otomatis kalau kosong
            if (empty($retur_invoice)) {
                $retur_invoice = $this->generate_retur_invoice();
            }

            // Mulai transaksi dengan pengecekan koneksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            /** ======================= INSERT HEADER RETUR PEMBELIAN ======================= **/
            $sql = "INSERT INTO purc_retur (
                        retur_date, retur_invoice, retur_nofak, retur_datefak, supplier_id, bayar_id, 
                        retur_disc, ppn, retur_total, catatan, 
                        user_id, status, date_created
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement gagal: " . $this->conn->error);
            }

            // Bind parameters dengan type yang benar
            $stmt->bind_param(
                "ssssiidddsi",
                $retur_date,
                $retur_invoice,
                $retur_nofak,
                $retur_datefak,
                $supplier_id,
                $bayar_id,
                $retur_disc,
                $retur_ppn,
                $retur_total,
                $catatan,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute statement gagal: " . $stmt->error);
            }

            $returid = $this->conn->insert_id;
            $stmt->close();

            /** ======================= INSERT DETAIL RETUR ======================= **/
            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("Tidak ada produk dalam retur");
            }

            $detail_sql = "INSERT INTO purc_retur_det 
                    (retur_id, produk_id, retur_qty, retur_harga, retur_kondisi, ppn, isi)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $detail = $this->conn->prepare($detail_sql);
            if (!$detail) {
                throw new Exception("Prepare detail gagal: " . $this->conn->error);
            }

            $success_count = 0;
            $produk_ids = $_POST['produk_id'];

            for ($k = 0; $k < count($produk_ids); $k++) {
                // Sanitize input detail
                $produk_id     = $this->sanitizeInt($produk_ids[$k]);
                $retur_qty     = $this->sanitizeFloat($_POST['retur_qty'][$k] ?? 0);
                $retur_harga   = $this->sanitizeFloat($_POST['retur_harga'][$k] ?? 0);
                $retur_kondisi = $this->sanitizeString($_POST['retur_kondisi'][$k] ?? 'Baik');
                $ppn_item      = $this->sanitizeFloat($_POST['ppn_item'][$k] ?? 0);
                $isi           = $this->sanitizeInt($_POST['isi'][$k] ?? 1);

                // Validasi data detail
                if ($produk_id <= 0) {
                    error_log("Produk ID tidak valid pada index $k: $produk_id");
                    continue; // Skip invalid product
                }

                if ($retur_qty <= 0) {
                    throw new Exception("Quantity retur tidak valid untuk produk index $k");
                }

                if ($retur_harga < 0) {
                    throw new Exception("Harga retur tidak valid untuk produk index $k");
                }

                $detail->bind_param(
                    "iiddsdi",
                    $returid,
                    $produk_id,
                    $retur_qty,
                    $retur_harga,
                    $retur_kondisi,
                    $ppn_item,
                    $isi
                );

                if (!$detail->execute()) {
                    throw new Exception("Execute detail gagal untuk produk index $k: " . $detail->error);
                }

                // Update stok produk - BERKURANG pada retur (barang dikembalikan ke supplier)
                $this->updateStokProduk($produk_id, $retur_qty * $isi, 'out');

                $success_count++;
            }

            $detail->close();

            // Validasi apakah ada produk yang berhasil disimpan
            if ($success_count === 0) {
                throw new Exception("Tidak ada produk yang valid untuk disimpan");
            }

            /** ======================= LOG AKTIVITAS ======================= **/
            $this->logActivity("Tambah Retur Pembelian", "purc_retur", $returid, "Retur Pembelian: {$retur_invoice}");

            // Commit transaksi
            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("Save Retur SUCCESS - ReturID: $returid, Invoice: $retur_invoice");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Retur pembelian berhasil disimpan",
                "returid" => $returid,
                "retur_invoice" => $retur_invoice
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            $error_msg = $e->getMessage();
            error_log("[SAVE_RETUR ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    /** ======================= [ GET RETUR BY ID ] ======================= **/
    function get_retur_by_id()
    {
        try {
            $returid = $this->sanitizeInt($_GET['id'] ?? 0);
            if ($returid <= 0) {
                throw new Exception('ID retur tidak valid');
            }

            $sql = "SELECT pr.returid AS retur_purc_id, pr.purc_id, pr.retur_date,
                           pr.retur_invoice AS retur_no, pr.retur_invoice,
                           pr.bayar_id AS jenis_retur, pr.retur_disc,
                           pr.ppn AS retur_ppn, pr.retur_total,
                           pr.catatan AS alasan_retur, pr.status,
                           p.purc_invoice, p.purc_date, p.supplier_id,
                           s.nama AS supplier_nama
                    FROM purc_retur pr
                    INNER JOIN purc p ON p.purcid = pr.purc_id
                    INNER JOIN supplier s ON s.id = p.supplier_id
                    WHERE pr.returid = ? AND pr.status <> 'DELETED'";
            $stmt = $this->prepare($sql);
            $stmt->bind_param('i', $returid);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$header) {
                throw new Exception('Data retur tidak ditemukan');
            }

            $returnable = $this->getPurchaseReturnableDetails((int) $header['purc_id'], $returid);
            $returnable_by_detail = [];
            foreach ($returnable as $item) {
                $returnable_by_detail[(int) $item['purc_detail_id']] = $item;
            }

            $detail_sql = "SELECT prd.noid AS retur_detail_id, prd.purc_detail_id,
                                  prd.produk_id, prd.retur_qty AS qty_retur,
                                  prd.retur_harga AS harga, prd.ppn,
                                  prd.isi, p.nama_produk, p.barcode, p.is_ppn
                           FROM purc_retur_det prd
                           INNER JOIN produk p ON p.id = prd.produk_id
                           WHERE prd.retur_id = ?
                           ORDER BY prd.noid";
            $detail_stmt = $this->prepare($detail_sql);
            $detail_stmt->bind_param('i', $returid);
            $detail_stmt->execute();
            $result = $detail_stmt->get_result();
            $details = [];
            while ($row = $result->fetch_assoc()) {
                $origin = $returnable_by_detail[(int) $row['purc_detail_id']] ?? [];
                $row['qty_beli'] = $origin['qty_beli'] ?? $row['qty_retur'];
                $row['qty_sisa'] = $origin['qty_sisa'] ?? $row['qty_retur'];
                $row['ppn_beli'] = $origin['ppn_beli'] ?? 0;
                $row['qty_sudah_retur'] = $origin['qty_sudah_retur'] ?? 0;
                $row['ppn_sudah_retur'] = $origin['ppn_sudah_retur'] ?? 0;
                $row['ppn'] = $origin['ppn'] ?? round((float) $row['ppn']);
                $row['subtotal_retur'] = (float) $row['qty_retur'] * (float) $row['harga'];
                $row['status_retur'] = 1;
                $details[] = $row;
            }
            $detail_stmt->close();

            echo json_encode(['status' => 'success', 'header' => $header, 'details' => $details]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    private function get_retur_by_id_legacy()
    {
        try {
            $returid = $this->sanitizeInt($_GET['id'] ?? 0);

            if ($returid <= 0) {
                throw new Exception("❌ ID Retur tidak valid!");
            }

            // Get header
            $sql = "SELECT pr.*, s.nama as supplier_nama, s.alamat as supplier_alamat
                    FROM purc_retur pr 
                    LEFT JOIN supplier s ON s.id = pr.supplier_id
                    WHERE pr.returid = ? AND pr.status != 'DELETED'";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $result = $stmt->get_result();
            $header = $result->fetch_assoc();

            if (!$header) {
                throw new Exception("❌ Data retur tidak ditemukan!");
            }

            // Get details
            $sql_detail = "SELECT prd.*, p.nama_produk, p.barcode, p.is_ppn, p.stok,
                                  s.nama_satuan, s.id as satuan_id
                          FROM purc_retur_det prd
                          LEFT JOIN produk p ON p.id = prd.produk_id
                          LEFT JOIN satuan s ON s.id = p.id_satuan
                          WHERE prd.retur_id = ?";

            $stmt_detail = $this->prepare($sql_detail);
            $stmt_detail->bind_param("i", $returid);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            $details = [];

            while ($row = $result_detail->fetch_assoc()) {
                $details[] = $row;
            }

            echo json_encode([
                "status" => "success",
                "header" => $header,
                "details" => $details
            ]);

            $stmt->close();
            $stmt_detail->close();
        } catch (Exception $e) {
            error_log("[GET_RETUR_BY_ID ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ UPDATE RETUR PEMBELIAN ] ======================= **/
    function update_retur()
    {
        try {
            $this->ensureConnection();
            $returid = $this->sanitizeInt($_POST['retur_purc_id'] ?? $_POST['returid'] ?? 0);
            $user_id = $this->sanitizeInt($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
            if ($returid <= 0) {
                throw new Exception('ID retur tidak valid');
            }
            if ($user_id <= 0) {
                throw new Exception('User tidak terautentikasi');
            }

            $this->conn->begin_transaction();
            $old = $this->fetchOne(
                "SELECT pr.*, p.purc_invoice AS purchase_invoice
                 FROM purc_retur pr
                 LEFT JOIN purc p ON p.purcid = pr.purc_id
                 WHERE pr.returid = ? FOR UPDATE",
                [$returid]
            );
            if (!$old || $old['status'] !== 'ACTIVE') {
                throw new Exception('Retur tidak ditemukan atau sudah tidak aktif');
            }

            if ((int) $old['bayar_id'] === 2 && (int) $old['retur_paid'] === 1 && !empty($old['purchase_invoice'])) {
                $this->adjustSupplierDebt($old['purchase_invoice'], (float) $old['retur_total'], 'reverse');
            }
            $this->revertStokRetur($returid);

            $request = $this->readReturnRequest();
            $prepared = $this->prepareReturnData($request, $returid);
            $retur_paid = $request['jenis_retur'] === 2 ? 1 : 0;

            $sql = "UPDATE purc_retur SET
                        purc_id = ?, retur_date = ?, supplier_id = ?, retur_datefak = ?,
                        retur_nofak = ?, retur_total = ?, retur_disc = ?, bayar_id = ?,
                        retur_paid = ?, user_id = ?, ppn = ?, tax_perc = ?, catatan = ?,
                        updated_at = NOW()
                    WHERE returid = ? AND status = 'ACTIVE'";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Gagal menyiapkan perubahan retur: ' . $this->conn->error);
            }
            $stmt->bind_param(
                'isissddiiiddsi',
                $request['purc_id'],
                $request['retur_date'],
                $prepared['purchase']['supplier_id'],
                $prepared['purchase']['purc_date'],
                $prepared['purchase']['purc_invoice'],
                $prepared['total'],
                $request['discount'],
                $request['jenis_retur'],
                $retur_paid,
                $user_id,
                $prepared['ppn'],
                $prepared['tax_percent'],
                $request['reason'],
                $returid
            );
            if (!$stmt->execute()) {
                throw new Exception('Gagal memperbarui retur: ' . $stmt->error);
            }
            $stmt->close();

            $this->replaceReturnDetails($returid, $prepared['items']);
            if ($request['jenis_retur'] === 2) {
                $this->adjustSupplierDebt($prepared['purchase']['purc_invoice'], $prepared['total'], 'apply');
            }

            $this->logActivity('Update Retur Pembelian', 'purc_retur', $returid, "Retur dari {$prepared['purchase']['purc_invoice']} diperbarui");
            $this->conn->commit();
            echo json_encode([
                'status' => 'success',
                'msg' => 'Retur pembelian berhasil diperbarui dan stok telah disesuaikan',
                'returid' => $returid
            ]);
        } catch (Exception $e) {
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }
            error_log('[UPDATE_RETUR ERROR] ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    private function update_retur_legacy()
    {
        try {
            $this->ensureConnection();

            // ======================= VALIDASI INPUT =======================
            $returid        = $this->sanitizeInt($_POST['returid'] ?? 0);
            $retur_date     = $this->sanitizeDate($_POST['retur_date'] ?? date("Y-m-d"));
            $retur_invoice  = $this->sanitizeString($_POST['retur_invoice'] ?? '');
            $retur_nofak    = $this->sanitizeString($_POST['retur_nofak'] ?? '');
            $retur_datefak  = !empty($_POST['retur_datefak']) ? $this->sanitizeDate($_POST['retur_datefak']) : null;
            $supplier_id    = $this->sanitizeInt($_POST['supplier_id'] ?? 0);
            $bayar_id       = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $retur_disc     = $this->sanitizeFloat($_POST['retur_disc'] ?? 0);
            $retur_ppn      = $this->sanitizeFloat($_POST['retur_ppn'] ?? 0);
            $retur_total    = $this->sanitizeFloat($_POST['retur_total'] ?? 0);
            $catatan        = $this->sanitizeString($_POST['catatan'] ?? '');
            $user_id        = $this->sanitizeInt($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);

            if ($returid <= 0) {
                throw new Exception("❌ ID Retur tidak valid!");
            }

            // ======================= VALIDASI DATA =======================
            if (empty($retur_date)) {
                throw new Exception("❌ Tanggal retur harus diisi!");
            }

            if ($supplier_id <= 0) {
                throw new Exception("❌ Pilih supplier terlebih dahulu!");
            }

            if ($retur_total <= 0) {
                throw new Exception("❌ Total retur tidak valid!");
            }

            // ======================= VALIDASI PRODUK =======================
            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("❌ Tidak ada produk dalam retur!");
            }

            // ======================= MULAI TRANSAKSI =======================
            if (!$this->conn->begin_transaction()) {
                throw new Exception("❌ Gagal memulai transaksi database: " . $this->conn->error);
            }

            // ======================= CEK STATUS TRANSAKSI =======================
            $check_sql = "SELECT status, retur_invoice FROM purc_retur WHERE returid = ?";
            $check_stmt = $this->conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("❌ Prepare check failed: " . $this->conn->error);
            }

            $check_stmt->bind_param("i", $returid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();

            if (!$check_data) {
                throw new Exception("❌ Data retur tidak ditemukan!");
            }

            if ($check_data['status'] !== 'ACTIVE') {
                throw new Exception("❌ Retur tidak aktif, tidak dapat diedit!");
            }

            error_log("🔄 UPDATE RETUR - ReturID: $returid, Invoice: " . $check_data['retur_invoice']);

            // ======================= KEMBALIKAN STOK LAMA =======================
            $this->revertStokRetur($returid);

            // ======================= UPDATE HEADER RETUR =======================
            $update_sql = "UPDATE purc_retur SET 
                        retur_date = ?, 
                        retur_invoice = ?, 
                        retur_nofak = ?, 
                        retur_datefak = ?,
                        supplier_id = ?, 
                        bayar_id = ?, 
                        retur_disc = ?, 
                        ppn = ?, 
                        retur_total = ?, 
                        catatan = ?, 
                        user_id = ?, 
                        updated_at = NOW()
                    WHERE returid = ? AND status = 'ACTIVE'";

            $update_stmt = $this->conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("❌ Prepare update header failed: " . $this->conn->error);
            }

            $update_stmt->bind_param(
                "ssssiidddsii",
                $retur_date,     // s - string
                $retur_invoice,  // s - string  
                $retur_nofak,    // s - string
                $retur_datefak,  // s - string
                $supplier_id,    // i - integer
                $bayar_id,       // i - integer
                $retur_disc,     // d - double
                $retur_ppn,      // d - double
                $retur_total,    // d - double
                $catatan,        // s - string
                $user_id,        // i - integer
                $returid         // i - integer
            );

            if (!$update_stmt->execute()) {
                throw new Exception("❌ Execute update header failed: " . $update_stmt->error);
            }

            $affected_rows = $update_stmt->affected_rows;
            $update_stmt->close();

            if ($affected_rows === 0) {
                throw new Exception("❌ Tidak ada data yang diupdate. Mungkin data tidak ditemukan atau status bukan ACTIVE.");
            }

            error_log("✅ Header updated - Affected rows: $affected_rows");

            // ======================= HAPUS DETAIL LAMA =======================
            $delete_sql = "DELETE FROM purc_retur_det WHERE retur_id = ?";
            $delete_stmt = $this->conn->prepare($delete_sql);
            if (!$delete_stmt) {
                throw new Exception("❌ Prepare delete detail failed: " . $this->conn->error);
            }

            $delete_stmt->bind_param("i", $returid);
            if (!$delete_stmt->execute()) {
                throw new Exception("❌ Execute delete detail failed: " . $delete_stmt->error);
            }

            $deleted_rows = $delete_stmt->affected_rows;
            $delete_stmt->close();

            error_log("🗑️ Detail deleted - Affected rows: $deleted_rows");

            // ======================= INSERT DETAIL BARU =======================
            $detail_sql = "INSERT INTO purc_retur_det 
                        (retur_id, produk_id, retur_qty, retur_harga, retur_kondisi, ppn, isi)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

            $detail_stmt = $this->conn->prepare($detail_sql);
            if (!$detail_stmt) {
                throw new Exception("❌ Prepare insert detail failed: " . $this->conn->error);
            }

            $success_count = 0;
            $produk_ids = $_POST['produk_id'];

            foreach ($produk_ids as $k => $pid_raw) {
                // Sanitize input detail
                $produk_id     = $this->sanitizeInt($pid_raw);
                $retur_qty     = $this->sanitizeFloat($_POST['retur_qty'][$k] ?? 0);
                $retur_harga   = $this->sanitizeFloat($_POST['retur_harga'][$k] ?? 0);
                $retur_kondisi = $this->sanitizeString($_POST['retur_kondisi'][$k] ?? 'Baik');
                $ppn_item      = $this->sanitizeFloat($_POST['ppn_item'][$k] ?? 0);
                $isi           = $this->sanitizeInt($_POST['isi'][$k] ?? 1);

                // Validasi data detail
                if ($produk_id <= 0) {
                    error_log("⚠️ Produk ID tidak valid pada index $k: $produk_id");
                    continue;
                }

                if ($retur_qty <= 0) {
                    throw new Exception("❌ Quantity retur tidak valid untuk produk index " . ($k + 1));
                }

                if ($retur_harga < 0) {
                    throw new Exception("❌ Harga retur tidak valid untuk produk index " . ($k + 1));
                }

                $detail_stmt->bind_param(
                    "iiddsdi",
                    $returid,       // i - integer
                    $produk_id,     // i - integer
                    $retur_qty,     // d - double
                    $retur_harga,   // d - double  
                    $retur_kondisi, // s - string
                    $ppn_item,      // d - double
                    $isi            // i - integer
                );

                if (!$detail_stmt->execute()) {
                    throw new Exception("❌ Execute insert detail failed untuk produk index " . ($k + 1) . ": " . $detail_stmt->error);
                }

                // Update stok produk - BERKURANG pada retur
                $this->updateStokProduk($produk_id, $retur_qty * $isi, 'out');

                $success_count++;
            }

            $detail_stmt->close();

            if ($success_count === 0) {
                throw new Exception("❌ Tidak ada produk yang valid untuk disimpan!");
            }

            error_log("✅ Detail inserted - Success count: $success_count");

            // ======================= LOG AKTIVITAS =======================
            $this->logActivity(
                "Update Retur Pembelian",
                "purc_retur",
                $returid,
                "Edit retur pembelian: {$retur_invoice}"
            );

            // ======================= COMMIT TRANSAKSI =======================
            if (!$this->conn->commit()) {
                throw new Exception("❌ Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("🎉 UPDATE RETUR SUCCESS - ReturID: $returid");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Retur pembelian berhasil diperbarui",
                "returid" => $returid,
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
            error_log("[UPDATE_RETUR ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    /** ======================= [ DELETE RETUR ] ======================= **/
    function delete_retur()
    {
        try {
            $this->ensureConnection();

            $returid = $this->sanitizeInt($_POST['retur_id'] ?? 0);

            if ($returid <= 0) {
                throw new Exception("❌ ID Retur tidak valid!");
            }

            // Mulai transaksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            // Kunci retur dan baca dampak finansial sebelum dibatalkan.
            $cek = $this->fetchOne(
                "SELECT pr.retur_invoice, pr.retur_total, pr.bayar_id, pr.retur_paid,
                        p.purc_invoice AS purchase_invoice
                 FROM purc_retur pr
                 LEFT JOIN purc p ON p.purcid = pr.purc_id
                 WHERE pr.returid = ? AND pr.status <> 'DELETED' FOR UPDATE",
                [$returid]
            );
            if (!$cek) {
                throw new Exception("❌ Data retur tidak ditemukan atau sudah dihapus!");
            }

            if ((int) $cek['bayar_id'] === 2 && (int) $cek['retur_paid'] === 1 && !empty($cek['purchase_invoice'])) {
                $this->adjustSupplierDebt($cek['purchase_invoice'], (float) $cek['retur_total'], 'reverse');
            }

            // Kembalikan stok karena retur dibatalkan.
            $this->revertStokRetur($returid);

            // Soft delete - update status menjadi DELETED
            $sql = "UPDATE purc_retur 
                    SET status = 'DELETED', updated_at = NOW() 
                    WHERE returid = ? AND status != 'DELETED'";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("❌ Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $returid);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("❌ Gagal menghapus retur!");
            }
            $stmt->close();

            // Commit transaksi
            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            // Log aktivitas
            $this->logActivity(
                "Hapus Retur Pembelian",
                "purc_retur",
                $returid,
                "Penghapusan retur pembelian No: {$cek['retur_invoice']}"
            );

            echo json_encode([
                "status" => "success",
                "msg" => "🗑️ Retur berhasil dihapus",
                "retur_id" => $returid
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            error_log("[DELETE_RETUR ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    /** ======================= [ EXPORT EXCEL RETUR ] ======================= **/
    function export_excel()
    {
        try {
            // Filter
            $tgl_awal = $this->sanitizeDate($_GET['tgl_awal'] ?? date('Y-m-01'));
            $tgl_akhir = $this->sanitizeDate($_GET['tgl_akhir'] ?? date('Y-m-d'));
            $jenis_retur = $this->sanitizeInt($_GET['jenis_retur'] ?? '');
            $supplier_id = $this->sanitizeInt($_GET['supplier_id'] ?? 0);

            $whereParts = ["pr.status != 'DELETED'"];
            $params = [];
            $types = "";

            // Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(pr.retur_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // Filter jenis transaksi (bayar_id)
            if (!empty($jenis_retur)) {
                $whereParts[] = "pr.bayar_id = ?";
                $params[] = $jenis_retur;
                $types .= "i";
            }

            // Filter supplier
            if ($supplier_id > 0) {
                $whereParts[] = "pr.supplier_id = ?";
                $params[] = $supplier_id;
                $types .= "i";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            $sql = "SELECT 
                        pr.retur_date,
                        pr.retur_invoice,
                        pr.retur_nofak,
                        CASE pr.bayar_id 
                            WHEN 1 THEN 'Pengembalian Produk / Dana'
                            WHEN 2 THEN 'Potong Hutang Supplier'
                            WHEN 3 THEN 'Tukar / Ganti Produk'
                            ELSE 'Lainnya'
                        END as jenis_transaksi,
                        s.nama as supplier_name,
                        pr.retur_total,
                        pr.retur_disc,
                        pr.ppn,
                        pr.status
                    FROM purc_retur pr
                    LEFT JOIN supplier s ON s.id = pr.supplier_id
                    $where
                    ORDER BY pr.retur_date DESC, pr.returid DESC";

            $stmt = $this->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            // Set header untuk download Excel
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="laporan_retur_pembelian_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo "LAPORAN RETUR PEMBELIAN\n";
            echo "Periode: " . date('d/m/Y', strtotime($tgl_awal)) . " - " . date('d/m/Y', strtotime($tgl_akhir)) . "\n\n";

            echo "Tanggal\tNo Retur\tNo Faktur\tJenis Transaksi\tSupplier\tDiskon\tPPN\tTotal Retur\tStatus\n";

            $total_all = 0;
            while ($row = $res->fetch_assoc()) {
                echo date('d/m/Y', strtotime($row['retur_date'])) . "\t";
                echo $row['retur_invoice'] . "\t";
                echo $row['retur_nofak'] . "\t";
                echo $row['jenis_transaksi'] . "\t";
                echo $row['supplier_name'] . "\t";
                echo number_format($row['retur_disc'], 0, ',', '.') . "\t";
                echo number_format($row['ppn'], 0, ',', '.') . "\t";
                echo number_format($row['retur_total'], 0, ',', '.') . "\t";
                echo $row['status'] . "\n";

                $total_all += $row['retur_total'];
            }

            echo "\n\nTOTAL RETUR: " . number_format($total_all, 0, ',', '.');

            $stmt->close();
            exit;
        } catch (Exception $e) {
            error_log("[EXPORT_EXCEL_RETUR ERROR] " . $e->getMessage());
            echo "Error: " . $e->getMessage();
        }
        exit;
    }

    /** ======================= [ SEARCH SUPPLIER ] ======================= **/
    function search_supplier()
    {
        try {
            $term = $this->sanitizeString($_GET['term'] ?? '');
            $sql = "SELECT id, nama, alamat, kota, kode 
                    FROM supplier 
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
            error_log("[SEARCH_SUPPLIER ERROR] " . $e->getMessage());
            echo json_encode([]);
        }
        exit;
    }

    /** ======================= [ SEARCH PRODUK ] ======================= **/
    function search_produk()
    {
        try {
            $term = trim($_GET['term'] ?? '');

            if ($term === '') {
                echo json_encode([]);
                exit;
            }

            // Pisahkan kata
            $keywords = preg_split('/\s+/', $term);

            // Base SQL
            $sql = "SELECT 
                        p.id, 
                        p.nama_produk, 
                        p.barcode, 
                        p.stok, 
                        p.harga_beli, 
                        p.harga_newmodal,
                        p.is_ppn,
                        s.nama_satuan 
                    FROM produk p
                    LEFT JOIN satuan s ON s.id = p.id_satuan
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

            $sql .= " ORDER BY p.nama_produk ASC LIMIT 50";

            $stmt = $this->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $text = "{$row['nama_produk']}" . (!empty($row['nama_satuan']) ? " ({$row['nama_satuan']})" : '');

                $data[] = [
                    "id"           => $row['id'],
                    "nama_produk"  => $row['nama_produk'],
                    "barcode"      => $row['barcode'],
                    "satuan"       => $row['nama_satuan'],
                    "stok"         => $row['stok'],
                    "harga_beli"   => $row['harga_beli'],
                    "harga_newmodal"  => $row['harga_newmodal'],
                    "is_ppn"       => (int)$row['is_ppn'],
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

    private function readReturnRequest()
    {
        $detail_ids = $_POST['purc_detail_id'] ?? [];
        $quantities = $_POST['qty_retur'] ?? [];

        return [
            'purc_id' => $this->sanitizeInt($_POST['purc_id'] ?? 0),
            'supplier_id' => $this->sanitizeInt($_POST['supplier_id'] ?? 0),
            'retur_date' => $this->sanitizeDate($_POST['retur_date'] ?? ''),
            'jenis_retur' => $this->sanitizeInt($_POST['jenis_retur'] ?? 1),
            'discount' => $this->sanitizeMoney($_POST['retur_disc'] ?? 0),
            'reason' => $this->sanitizeString($_POST['alasan_retur'] ?? ''),
            'detail_ids' => is_array($detail_ids) ? $detail_ids : [],
            'quantities' => is_array($quantities) ? $quantities : []
        ];
    }

    private function getPurchaseReturnableDetails($purc_id, $current_retur_id = 0)
    {
        $sql = "SELECT pd.noid AS purc_detail_id, pd.produk_id,
                       pd.purc_qty AS qty_beli, pd.purc_harga AS harga,
                       pd.ppn AS ppn_beli, COALESCE(NULLIF(pd.isi, 0), 1) AS isi,
                       p.nama_produk, p.barcode, p.is_ppn, p.stok,
                       COALESCE(SUM(CASE
                           WHEN pr.status = 'ACTIVE' AND pr.returid <> ? THEN prd.retur_qty
                           ELSE 0 END), 0) AS qty_sudah_retur,
                       COALESCE(SUM(CASE
                           WHEN pr.status = 'ACTIVE' AND pr.returid = ? THEN prd.retur_qty
                           ELSE 0 END), 0) AS qty_retur_sekarang,
                       COALESCE(SUM(CASE
                           WHEN pr.status = 'ACTIVE' AND pr.returid <> ? THEN prd.ppn
                           ELSE 0 END), 0) AS ppn_sudah_retur
                FROM purc_det pd
                INNER JOIN produk p ON p.id = pd.produk_id
                LEFT JOIN purc_retur_det prd ON prd.purc_detail_id = pd.noid
                LEFT JOIN purc_retur pr ON pr.returid = prd.retur_id
                WHERE pd.purc_id = ?
                GROUP BY pd.noid, pd.produk_id, pd.purc_qty, pd.purc_harga,
                         pd.ppn, pd.isi, p.nama_produk, p.barcode, p.is_ppn, p.stok
                ORDER BY pd.noid";
        $stmt = $this->prepare($sql);
        $stmt->bind_param('iiii', $current_retur_id, $current_retur_id, $current_retur_id, $purc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = [];

        while ($row = $result->fetch_assoc()) {
            $qty_beli = (float) $row['qty_beli'];
            $qty_current = (float) $row['qty_retur_sekarang'];
            $isi = max(1, (int) $row['isi']);
            $remaining_purchase = max(0, $qty_beli - (float) $row['qty_sudah_retur']);
            $available_stock = max(0, ((float) $row['stok'] + ($qty_current * $isi)) / $isi);
            $qty_sisa = min($remaining_purchase, $available_stock);
            $ppn_beli = round((float) $row['ppn_beli']);
            $ppn_sudah_retur = round((float) $row['ppn_sudah_retur']);
            $ppn_retur_sekarang = $this->allocateReturnPpn(
                $ppn_beli,
                $qty_beli,
                (float) $row['qty_sudah_retur'],
                $ppn_sudah_retur,
                $qty_current
            );

            $details[] = [
                'purc_detail_id' => (int) $row['purc_detail_id'],
                'produk_id' => (int) $row['produk_id'],
                'nama_produk' => $row['nama_produk'],
                'barcode' => $row['barcode'],
                'qty_beli' => $qty_beli,
                'qty_sisa' => round($qty_sisa, 4),
                'qty_retur' => $qty_current,
                'harga' => (float) $row['harga'],
                'ppn_beli' => $ppn_beli,
                'qty_sudah_retur' => (float) $row['qty_sudah_retur'],
                'ppn_sudah_retur' => $ppn_sudah_retur,
                'ppn' => $ppn_retur_sekarang,
                'is_ppn' => (int) $row['is_ppn'],
                'isi' => $isi,
                'subtotal_retur' => round($qty_current * (float) $row['harga'], 2),
                'status_retur' => $qty_current > 0 ? 1 : 0
            ];
        }
        $stmt->close();
        return $details;
    }

    /**
     * Alokasikan PPN yang sudah dibulatkan di detail pembelian ke retur bertahap.
     * Dengan metode kumulatif ini, retur terakhir selalu menerima sisa PPN sehingga
     * akumulasi PPN retur tidak bisa lebih atau kurang dari PPN pembelian asal.
     */
    private function allocateReturnPpn($purchase_ppn, $purchase_qty, $returned_qty, $returned_ppn, $return_qty)
    {
        $purchase_ppn = max(0, round((float) $purchase_ppn));
        $purchase_qty = max(0, (float) $purchase_qty);
        $returned_qty = max(0, (float) $returned_qty);
        $returned_ppn = max(0, round((float) $returned_ppn));
        $return_qty = max(0, (float) $return_qty);

        if ($purchase_ppn <= 0 || $purchase_qty <= 0 || $return_qty <= 0) {
            return 0;
        }

        $remaining_qty = max(0, $purchase_qty - $returned_qty);
        $remaining_ppn = max(0, $purchase_ppn - $returned_ppn);
        $allocated_qty = min($return_qty, $remaining_qty);
        if ($allocated_qty <= 0 || $remaining_ppn <= 0) {
            return 0;
        }

        if ($allocated_qty >= $remaining_qty - 0.000001) {
            return $remaining_ppn;
        }

        $cumulative_ppn = round($purchase_ppn * (($returned_qty + $allocated_qty) / $purchase_qty));
        return max(0, min($remaining_ppn, $cumulative_ppn - $returned_ppn));
    }

    private function prepareReturnData(array $request, $exclude_retur_id = 0)
    {
        if ($request['purc_id'] <= 0) {
            throw new Exception('Pilih pembelian asal terlebih dahulu');
        }
        if (!$request['retur_date']) {
            throw new Exception('Tanggal retur harus diisi');
        }
        if (!in_array($request['jenis_retur'], [1, 2, 3], true)) {
            throw new Exception('Jenis retur tidak valid');
        }
        if ($request['reason'] === '') {
            throw new Exception('Alasan retur harus diisi');
        }
        if ($request['discount'] < 0) {
            throw new Exception('Diskon retur tidak boleh minus');
        }

        $purchase = $this->fetchOne(
            "SELECT p.purcid, p.purc_invoice, p.purc_date, p.supplier_id, s.nama AS supplier_nama
             FROM purc p
             INNER JOIN supplier s ON s.id = p.supplier_id
             WHERE p.purcid = ? AND p.purc_status <> 0 FOR UPDATE",
            [$request['purc_id']]
        );
        if (!$purchase) {
            throw new Exception('Pembelian asal tidak ditemukan atau sudah dibatalkan');
        }
        if ($request['supplier_id'] > 0 && $request['supplier_id'] !== (int) $purchase['supplier_id']) {
            throw new Exception('Supplier tidak sesuai dengan pembelian asal');
        }
        if ($request['retur_date'] < $purchase['purc_date']) {
            throw new Exception('Tanggal retur tidak boleh sebelum tanggal pembelian');
        }

        $requested_lines = [];
        foreach ($request['detail_ids'] as $index => $raw_detail_id) {
            $detail_id = $this->sanitizeInt($raw_detail_id);
            $qty = $this->sanitizeFloat($request['quantities'][$index] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if ($detail_id <= 0 || isset($requested_lines[$detail_id])) {
                throw new Exception('Detail produk retur tidak valid atau duplikat');
            }
            $requested_lines[$detail_id] = $qty;
        }
        if (!$requested_lines) {
            throw new Exception('Isi quantity retur minimal pada satu produk');
        }
        ksort($requested_lines);

        $items = [];
        $subtotal = 0.0;
        foreach ($requested_lines as $detail_id => $qty) {
            $detail = $this->fetchOne(
                "SELECT pd.noid, pd.produk_id, pd.purc_qty, pd.purc_harga,
                        pd.ppn, COALESCE(NULLIF(pd.isi, 0), 1) AS isi,
                        p.nama_produk, p.stok
                 FROM purc_det pd
                 INNER JOIN produk p ON p.id = pd.produk_id
                 WHERE pd.noid = ? AND pd.purc_id = ? FOR UPDATE",
                [$detail_id, $request['purc_id']]
            );
            if (!$detail) {
                throw new Exception('Produk retur tidak berasal dari pembelian yang dipilih');
            }

            $returned_stmt = $this->prepare(
                "SELECT COALESCE(SUM(prd.retur_qty), 0) AS qty_returned
                 FROM purc_retur_det prd
                 INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                 WHERE prd.purc_detail_id = ? AND pr.status = 'ACTIVE' AND pr.returid <> ?"
            );
            $returned_stmt->bind_param('ii', $detail_id, $exclude_retur_id);
            $returned_stmt->execute();
            $returned = $returned_stmt->get_result()->fetch_assoc();
            $qty_returned = (float) $returned['qty_returned'];
            $returned_stmt->close();

            $remaining = max(0, (float) $detail['purc_qty'] - $qty_returned);
            if ($qty > $remaining + 0.000001) {
                throw new Exception("Qty retur {$detail['nama_produk']} melebihi sisa pembelian ({$remaining})");
            }

            $isi = max(1, (int) $detail['isi']);
            $base_qty = $qty * $isi;
            if ($base_qty > (float) $detail['stok'] + 0.000001) {
                throw new Exception("Stok {$detail['nama_produk']} tidak cukup. Tersedia {$detail['stok']} unit dasar");
            }

            $line_subtotal = $qty * (float) $detail['purc_harga'];
            // Harga retur mengikuti harga beli sebagaimana kasir/penjualan:
            // tidak ada komponen PPN terpisah yang ditambahkan ke total retur.
            $line_ppn = 0;
            $subtotal += $line_subtotal;
            $items[] = [
                'purc_detail_id' => (int) $detail['noid'],
                'produk_id' => (int) $detail['produk_id'],
                'qty' => $qty,
                'harga' => (float) $detail['purc_harga'],
                'isi' => $isi,
                'ppn' => $line_ppn,
                'nama_produk' => $detail['nama_produk']
            ];
        }

        if ($request['discount'] > $subtotal) {
            throw new Exception('Diskon retur tidak boleh melebihi subtotal produk');
        }
        $total = round($subtotal - $request['discount'], 2);
        if ($total <= 0) {
            throw new Exception('Total retur harus lebih dari nol');
        }

        return [
            'purchase' => $purchase,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'ppn' => 0,
            'tax_percent' => 0,
            'total' => $total
        ];
    }

    private function replaceReturnDetails($retur_id, array $items)
    {
        $delete = $this->conn->prepare('DELETE FROM purc_retur_det WHERE retur_id = ?');
        $delete->bind_param('i', $retur_id);
        if (!$delete->execute()) {
            throw new Exception('Gagal mengganti detail retur: ' . $delete->error);
        }
        $delete->close();

        $stmt = $this->conn->prepare(
            "INSERT INTO purc_retur_det
                (retur_id, purc_detail_id, produk_id, retur_qty, retur_harga, retur_kondisi, ppn, isi)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)"
        );
        if (!$stmt) {
            throw new Exception('Gagal menyiapkan detail retur: ' . $this->conn->error);
        }

        foreach ($items as $item) {
            $stmt->bind_param(
                'iiidddi',
                $retur_id,
                $item['purc_detail_id'],
                $item['produk_id'],
                $item['qty'],
                $item['harga'],
                $item['ppn'],
                $item['isi']
            );
            if (!$stmt->execute()) {
                throw new Exception('Gagal menyimpan detail retur: ' . $stmt->error);
            }
            $this->updateStokProduk($item['produk_id'], $item['qty'] * $item['isi'], 'out', $item['nama_produk']);
        }
        $stmt->close();
    }

    private function adjustSupplierDebt($purchase_invoice, $amount, $direction)
    {
        $debt = $this->fetchOne(
            "SELECT id, tanggal_jatuh_tempo, total_hutang, total_dibayar, sisa_hutang
             FROM hutang_supplier WHERE ref_pembelian = ? FOR UPDATE",
            [$purchase_invoice]
        );
        if (!$debt) {
            if ($direction === 'apply') {
                throw new Exception('Pembelian ini tidak memiliki hutang supplier yang dapat dipotong');
            }
            return;
        }

        if ($direction === 'apply') {
            if ($amount > (float) $debt['sisa_hutang'] + 0.01) {
                throw new Exception('Total retur melebihi sisa hutang supplier. Pilih jenis retur produk/penggantian.');
            }
            $total_hutang = max((float) $debt['total_dibayar'], (float) $debt['total_hutang'] - $amount);
            $sisa = max(0, (float) $debt['sisa_hutang'] - $amount);
        } else {
            $total_hutang = (float) $debt['total_hutang'] + $amount;
            $sisa = (float) $debt['sisa_hutang'] + $amount;
        }

        $status = $sisa <= 0.01
            ? 'LUNAS'
            : (!empty($debt['tanggal_jatuh_tempo']) && $debt['tanggal_jatuh_tempo'] < date('Y-m-d') ? 'OVERDUE' : 'BELUM');
        $stmt = $this->conn->prepare(
            'UPDATE hutang_supplier SET total_hutang = ?, sisa_hutang = ?, status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('ddsi', $total_hutang, $sisa, $status, $debt['id']);
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyesuaikan hutang supplier: ' . $stmt->error);
        }
        $stmt->close();
    }

    /** ======================= [ HELPER METHODS - STOK MANAGEMENT ] ======================= **/

    /**
     * Update stok produk
     */
    private function updateStokProduk($produk_id, $qty, $type = 'out', $product_name = 'produk')
    {
        $produk_id = $this->sanitizeInt($produk_id);
        $qty = $this->sanitizeFloat($qty);
        if ($produk_id <= 0 || $qty <= 0) {
            throw new Exception('Data perubahan stok tidak valid');
        }

        if ($type === 'in') {
            $stmt = $this->conn->prepare('UPDATE produk SET stok = stok + ? WHERE id = ?');
            if (!$stmt) {
                throw new Exception('Gagal menyiapkan perubahan stok ' . $product_name);
            }
            $stmt->bind_param('di', $qty, $produk_id);
        } else {
            $stmt = $this->conn->prepare('UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?');
            if (!$stmt) {
                throw new Exception('Gagal menyiapkan perubahan stok ' . $product_name);
            }
            $stmt->bind_param('did', $qty, $produk_id, $qty);
        }
        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui stok ' . $product_name);
        }
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new Exception('Stok ' . $product_name . ' tidak cukup untuk diretur');
        }
        $stmt->close();
    }

    /**
     * Kembalikan stok dari retur yang akan dihapus/diupdate
     */
    private function revertStokRetur($returid)
    {
        $stmt = $this->conn->prepare(
            'SELECT prd.produk_id, prd.retur_qty, prd.isi, p.nama_produk
             FROM purc_retur_det prd
             INNER JOIN produk p ON p.id = prd.produk_id
             WHERE prd.retur_id = ?'
        );
        $stmt->bind_param('i', $returid);
        $stmt->execute();
        $qry = $stmt->get_result();

        while ($row = $qry->fetch_assoc()) {
            // Kembalikan stok (bertambah karena retur dibatalkan)
            $this->updateStokProduk($row['produk_id'], $row['retur_qty'] * $row['isi'], 'in', $row['nama_produk']);
        }
        $stmt->close();
    }

    /**
     * Catat aktivitas user ke tabel log_aktivitas
     */
    private function logActivity($aksi, $tabel, $record_id, $keterangan = "")
    {
        try {
            $user_id = intval($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
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

    /** ======================= [ GENERATE INVOICE RETUR ] ======================= **/
    private function generate_retur_invoice()
    {
        $tahun = date("Y");
        $bulan = date("m");
        $prefix = "RETUR-{$tahun}{$bulan}-";

        $stmt = $this->prepare("SELECT retur_invoice FROM purc_retur WHERE retur_invoice LIKE ? ORDER BY returid DESC LIMIT 1");
        $like = "{$prefix}%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();

        $next = 1;
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $last_invoice = $row['retur_invoice'];
            // Ambil angka terakhir setelah prefix
            $last_number = intval(substr($last_invoice, strlen($prefix)));
            $next = $last_number + 1;
        }

        $stmt->close();
        return $prefix . str_pad($next, 4, "0", STR_PAD_LEFT);
    }

    /** ======================= [ HELPER METHOD - FIXED ACCESS LEVEL ] ======================= **/
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

/** ======================= [ ROUTER - IMPROVED ERROR HANDLING ] ======================= **/
if (is_direct_script(__FILE__)) {
require_login(true);
require_csrf_for_unsafe_request(true);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $ReturPembelian = new ReturPembelian();
    $action = request_action('none');

    $allowed_actions = [
        'get_data_retur',
        'search_supplier',
        'search_pembelian',
        'get_pembelian_details',
        'get_next_retur_no',
        'search_produk',
        'save_retur',
        'update_retur',
        'get_retur_by_id',
        'delete_retur',
        'export_excel'
    ];

    if (in_array($action, $allowed_actions)) {
        $ReturPembelian->$action();
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'failed', 'msg' => 'Action not allowed']);
    }
} catch (Exception $e) {
    error_log("[RETUR PEMBELIAN ROUTER ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'failed',
        'msg' => 'Internal Server Error: ' . $e->getMessage()
    ]);
}
exit(0);
}
