<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/NumericInput.php');
require_once(__DIR__ . '/DBConnection.php');

class Pembelian extends DBConnection
{
    private $settings;

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

    private function productIsPpn($produkId)
    {
        $produkId = $this->sanitizeInt($produkId);
        if ($produkId <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT is_ppn FROM produk WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare cek PPN produk gagal: " . $this->conn->error);
        }

        $stmt->bind_param("i", $produkId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['is_ppn'] ?? 0);
    }

    private function purchaseItemPpn($produkId, $lineSubtotal)
    {
        $lineSubtotal = max((float)$lineSubtotal, 0);
        return $this->productIsPpn($produkId) === 1 ? round($lineSubtotal * $this->ppnRate()) : 0;
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

    /** ======================= [ GET DATA PEMBELIAN - FIXED ] ======================= **/
    function get_data_pembelian()
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
                0 => 'p.purcid',
                1 => 'p.purc_date',
                2 => 'p.purc_invoice',
                3 => 'p.bayar_id',
                4 => 's.nama',
                5 => 'p.purc_total',
                6 => 'p.purc_status'
            ];
            $orderColumn = $columns[$orderColumnIndex] ?? 'p.purcid';

            /* =====================================================
               WHERE builder
               ===================================================== */
            $whereParts = ["p.purc_status <> 0"]; // Hanya tampilkan yang tidak dihapus
            $params = [];
            $types = "";

            // 🔍 Filter global pencarian
            if (!empty($search)) {
                $whereParts[] = "(p.purc_invoice LIKE ? OR p.purc_nofak LIKE ? OR s.nama LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                $types .= "sss";
            }

            // 📅 Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(p.purc_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // 💰 Filter jenis transaksi
            if (!empty($jenis)) {
                $whereParts[] = "p.bayar_id = ?";
                $params[] = $jenis;
                $types .= "i";
            }

            // 👤 Filter supplier
            if ($supplier_id > 0) {
                $whereParts[] = "p.supplier_id = ?";
                $params[] = $supplier_id;
                $types .= "i";
            }

            // Gabungkan kondisi WHERE
            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            /* =====================================================
               Hitung total record
               ===================================================== */
            $totalQuery = $this->query("SELECT COUNT(*) AS cnt FROM purc p WHERE p.purc_status <> 0");
            $totalRecords = $totalQuery ? $totalQuery->fetch_assoc()['cnt'] ?? 0 : 0;

            /* =====================================================
               Hitung filtered record
               ===================================================== */
            $countSql = "SELECT COUNT(*) AS cnt 
                         FROM purc p 
                         LEFT JOIN supplier s ON s.id = p.supplier_id
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
                        p.purcid,
                        p.purc_date,
                        p.purc_invoice,
                        p.purc_nofak,
                        p.bayar_id,
                        p.purc_total AS purc_total_gross,
                        COALESCE((
                            SELECT SUM(pr.retur_total)
                            FROM purc_retur pr
                            WHERE pr.purc_id = p.purcid
                              AND pr.status = 'ACTIVE'
                        ), 0) AS retur_total_active,
                        (p.purc_total - COALESCE((
                            SELECT SUM(pr.retur_total)
                            FROM purc_retur pr
                            WHERE pr.purc_id = p.purcid
                              AND pr.status = 'ACTIVE'
                        ), 0)) AS purc_total,
                        p.purc_status,
                        s.nama AS supplier_name,
                        s.alamat AS supplier_alamat
                    FROM purc p
                    LEFT JOIN supplier s ON s.id = p.supplier_id
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
                    $r['purc_date'] = date("d/m/Y", strtotime($r['purc_date']));
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
            error_log("[GET_DATA_PEMBELIAN ERROR] " . $e->getMessage());
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

    /** ======================= [ SAVE PEMBELIAN - SECURE ] ======================= **/
    function save_pembelian()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

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
            $purc_date    = $this->sanitizeDate($_POST['purc_date'] ?? date("Y-m-d"));
            $purc_invoice = $this->sanitizeString($_POST['purc_invoice'] ?? '');
            $purc_nofak   = $this->sanitizeString($_POST['purc_nofak'] ?? '');
            $purc_datefak = !empty($_POST['purc_datefak']) ? $this->sanitizeDate($_POST['purc_datefak']) : null;
            $supplier_id  = $this->sanitizeInt($_POST['supplier_id'] ?? 0);
            $bayar_id     = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $purc_jt      = !empty($_POST['purc_jt']) ? $this->sanitizeDate($_POST['purc_jt']) : null;
            $purc_disc    = $this->sanitizeFloat($_POST['purc_disc'] ?? 0);
            $purc_ppn     = $this->sanitizeFloat($_POST['purc_ppn'] ?? 0);
            $purc_total   = $this->sanitizeFloat($_POST['purc_total'] ?? 0);
            $catatan      = $this->sanitizeString($_POST['catatan'] ?? '');

            // Debug log untuk input
            error_log("Save Pembelian - Input: supplier_id=$supplier_id, user_id=$user_id, total=$purc_total");

            // Validasi input required
            if (empty($purc_date)) {
                throw new Exception("Tanggal faktur harus diisi");
            }

            if ($supplier_id <= 0) {
                throw new Exception("Pilih supplier terlebih dahulu");
            }

            if ($purc_disc < 0) {
                throw new Exception("Diskon pembelian tidak boleh minus");
            }

            // Generate invoice otomatis kalau kosong
            if (empty($purc_invoice)) {
                $purc_invoice = $this->generate_invoice();
            }

            // Mulai transaksi dengan pengecekan koneksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            /** ======================= INSERT HEADER PEMBELIAN ======================= **/
            $sql = "INSERT INTO purc (
                        purc_date, purc_invoice, purc_nofak, purc_datefak, supplier_id, bayar_id, 
                        purc_jt, purc_disc, ppn, purc_total, catatan, 
                        user_id, purc_status, date_created
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement gagal: " . $this->conn->error);
            }

            // Bind parameters dengan type yang benar
            $stmt->bind_param(
                "ssssiisdddsi",
                $purc_date,
                $purc_invoice,
                $purc_nofak,
                $purc_datefak,
                $supplier_id,
                $bayar_id,
                $purc_jt,
                $purc_disc,
                $purc_ppn,
                $purc_total,
                $catatan,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute statement gagal: " . $stmt->error);
            }

            $purcid = $this->conn->insert_id;
            $stmt->close();

            // * ======================= INSERT DETAIL PRODUK DENGAN VALIDASI ======================= *
            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("Tidak ada produk dalam pembelian");
            }

            $detail_sql = "INSERT INTO purc_det 
                    (purc_id, produk_id, purc_qty, purc_harga, ppn, isi)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $detail = $this->conn->prepare($detail_sql);
            if (!$detail) {
                throw new Exception("Prepare detail gagal: " . $this->conn->error);
            }

            $success_count = 0;
            $computed_subtotal = 0.0;
            $computed_ppn = 0.0;
            $produk_ids = $_POST['produk_id'];

            for ($k = 0; $k < count($produk_ids); $k++) {
                // Sanitize input detail
                $produk_id = $this->sanitizeInt($produk_ids[$k]);
                $qty       = $this->sanitizeQuantity($_POST['purc_qty'][$k] ?? 0);
                $harga     = $this->sanitizeFloat($_POST['purc_harga'][$k] ?? 0);
                $isi       = $this->sanitizeInt($_POST['isi'][$k] ?? 1);

                // Validasi data detail
                if ($produk_id <= 0) {
                    error_log("Produk ID tidak valid pada index $k: $produk_id");
                    continue; // Skip invalid product
                }

                if ($qty <= 0) {
                    throw new Exception("Quantity produk tidak valid untuk produk index $k");
                }

                if ($harga < 0) {
                    throw new Exception("Harga produk tidak valid untuk produk index $k");
                }

                if ($isi <= 0) {
                    throw new Exception("Isi kemasan tidak valid untuk produk index $k");
                }

                $line_subtotal = $qty * $harga;
                $ppn_item = $this->purchaseItemPpn($produk_id, $line_subtotal);
                $computed_subtotal += $line_subtotal;
                $computed_ppn += $ppn_item;

                $detail->bind_param(
                    "iidddi",
                    $purcid,
                    $produk_id,
                    $qty,
                    $harga,
                    $ppn_item,
                    $isi
                );

                if (!$detail->execute()) {
                    throw new Exception("Execute detail gagal untuk produk index $k: " . $detail->error);
                }

                // Update stok produk - BERTAMBAH pada pembelian
                $this->updateStokProduk($produk_id, $qty * $isi, 'in');

                // Update harga modal
                $this->updateHargaModal($produk_id, $harga);

                $success_count++;
            }

            $detail->close();

            // Validasi apakah ada produk yang berhasil disimpan
            if ($success_count === 0) {
                throw new Exception("Tidak ada produk yang valid untuk disimpan");
            }

            if ($purc_disc > $computed_subtotal) {
                throw new Exception("Diskon pembelian tidak boleh melebihi subtotal produk");
            }
            $purc_ppn = round($computed_ppn);
            $purc_total = round(($computed_subtotal - $purc_disc) + $purc_ppn, 2);
            if ($purc_total <= 0) {
                throw new Exception("Total pembelian hasil perhitungan server harus lebih dari 0");
            }

            $total_update = $this->conn->prepare("UPDATE purc SET ppn = ?, purc_total = ? WHERE purcid = ?");
            if (!$total_update) {
                throw new Exception("Prepare update total pembelian gagal: " . $this->conn->error);
            }

            $total_update->bind_param("ddi", $purc_ppn, $purc_total, $purcid);
            if (!$total_update->execute()) {
                throw new Exception("Execute update total pembelian gagal: " . $total_update->error);
            }
            $total_update->close();

            $this->syncHutangPembelian(
                $purcid,
                $purc_invoice,
                null,
                $bayar_id,
                $supplier_id,
                $purc_date,
                $purc_jt,
                $purc_total,
                $catatan,
                $user_id
            );

            /** ======================= LOG AKTIVITAS ======================= **/
            $this->logActivity("Tambah Pembelian Baru", "purc", $purcid, "Transaksi Pembelian: {$purc_invoice}");

            // Commit transaksi
            if (!$this->conn->commit()) {
                throw new Exception("Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("Save Pembelian SUCCESS - PurcID: $purcid, Invoice: $purc_invoice");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Pembelian berhasil disimpan",
                "purcid" => $purcid,
                "purc_invoice" => $purc_invoice
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            $error_msg = $e->getMessage();
            error_log("[SAVE_PEMBELIAN ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

// /** ======================= [ SAVE PEMBELIAN - SUPPORT DESIMAL ] ======================= **/
// function save_pembelian() {
//     try {
//         $this->ensureConnection();
//         // $this->validateCSRF();

//         // Validasi user session
//         $user_id = 0;
//         if (isset($_SESSION['userdata']['id'])) {
//             $user_id = $this->sanitizeInt($_SESSION['userdata']['id']);
//         } elseif (isset($_SESSION['user_id'])) {
//             $user_id = $this->sanitizeInt($_SESSION['user_id']);
//         }
        
//         if ($user_id <= 0) {
//             throw new Exception("User tidak terautentikasi");
//         }

//         // Sanitize input
//         $purc_date    = $this->sanitizeDate($_POST['purc_date'] ?? date("Y-m-d"));
//         $purc_invoice = $this->sanitizeString($_POST['purc_invoice'] ?? '');
//         $purc_nofak   = $this->sanitizeString($_POST['purc_nofak'] ?? '');
//         $purc_datefak = !empty($_POST['purc_datefak']) ? $this->sanitizeDate($_POST['purc_datefak']) : null;
//         $supplier_id  = $this->sanitizeInt($_POST['supplier_id'] ?? 0);
//         $bayar_id     = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
//         $purc_jt      = !empty($_POST['purc_jt']) ? $this->sanitizeDate($_POST['purc_jt']) : null;
//         $purc_disc    = $this->sanitizeFloat($_POST['purc_disc'] ?? 0);
//         $purc_ppn     = $this->sanitizeFloat($_POST['purc_ppn'] ?? 0);
//         $purc_total   = $this->sanitizeFloat($_POST['purc_total'] ?? 0);
//         $catatan      = $this->sanitizeString($_POST['catatan'] ?? '');
        
//         // Debug log untuk input
//         error_log("Save Pembelian - Input: supplier_id=$supplier_id, user_id=$user_id, total=$purc_total");

//         // Validasi input required
//         if (empty($purc_date)) {
//             throw new Exception("Tanggal faktur harus diisi");
//         }
        
//         if ($supplier_id <= 0) {
//             throw new Exception("Pilih supplier terlebih dahulu");
//         }

//         // Validasi total
//         if ($purc_total < 0) {
//             throw new Exception("Total pembelian tidak valid");
//         }

//         // Generate invoice otomatis kalau kosong
//         if (empty($purc_invoice)) {
//             $purc_invoice = $this->generate_invoice();
//         }

//         // Mulai transaksi dengan pengecekan koneksi
//         if (!$this->conn->begin_transaction()) {
//             throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
//         }

//         /** ======================= INSERT HEADER PEMBELIAN ======================= **/
//         $sql = "INSERT INTO purc (
//                     purc_date, purc_invoice, purc_nofak, purc_datefak, supplier_id, bayar_id, 
//                     purc_jt, purc_disc, ppn, purc_total, catatan, 
//                     user_id, purc_status, date_created
//                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

//         $stmt = $this->conn->prepare($sql);
//         if (!$stmt) {
//             throw new Exception("Prepare statement gagal: " . $this->conn->error);
//         }

//         // Bind parameters dengan type yang benar
//         $stmt->bind_param(
//             "ssssiisdddsi",
//             $purc_date, $purc_invoice, $purc_nofak, $purc_datefak, $supplier_id, $bayar_id,
//             $purc_jt, $purc_disc, $purc_ppn, $purc_total, $catatan, $user_id
//         );

//         if (!$stmt->execute()) {
//             throw new Exception("Execute statement gagal: " . $stmt->error);
//         }
        
//         $purcid = $this->conn->insert_id;
//         $stmt->close();

//         /** ======================= INSERT DETAIL PRODUK DENGAN VALIDASI ======================= **/
//         if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
//             throw new Exception("Tidak ada produk dalam pembelian");
//         }

//         $detail_sql = "INSERT INTO purc_det 
//                 (purc_id, produk_id, purc_qty, purc_harga, ppn, isi)
//                 VALUES (?, ?, ?, ?, ?, ?)";
        
//         $detail = $this->conn->prepare($detail_sql);
//         if (!$detail) {
//             throw new Exception("Prepare detail gagal: " . $this->conn->error);
//         }

//         $success_count = 0;
//         $produk_ids = $_POST['produk_id'];
        
//         for ($k = 0; $k < count($produk_ids); $k++) {
//             // Sanitize input detail - SUPPORT DESIMAL: Gunakan sanitizeFloat untuk qty
//             $produk_id = $this->sanitizeInt($produk_ids[$k]);
            
//             // SUPPORT DESIMAL: Tetap gunakan sanitizeFloat untuk qty
//             $qty_raw = $_POST['purc_qty'][$k] ?? 0;
//             $qty = $this->sanitizeFloat($qty_raw); // Biarkan sebagai float
            
//             $harga     = $this->sanitizeFloat($_POST['purc_harga'][$k] ?? 0);
//             $ppn_item  = $this->sanitizeFloat($_POST['ppn'][$k] ?? 0);
//             $isi       = $this->sanitizeInt($_POST['isi'][$k] ?? 1);

//             // Validasi data detail
//             if ($produk_id <= 0) {
//                 error_log("Produk ID tidak valid pada index $k: $produk_id");
//                 continue; // Skip invalid product
//             }
            
//             // SUPPORT DESIMAL: Validasi qty sebagai float
//             if ($qty <= 0) {
//                 throw new Exception("Quantity produk harus lebih dari 0 untuk produk index $k");
//             }
            
//             if ($harga < 0) {
//                 throw new Exception("Harga produk tidak valid untuk produk index $k");
//             }

//             $detail->bind_param(
//                 "iidddi",
//                 $purcid, $produk_id,
//                 $qty, $harga, $ppn_item, $isi
//             );
            
//             if (!$detail->execute()) {
//                 throw new Exception("Execute detail gagal untuk produk index $k: " . $detail->error);
//             }
            
//             // Update stok produk - BERTAMBAH pada pembelian
//             $this->updateStokProduk($produk_id, $qty * $isi, 'in');
            
//             // Update harga modal
//             $this->updateHargaModal($produk_id, $harga);
            
//             $success_count++;
//         }
        
//         $detail->close();

//         // Validasi apakah ada produk yang berhasil disimpan
//         if ($success_count === 0) {
//             throw new Exception("Tidak ada produk yang valid untuk disimpan");
//         }

//         /** ======================= LOG AKTIVITAS ======================= **/
//         $this->logActivity("Tambah Pembelian Baru", "purc", $purcid, "Transaksi Pembelian: {$purc_invoice}");

//         // Commit transaksi
//         if (!$this->conn->commit()) {
//             throw new Exception("Commit transaksi gagal: " . $this->conn->error);
//         }

//         error_log("Save Pembelian SUCCESS - PurcID: $purcid, Invoice: $purc_invoice");

//         echo json_encode([
//             "status" => "success",
//             "msg" => "✅ Pembelian berhasil disimpan",
//             "purcid" => $purcid,
//             "purc_invoice" => $purc_invoice
//         ]);

//     } catch (Exception $e) {
//         // Rollback transaksi jika ada error
//         if ($this->conn && method_exists($this->conn, 'rollback')) {
//             $this->conn->rollback();
//         }
        
//         $error_msg = $e->getMessage();
//         error_log("[SAVE_PEMBELIAN ERROR] " . $error_msg);
        
//         http_response_code(400);
//         echo json_encode([
//             "status" => "failed", 
//             "msg" => $error_msg
//         ]);
//     }
//     exit;
// }

    /** ======================= [ GET PEMBELIAN BY ID - SUPPORT DESIMAL ] ======================= **/
    function get_pembelian_by_id()
    {
        try {
            // $this->ensureConnection();

            $purcid = $this->sanitizeInt($_GET['id'] ?? 0);

            if ($purcid <= 0) {
                throw new Exception("❌ ID Pembelian tidak valid!");
            }

            // Get header
            $sql = "SELECT p.*, s.nama as supplier_nama, s.alamat as supplier_alamat,
                           COALESCE((
                               SELECT SUM(pr.retur_total)
                               FROM purc_retur pr
                               WHERE pr.purc_id = p.purcid AND pr.status = 'ACTIVE'
                           ), 0) AS retur_total_active
                FROM purc p 
                LEFT JOIN supplier s ON s.id = p.supplier_id
                WHERE p.purcid = ? AND p.purc_status <> 0";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $purcid);
            $stmt->execute();
            $result = $stmt->get_result();
            $header = $result->fetch_assoc();

            if (!$header) {
                throw new Exception("❌ Data pembelian tidak ditemukan!");
            }

            // Get details
            $sql_detail = "SELECT pd.*, pr.nama_produk, pr.barcode, pr.stok, pr.is_ppn, pr.harga_beli,
                              s.nama_satuan, s.id as satuan_id, pd.isi
                      FROM purc_det pd
                      LEFT JOIN produk pr ON pr.id = pd.produk_id
                      LEFT JOIN satuan s ON s.id = pr.id_satuan
                      WHERE pd.purc_id = ?";

            $stmt_detail = $this->prepare($sql_detail);
            $stmt_detail->bind_param("i", $purcid);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            $details = [];

            while ($row = $result_detail->fetch_assoc()) {
                // SUPPORT DESIMAL: Biarkan nilai asli dari database (tidak di-convert ke int)
                // $row['purc_qty'] tetap sebagai float/desimal
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
            error_log("[GET_PEMBELIAN_BY_ID ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }


    /** ======================= [ UPDATE PEMBELIAN - FIXED ] ======================= **/
    function update_pembelian()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

            // ======================= VALIDASI INPUT =======================
            $purcid       = $this->sanitizeInt($_POST['purcid'] ?? 0);
            $purc_date    = $this->sanitizeDate($_POST['purc_date'] ?? date("Y-m-d"));
            $purc_invoice = $this->sanitizeString($_POST['purc_invoice'] ?? '');
            $purc_nofak   = $this->sanitizeString($_POST['purc_nofak'] ?? '');
            $purc_datefak = !empty($_POST['purc_datefak']) ? $this->sanitizeDate($_POST['purc_datefak']) : null;
            $supplier_id  = $this->sanitizeInt($_POST['supplier_id'] ?? 0);
            $bayar_id     = $this->sanitizeInt($_POST['bayar_id'] ?? 1);
            $purc_jt      = !empty($_POST['purc_jt']) ? $this->sanitizeDate($_POST['purc_jt']) : null;
            $purc_disc    = $this->sanitizeFloat($_POST['purc_disc'] ?? 0);
            $purc_ppn     = $this->sanitizeFloat($_POST['purc_ppn'] ?? 0);
            $purc_total   = $this->sanitizeFloat($_POST['purc_total'] ?? 0);
            $catatan      = $this->sanitizeString($_POST['catatan'] ?? '');
            $user_id      = $this->sanitizeInt($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);

            if ($purcid <= 0) {
                throw new Exception("❌ ID Pembelian tidak valid!");
            }

            // ======================= VALIDASI DATA =======================
            if (empty($purc_date)) {
                throw new Exception("❌ Tanggal faktur harus diisi!");
            }

            if ($supplier_id <= 0) {
                throw new Exception("❌ Pilih supplier terlebih dahulu!");
            }

            if ($purc_disc < 0) {
                throw new Exception("Diskon pembelian tidak boleh minus");
            }

            // ======================= VALIDASI PRODUK =======================
            if (empty($_POST['produk_id']) || !is_array($_POST['produk_id'])) {
                throw new Exception("❌ Tidak ada produk dalam pembelian!");
            }

            // ======================= MULAI TRANSAKSI =======================
            if (!$this->conn->begin_transaction()) {
                throw new Exception("❌ Gagal memulai transaksi database: " . $this->conn->error);
            }

            // ======================= CEK STATUS TRANSAKSI =======================
            $check_sql = "SELECT purc_status, purc_invoice FROM purc WHERE purcid = ?";
            $check_stmt = $this->conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("❌ Prepare check failed: " . $this->conn->error);
            }

            $check_stmt->bind_param("i", $purcid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();

            if (!$check_data) {
                throw new Exception("❌ Data pembelian tidak ditemukan!");
            }

            if ((int)$check_data['purc_status'] !== 1) {
                throw new Exception("❌ Transaksi tidak aktif, tidak dapat diedit!");
            }

            // Detail pembelian adalah sumber referensi retur. Mengubahnya akan
            // merusak jejak qty/harga retur dan dapat membuat stok tidak sinkron.
            $this->assertNoLinkedPurchaseReturns($purcid, false, 'diedit');

            error_log("🔄 UPDATE PEMBELIAN - PurcID: $purcid, Invoice: " . $check_data['purc_invoice']);

            if (empty($purc_invoice)) {
                $purc_invoice = $check_data['purc_invoice'];
            }

            // ======================= KEMBALIKAN STOK LAMA =======================
            $this->revertStokPembelian($purcid);

            // ======================= UPDATE HEADER PEMBELIAN =======================
            $update_sql = "UPDATE purc SET 
                        purc_date = ?, 
                        purc_invoice = ?, 
                        purc_nofak = ?, 
                        purc_datefak = ?,
                        supplier_id = ?, 
                        bayar_id = ?, 
                        purc_jt = ?, 
                        purc_disc = ?, 
                        ppn = ?, 
                        purc_total = ?, 
                        catatan = ?, 
                        user_id = ?, 
                        updated_at = NOW()
                    WHERE purcid = ? AND purc_status = 1";

            $update_stmt = $this->conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("❌ Prepare update header failed: " . $this->conn->error);
            }

            $update_stmt->bind_param(
                "ssssiisdddsii",
                $purc_date,     // s - string
                $purc_invoice,  // s - string  
                $purc_nofak,    // s - string
                $purc_datefak,  // s - string
                $supplier_id,   // i - integer
                $bayar_id,      // i - integer
                $purc_jt,       // s - string (bisa NULL)
                $purc_disc,     // d - double
                $purc_ppn,      // d - double
                $purc_total,    // d - double
                $catatan,       // s - string
                $user_id,       // i - integer
                $purcid         // i - integer
            );

            if (!$update_stmt->execute()) {
                throw new Exception("❌ Execute update header failed: " . $update_stmt->error);
            }

            $affected_rows = $update_stmt->affected_rows;
            $update_stmt->close();

            if (false && $affected_rows === 0) {
                throw new Exception("❌ Tidak ada data yang diupdate. Mungkin data tidak ditemukan atau status bukan ACTIVE.");
            }

            error_log("✅ Header updated - Affected rows: $affected_rows");

            // ======================= HAPUS DETAIL LAMA =======================
            $delete_sql = "DELETE FROM purc_det WHERE purc_id = ?";
            $delete_stmt = $this->conn->prepare($delete_sql);
            if (!$delete_stmt) {
                throw new Exception("❌ Prepare delete detail failed: " . $this->conn->error);
            }

            $delete_stmt->bind_param("i", $purcid);
            if (!$delete_stmt->execute()) {
                throw new Exception("❌ Execute delete detail failed: " . $delete_stmt->error);
            }

            $deleted_rows = $delete_stmt->affected_rows;
            $delete_stmt->close();

            error_log("🗑️ Detail deleted - Affected rows: $deleted_rows");

            // ======================= INSERT DETAIL BARU =======================
            $detail_sql = "INSERT INTO purc_det 
                        (purc_id, produk_id, purc_qty, purc_harga, ppn, isi)
                        VALUES (?, ?, ?, ?, ?, ?)";

            $detail_stmt = $this->conn->prepare($detail_sql);
            if (!$detail_stmt) {
                throw new Exception("❌ Prepare insert detail failed: " . $this->conn->error);
            }

            $success_count = 0;
            $computed_subtotal = 0.0;
            $computed_ppn = 0.0;
            $produk_ids = $_POST['produk_id'];

            foreach ($produk_ids as $k => $pid_raw) {
                // Sanitize input detail
                $produk_id = $this->sanitizeInt($pid_raw);
                $qty       = $this->sanitizeQuantity($_POST['purc_qty'][$k] ?? 0);
                $harga     = $this->sanitizeFloat($_POST['purc_harga'][$k] ?? 0);
                $isi       = $this->sanitizeInt($_POST['isi'][$k] ?? 1);

                // Validasi data detail
                if ($produk_id <= 0) {
                    error_log("⚠️ Produk ID tidak valid pada index $k: $produk_id");
                    continue;
                }

                if ($qty <= 0) {
                    throw new Exception("❌ Quantity produk tidak valid untuk produk index " . ($k + 1));
                }

                if ($harga < 0) {
                    throw new Exception("❌ Harga produk tidak valid untuk produk index " . ($k + 1));
                }

                if ($isi <= 0) {
                    throw new Exception("Isi kemasan tidak valid untuk produk index " . ($k + 1));
                }

                $line_subtotal = $qty * $harga;
                $ppn_item = $this->purchaseItemPpn($produk_id, $line_subtotal);
                $computed_subtotal += $line_subtotal;
                $computed_ppn += $ppn_item;

                $detail_stmt->bind_param(
                    "iidddi",
                    $purcid,     // i - integer
                    $produk_id,  // i - integer
                    $qty,        // d - double
                    $harga,      // d - double  
                    $ppn_item,   // d - double
                    $isi         // i - integer
                );

                if (!$detail_stmt->execute()) {
                    throw new Exception("❌ Execute insert detail failed untuk produk index " . ($k + 1) . ": " . $detail_stmt->error);
                }

                // Update stok produk - BERTAMBAH pada pembelian
                $this->updateStokProduk($produk_id, $qty * $isi, 'in');

                // Update harga modal
                $this->updateHargaModal($produk_id, $harga);

                $success_count++;
            }

            $detail_stmt->close();

            if ($success_count === 0) {
                throw new Exception("❌ Tidak ada produk yang valid untuk disimpan!");
            }

            error_log("✅ Detail inserted - Success count: $success_count");

            if ($purc_disc > $computed_subtotal) {
                throw new Exception("Diskon pembelian tidak boleh melebihi subtotal produk");
            }
            $purc_ppn = round($computed_ppn);
            $purc_total = round(($computed_subtotal - $purc_disc) + $purc_ppn, 2);
            if ($purc_total <= 0) {
                throw new Exception("Total pembelian hasil perhitungan server harus lebih dari 0");
            }

            $total_update = $this->conn->prepare("UPDATE purc SET ppn = ?, purc_total = ? WHERE purcid = ?");
            if (!$total_update) {
                throw new Exception("âŒ Prepare update total pembelian failed: " . $this->conn->error);
            }

            $total_update->bind_param("ddi", $purc_ppn, $purc_total, $purcid);
            if (!$total_update->execute()) {
                throw new Exception("âŒ Execute update total pembelian failed: " . $total_update->error);
            }
            $total_update->close();

            $this->syncHutangPembelian(
                $purcid,
                $purc_invoice,
                $check_data['purc_invoice'],
                $bayar_id,
                $supplier_id,
                $purc_date,
                $purc_jt,
                $purc_total,
                $catatan,
                $user_id
            );

            // ======================= LOG AKTIVITAS =======================
            $this->logActivity(
                "Update Pembelian",
                "purc",
                $purcid,
                "Edit transaksi pembelian: {$purc_invoice}"
            );

            // ======================= COMMIT TRANSAKSI =======================
            if (!$this->conn->commit()) {
                throw new Exception("❌ Commit transaksi gagal: " . $this->conn->error);
            }

            error_log("🎉 UPDATE PEMBELIAN SUCCESS - PurcID: $purcid");

            echo json_encode([
                "status" => "success",
                "msg" => "✅ Pembelian berhasil diperbarui",
                "purcid" => $purcid,
                "purc_invoice" => $purc_invoice,
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
            error_log("[UPDATE_PEMBELIAN ERROR] " . $error_msg);

            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $error_msg
            ]);
        }
        exit;
    }

    /** ======================= [ DELETE PEMBELIAN ] ======================= **/
    function delete_pembelian()
    {
        try {
            $this->ensureConnection();
            // $this->validateCSRF();

            $purcid = $this->sanitizeInt($_POST['purcid'] ?? 0);

            if ($purcid <= 0) {
                throw new Exception("❌ ID Pembelian tidak valid!");
            }

            // Mulai transaksi
            if (!$this->conn->begin_transaction()) {
                throw new Exception("Gagal memulai transaksi database: " . $this->conn->error);
            }

            // Cek data sebelum dihapus
            $cek = $this->fetchOne("SELECT purc_invoice FROM purc WHERE purcid = ? AND purc_status <> 0", [$purcid]);
            if (!$cek) {
                throw new Exception("❌ Data pembelian tidak ditemukan atau sudah dihapus!");
            }

            // Retur aktif sudah mengurangi stok dari pembelian sumbernya.
            $this->assertNoLinkedPurchaseReturns($purcid, true, 'dihapus');

            $this->removeHutangPembelian($cek['purc_invoice']);

            // Kembalikan stok sebelum dihapus
            $this->revertStokPembelian($purcid);

            // Soft delete - update status menjadi DELETED
            $sql = "UPDATE purc 
                    SET purc_status = 0, updated_at = NOW() 
                    WHERE purcid = ? AND purc_status <> 0";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("❌ Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $purcid);
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
                "Hapus Pembelian",
                "purc",
                $purcid,
                "Penghapusan transaksi pembelian No: {$cek['purc_invoice']}"
            );

            echo json_encode([
                "status" => "success",
                "msg" => "🗑️ Transaksi berhasil dihapus",
                "purcid" => $purcid
            ]);
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            if ($this->conn && method_exists($this->conn, 'rollback')) {
                $this->conn->rollback();
            }

            error_log("[DELETE_PEMBELIAN ERROR] " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                "status" => "failed",
                "msg" => $e->getMessage()
            ]);
        }
        exit;
    }

    // /** ======================= [ GET PEMBELIAN BY ID ] ======================= **/
    // function get_pembelian_by_id() {
    //     try {
    //         // $this->ensureConnection();
            
    //         $purcid = $this->sanitizeInt($_GET['id'] ?? 0);
            
    //         if ($purcid <= 0) {
    //             throw new Exception("❌ ID Pembelian tidak valid!");
    //         }

    //         // Get header
    //         $sql = "SELECT p.*, s.nama as supplier_nama, s.alamat as supplier_alamat
    //                 FROM purc p 
    //                 LEFT JOIN supplier s ON s.id = p.supplier_id
    //                 WHERE p.purcid = ? AND p.purc_status != 'DELETED'";
            
    //         $stmt = $this->prepare($sql);
    //         $stmt->bind_param("i", $purcid);
    //         $stmt->execute();
    //         $result = $stmt->get_result();
    //         $header = $result->fetch_assoc();
            
    //         if (!$header) {
    //             throw new Exception("❌ Data pembelian tidak ditemukan!");
    //         }

    //         // Get details
    //         $sql_detail = "SELECT pd.*, pr.nama_produk, pr.barcode, pr.stok, pr.is_ppn, pr.harga_beli,
    //                               s.nama_satuan, s.id as satuan_id, pd.isi
    //                       FROM purc_det pd
    //                       LEFT JOIN produk pr ON pr.id = pd.produk_id
    //                       LEFT JOIN satuan s ON s.id = pr.id_satuan
    //                       WHERE pd.purc_id = ?";
            
    //         $stmt_detail = $this->prepare($sql_detail);
    //         $stmt_detail->bind_param("i", $purcid);
    //         $stmt_detail->execute();
    //         $result_detail = $stmt_detail->get_result();
    //         $details = [];
            
    //         while ($row = $result_detail->fetch_assoc()) {
    //             $details[] = $row;
    //         }

    //         echo json_encode([
    //             "status" => "success",
    //             "header" => $header,
    //             "details" => $details
    //         ]);

    //         $stmt->close();
    //         $stmt_detail->close();
            
    //     } catch (Exception $e) {
    //         error_log("[GET_PEMBELIAN_BY_ID ERROR] " . $e->getMessage());
    //         http_response_code(400);
    //         echo json_encode([
    //             "status" => "failed", 
    //             "msg" => $e->getMessage()
    //         ]);
    //     }
    //     exit;
    // }

    /** ======================= [ EXPORT EXCEL ] ======================= **/
    function export_excel()
    {
        try {
            // $this->ensureConnection();

            // Filter
            $tgl_awal = $this->sanitizeDate($_GET['tgl_awal'] ?? date('Y-m-01'));
            $tgl_akhir = $this->sanitizeDate($_GET['tgl_akhir'] ?? date('Y-m-d'));
            $jenis = $this->sanitizeInt($_GET['jenis'] ?? '');
            $supplier_id = $this->sanitizeInt($_GET['supplier_id'] ?? 0);

            $whereParts = ["p.purc_status <> 0"];
            $params = [];
            $types = "";

            // Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(p.purc_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // Filter jenis transaksi
            if (!empty($jenis)) {
                $whereParts[] = "p.bayar_id = ?";
                $params[] = $jenis;
                $types .= "i";
            }

            // Filter supplier
            if ($supplier_id > 0) {
                $whereParts[] = "p.supplier_id = ?";
                $params[] = $supplier_id;
                $types .= "i";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            $sql = "SELECT 
                        p.purc_date,
                        p.purc_invoice,
                        p.purc_nofak,
                        CASE p.bayar_id 
                            WHEN 1 THEN 'Tunai' 
                            WHEN 2 THEN 'Kredit' 
                            ELSE 'Lainnya' 
                        END as jenis_transaksi,
                        s.nama as supplier_name,
                        p.purc_total,
                        p.purc_status
                    FROM purc p
                    LEFT JOIN supplier s ON s.id = p.supplier_id
                    $where
                    ORDER BY p.purc_date DESC, p.purcid DESC";

            $stmt = $this->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            // Set header untuk download Excel
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="laporan_pembelian_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo "LAPORAN PEMBELIAN\n";
            echo "Periode: " . date('d/m/Y', strtotime($tgl_awal)) . " - " . date('d/m/Y', strtotime($tgl_akhir)) . "\n\n";

            echo "Tanggal\tNo Invoice\tNo Faktur\tJenis Transaksi\tSupplier\tTotal\tStatus\n";

            $total_all = 0;
            while ($row = $res->fetch_assoc()) {
                echo date('d/m/Y', strtotime($row['purc_date'])) . "\t";
                echo $row['purc_invoice'] . "\t";
                echo $row['purc_nofak'] . "\t";
                echo $row['jenis_transaksi'] . "\t";
                echo $row['supplier_name'] . "\t";
                echo number_format($row['purc_total'], 0, ',', '.') . "\t";
                echo $row['purc_status'] . "\n";

                $total_all += $row['purc_total'];
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

    /** ======================= [ SEARCH SUPPLIER ] ======================= **/
    function search_supplier()
    {
        try {
            // $this->ensureConnection();

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
            // $this->ensureConnection();

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
                    "stok"         => (float)$row['stok'],
                    "harga_beli"   => (float)$row['harga_beli'],
                    "harga_newmodal"  => (float)$row['harga_newmodal'],
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

    /** ======================= [ HELPER METHODS - STOK MANAGEMENT ] ======================= **/

    private function assertNoLinkedPurchaseReturns($purc_id, $active_only, $operation)
    {
        $sql = "SELECT COUNT(*) AS total FROM purc_retur WHERE purc_id = ?";
        if ($active_only) {
            $sql .= " AND status = 'ACTIVE'";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Gagal memeriksa keterkaitan retur pembelian: " . $this->conn->error);
        }

        $stmt->bind_param('i', $purc_id);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal memeriksa keterkaitan retur pembelian: " . $error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int)($row['total'] ?? 0) > 0) {
            $scope = $active_only ? 'retur pembelian aktif' : 'riwayat retur pembelian';
            throw new Exception(
                "Pembelian tidak dapat {$operation} karena sudah mempunyai {$scope}. " .
                "Ubah atau batalkan retur dari menu Retur Pembelian terlebih dahulu."
            );
        }
    }

    /**
     * Update stok produk
     */
    private function updateStokProduk($produk_id, $qty, $type = 'in')
    {
        $produk_id = $this->sanitizeInt($produk_id);
        $qty = $this->sanitizeQuantity($qty);
        if ($produk_id <= 0 || $qty <= 0) {
            throw new Exception("Data stok produk tidak valid");
        }

        $operator = ($type == 'in') ? '+' : '-';
        $stmt = $this->conn->prepare("UPDATE produk SET stok = stok {$operator} ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare update stok gagal: " . $this->conn->error);
        }

        $stmt->bind_param("di", $qty, $produk_id);
        if (!$stmt->execute()) {
            throw new Exception("Update stok produk gagal: " . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Update harga modal produk
     */
    private function updateHargaModal($produk_id, $harga)
    {
        $produk_id = $this->sanitizeInt($produk_id);
        $harga = $this->sanitizeFloat($harga);
        if ($produk_id <= 0 || $harga < 0) {
            throw new Exception("Data harga modal produk tidak valid");
        }

        $stmt = $this->conn->prepare(
            "UPDATE produk
             SET harga_newmodal = GREATEST(COALESCE(harga_beli, 0), ?),
                 harga_sales = CASE
                     WHEN COALESCE(harga_sales, 0) > 0
                          AND harga_sales < GREATEST(COALESCE(harga_beli, 0), ?)
                         THEN GREATEST(COALESCE(harga_beli, 0), ?)
                     ELSE COALESCE(harga_sales, 0)
                 END,
                 harga_kasir = CASE
                     WHEN COALESCE(harga_kasir, 0) > 0
                          AND harga_kasir < GREATEST(COALESCE(harga_beli, 0), ?)
                         THEN GREATEST(COALESCE(harga_beli, 0), ?)
                     ELSE COALESCE(harga_kasir, 0)
                 END,
                 P2 = CASE
                     WHEN COALESCE(P2, 0) > 0
                          AND P2 < GREATEST(COALESCE(harga_beli, 0), ?)
                         THEN GREATEST(COALESCE(harga_beli, 0), ?)
                     ELSE COALESCE(P2, 0)
                 END,
                 P3 = CASE
                     WHEN COALESCE(P3, 0) > 0
                          AND P3 < GREATEST(COALESCE(harga_beli, 0), ?)
                         THEN GREATEST(COALESCE(harga_beli, 0), ?)
                     ELSE COALESCE(P3, 0)
                 END,
                 P4 = CASE
                     WHEN COALESCE(P4, 0) > 0
                          AND P4 < GREATEST(COALESCE(harga_beli, 0), ?)
                         THEN GREATEST(COALESCE(harga_beli, 0), ?)
                     ELSE COALESCE(P4, 0)
                 END
             WHERE id = ?"
        );
        if (!$stmt) {
            throw new Exception("Prepare update harga modal gagal: " . $this->conn->error);
        }

        $stmt->bind_param(
            "dddddddddddi",
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $harga,
            $produk_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Update harga modal gagal: " . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Kembalikan stok dari pembelian yang akan dihapus/diupdate
     */
    private function revertStokPembelian($purc_id)
    {
        $purc_id = $this->sanitizeInt($purc_id);
        if ($purc_id <= 0) {
            throw new Exception("ID pembelian tidak valid untuk revert stok");
        }

        $stmt = $this->conn->prepare("SELECT produk_id, purc_qty, isi FROM purc_det WHERE purc_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare revert stok gagal: " . $this->conn->error);
        }

        $stmt->bind_param("i", $purc_id);
        $stmt->execute();
        $qry = $stmt->get_result();

        while ($row = $qry->fetch_assoc()) {
            $this->updateStokProduk($row['produk_id'], $row['purc_qty'] * $row['isi'], 'out');
        }

        $stmt->close();
    }

    private function nextHutangNumber()
    {
        $prefix = "HUT-" . date("Y-m-");
        $like = $prefix . "%";
        $stmt = $this->conn->prepare("SELECT no_hutang FROM hutang_supplier WHERE no_hutang LIKE ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare nomor hutang gagal: " . $this->conn->error);
        }

        $stmt->bind_param("s", $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $next = 1;
        if (!empty($row['no_hutang']) && preg_match('/(\d+)$/', $row['no_hutang'], $matches)) {
            $next = ((int)$matches[1]) + 1;
        }

        return $prefix . str_pad($next, 4, "0", STR_PAD_LEFT);
    }

    private function hutangStatus($total, $paid, $dueDate)
    {
        if (((float)$total - (float)$paid) <= 0) {
            return "LUNAS";
        }

        if (!empty($dueDate) && strtotime($dueDate) < strtotime(date("Y-m-d"))) {
            return "OVERDUE";
        }

        return "BELUM";
    }

    private function syncHutangPembelian($purcid, $invoice, $oldInvoice, $bayar_id, $supplier_id, $tanggal, $jatuh_tempo, $total, $catatan, $user_id)
    {
        $invoice = trim((string)$invoice);
        $oldInvoice = trim((string)$oldInvoice);
        $jatuh_tempo = $jatuh_tempo ?: $tanggal;

        if ((int)$bayar_id !== 2) {
            $this->removeHutangPembelian($oldInvoice ?: $invoice, true);
            if ($oldInvoice !== '' && $oldInvoice !== $invoice) {
                $this->removeHutangPembelian($invoice, true);
            }
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT id, total_dibayar
            FROM hutang_supplier
            WHERE ref_pembelian = ? OR ref_pembelian = ?
            ORDER BY id ASC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare cek hutang gagal: " . $this->conn->error);
        }
        $lookupOld = $oldInvoice !== '' ? $oldInvoice : $invoice;
        $stmt->bind_param("ss", $invoice, $lookupOld);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $paid = (float)($existing['total_dibayar'] ?? 0);
        if ($paid > (float)$total) {
            throw new Exception("Total pembelian tidak boleh lebih kecil dari pembayaran hutang yang sudah tercatat");
        }

        $remaining = max((float)$total - $paid, 0);
        $status = $this->hutangStatus($total, $paid, $jatuh_tempo);
        $description = trim($catatan) !== '' ? $catatan : "Hutang dari pembelian {$invoice}";

        if ($existing) {
            $stmt = $this->conn->prepare("
                UPDATE hutang_supplier
                SET tanggal_hutang = ?,
                    tanggal_jatuh_tempo = ?,
                    supplier_id = ?,
                    total_hutang = ?,
                    sisa_hutang = ?,
                    status = ?,
                    keterangan = ?,
                    ref_pembelian = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception("Prepare update hutang gagal: " . $this->conn->error);
            }
            $hutangId = (int)$existing['id'];
            $stmt->bind_param("ssiddsssi", $tanggal, $jatuh_tempo, $supplier_id, $total, $remaining, $status, $description, $invoice, $hutangId);
            if (!$stmt->execute()) {
                throw new Exception("Update hutang pembelian gagal: " . $stmt->error);
            }
            $stmt->close();
            return;
        }

        $noHutang = $this->nextHutangNumber();
        $stmt = $this->conn->prepare("
            INSERT INTO hutang_supplier
                (no_hutang, tanggal_hutang, tanggal_jatuh_tempo, supplier_id, total_hutang, total_dibayar, sisa_hutang, status, keterangan, ref_pembelian, user_id, tgl_input)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception("Prepare insert hutang gagal: " . $this->conn->error);
        }
        $stmt->bind_param("sssiddsssi", $noHutang, $tanggal, $jatuh_tempo, $supplier_id, $total, $remaining, $status, $description, $invoice, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Insert hutang pembelian gagal: " . $stmt->error);
        }
        $stmt->close();
    }

    private function removeHutangPembelian($invoice, $ignoreMissing = false)
    {
        $invoice = trim((string)$invoice);
        if ($invoice === '') {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT h.id, COUNT(p.id) AS total_pembayaran
            FROM hutang_supplier h
            LEFT JOIN pembayaran_hutang p ON p.hutang_id = h.id
            WHERE h.ref_pembelian = ?
            GROUP BY h.id
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare cek hutang pembelian gagal: " . $this->conn->error);
        }
        $stmt->bind_param("s", $invoice);
        $stmt->execute();
        $hutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$hutang) {
            if (!$ignoreMissing) {
                return;
            }
            return;
        }

        if ((int)$hutang['total_pembayaran'] > 0) {
            throw new Exception("Pembelian tidak dapat diubah/dihapus karena hutangnya sudah memiliki pembayaran");
        }

        $stmt = $this->conn->prepare("DELETE FROM hutang_supplier WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare hapus hutang pembelian gagal: " . $this->conn->error);
        }
        $hutangId = (int)$hutang['id'];
        $stmt->bind_param("i", $hutangId);
        if (!$stmt->execute()) {
            throw new Exception("Hapus hutang pembelian gagal: " . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Catat aktivitas user ke tabel log_aktivitas
     */
    private function logActivity($aksi, $tabel, $record_id, $keterangan = "")
    {
        try {
            // $this->ensureConnection();

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

    /** ======================= [ GENERATE INVOICE ] ======================= **/
    private function generate_invoice()
    {
        // $this->ensureConnection();

        $tahun = date("Y");
        $bulan = date("m");
        $prefix = "PURC-{$tahun}{$bulan}-";

        $stmt = $this->prepare("SELECT purc_invoice FROM purc WHERE purc_invoice LIKE ? ORDER BY purcid DESC LIMIT 1");
        $like = "{$prefix}%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();

        $next = 1;
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $last_invoice = $row['purc_invoice'];
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

    // Kirim headers ASAP
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Instantiate class
    $Pembelian = new Pembelian();
    $action = request_action('none');

    $allowed_actions = [
        'get_data_pembelian',
        'search_supplier',
        'search_produk',
        'save_pembelian',
        'update_pembelian',
        'get_pembelian_by_id',
        'delete_pembelian',
        'export_excel'
    ];

    if (in_array($action, $allowed_actions)) {
        // Call method - method akan handle JSON output dan exit
        $Pembelian->$action();
        // Tidak akan mencapai sini karena method call exit()
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'failed', 'msg' => 'Action not allowed']);
        exit(0);
    }
} catch (Exception $e) {
    error_log("[PEMBELIAN ROUTER ERROR] " . $e->getMessage());
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
