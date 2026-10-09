<?php
require_once '../config.php';

class Transaksi {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    private function normalizeLegacyUnitPrice($storedPrice, $masterPrice = 0, $detailTotal = 0, $saleTotal = 0) {
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

    /**
     * Validasi CSRF token dengan lebih fleksibel
     */
    private function validateCSRF() {
        // Untuk request GET (DataTables) tidak perlu CSRF
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }

        // Cek token dari berbagai sumber
        $token = null;
        
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (isset($_GET['csrf_token'])) {
            $token = $_GET['csrf_token'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } else {
            // Untuk API request dengan JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }

        if ($token && isset($_SESSION['csrf_token'])) {
            return hash_equals($_SESSION['csrf_token'], $token);
        }

        // Log untuk debugging
        error_log("CSRF Validation Failed: Token not found or invalid");
        return false;
    }

    /**
     * Datatable server-side processing untuk penjualan
     */
    public function dt_penjualan() {
        // // CSRF Validation
        // if (!$this->validateCSRF()) {
        //     return json_encode(['error' => 'Token CSRF tidak valid']);
        // }

        // Untuk DataTables, kita relax CSRF check karena ini read-only operation
        // Tapi tetap validasi user session
        if (!isset($_SESSION['user_id'])) {
            return json_encode(['error' => 'Unauthorized access']);
        }

        // Column definition
        $columns = array(
            0 => 's.saleid',
            1 => 's.sale_date',
            2 => 's.sale_invoice',
            3 => 's.bayar_id',
            4 => 'p.nama',
            5 => 's.sale_total',
            6 => 's.status'
        );

        // // Paging parameters
        // $limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
        // $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        // $order = $columns[$_POST['order'][0]['column']] ?? 's.saleid';
        // $dir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';
        // $search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

        // Paging parameters
        $limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $order = isset($_POST['order'][0]['column']) ? $columns[$_POST['order'][0]['column']] : 's.saleid';
        $dir = isset($_POST['order'][0]['dir']) ? $this->conn->real_escape_string($_POST['order'][0]['dir']) : 'desc';
        $search = isset($_POST['search']['value']) ? $this->conn->real_escape_string($_POST['search']['value']) : '';

        // Main query
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
                s.saleid, s.sale_date, s.sale_invoice, s.bayar_id, 
                s.sale_jt, s.sale_total, s.sale_disc, s.ppn, s.status,
                p.nama as nama_pelanggan,
                u.firstname as sales_name
                FROM sale s 
                LEFT JOIN pelanggan p ON s.cust_id = p.id 
                LEFT JOIN users u ON s.sales_id = u.id 
                WHERE 1=1";

        $params = [];
        $types = '';

        // Search filter
        if (!empty($search)) {
            $sql .= " AND (s.sale_invoice LIKE ? OR p.nama LIKE ? OR u.firstname LIKE ?)";
            $search_term = "%{$search}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }

        // User level filter (jika perlu)
        $user_level = isset($_POST['user_level']) ? intval($_POST['user_level']) : 0;
        if ($user_level > 1) {
            // Filter berdasarkan sales tertentu jika bukan admin
            $sql .= " AND s.sales_id = ?";
            $params[] = $_SESSION['user_id'] ?? 0;
            $types .= 'i';
        }

        $sql .= " ORDER BY {$order} {$dir} LIMIT ?, ?";
        $params[] = $start;
        $params[] = $limit;
        $types .= 'ii';

        // // Prepare and execute main query
        // $stmt = $this->conn->prepare($sql);
        // if ($params) {
        //     $stmt->bind_param($types, ...$params);
        // }
        // $stmt->execute();
        // $result = $stmt->get_result();

        // $data = array();
        // $no = $start + 1;

        // Prepare and execute main query
        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            return json_encode(['error' => 'Database error: ' . $stmt->error]);
        }
        
        $result = $stmt->get_result();

        $data = array();
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $nestedData = array();
            $nestedData[] = $no++;
            $nestedData[] = $row['sale_date'];
            $nestedData[] = $row['sale_invoice'];
            $nestedData[] = $row['bayar_id'];
            $nestedData[] = $row['nama_pelanggan'];
            $nestedData[] = number_format($row['sale_total'], 2, '.', '');
            $nestedData[] = $row['status'];
            $nestedData[] = $row['saleid'];
            $data[] = $nestedData;
        }

        // Total records
        $totalRecords = $this->conn->query("SELECT FOUND_ROWS()")->fetch_row()[0];

        // Output
        $json_data = array(
            "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecords,
            "data" => $data
        );

        return json_encode($json_data);
    }

    // /**
    //  * Get detail penjualan
    //  */
    // public function get_sale_detail($saleid) {
    //     if (!$this->validateCSRF()) {
    //         return ['status' => 'error', 'message' => 'Token CSRF tidak valid'];
    //     }

    //     $saleid = intval($saleid);
    //     if ($saleid <= 0) {
    //         return ['status' => 'error', 'message' => 'ID penjualan tidak valid'];
    //     }

    /**
     * Get detail penjualan
     */
    public function get_sale_detail($saleid) {
        // Relax CSRF untuk read operations, tapi validasi session
        if (!isset($_SESSION['user_id'])) {
            return ['status' => 'error', 'message' => 'Unauthorized access'];
        }

        $saleid = intval($saleid);
        if ($saleid <= 0) {
            return ['status' => 'error', 'message' => 'ID penjualan tidak valid'];
        }

        try {
            // Header penjualan
            $stmt = $this->conn->prepare("
                SELECT s.*, p.nama as nama_pelanggan, p.alamat, p.telpon,
                       u.firstname as sales_name
                FROM sale s 
                LEFT JOIN pelanggan p ON s.cust_id = p.id 
                LEFT JOIN users u ON s.sales_id = u.id 
                WHERE s.saleid = ?
            ");
            $stmt->bind_param("i", $saleid);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 0) {
                return ['status' => 'error', 'message' => 'Data penjualan tidak ditemukan'];
            }

            $data = $result->fetch_assoc();

            // Detail items
            $stmt2 = $this->conn->prepare("
                SELECT sd.*, pr.nama_produk, pr.barcode, pr.harga_kasir, pr.harga_sales, pr.harga_newmodal, sat.nama_satuan
                FROM sale_det sd
                LEFT JOIN produk pr ON sd.produk_id = pr.id
                LEFT JOIN satuan sat ON pr.id_satuan = sat.id
                WHERE sd.sale_id = ?
            ");
            $stmt2->bind_param("i", $saleid);
            $stmt2->execute();
            $items_result = $stmt2->get_result();
            $items = [];

            while($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }

            $detail_total = 0;
            foreach ($items as $item) {
                $detail_total += (float)($item['sale_qty'] ?? 0) * (float)($item['sale_harga'] ?? 0);
            }

            foreach ($items as &$item) {
                $master_price = (float)($item['harga_sales'] ?: $item['harga_kasir'] ?: 0);
                $item['sale_harga_raw'] = $item['sale_harga'] ?? 0;
                $item['sale_harga'] = $this->normalizeLegacyUnitPrice($item['sale_harga'] ?? 0, $master_price, $detail_total, $data['sale_total'] ?? 0);
                $item['subtotal'] = (float)($item['sale_qty'] ?? 0) * (float)$item['sale_harga'];
                $item['discount_amount'] = $item['subtotal'] * ((float)($item['sale_pdisc'] ?? 0)) / 100;
            }
            unset($item);

            $data['items'] = $items;

            return ['status' => 'success', 'data' => $data];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }

    /**
     * Hapus penjualan
     */
    public function delete_penjualan($saleid) {
        if (!$this->validateCSRF()) {
            return ['status' => 'error', 'message' => 'Token CSRF tidak valid'];
        }

        $saleid = intval($saleid);
        if ($saleid <= 0) {
            return ['status' => 'error', 'message' => 'ID penjualan tidak valid'];
        }

        $this->conn->autocommit(FALSE);

        try {
            // Cek apakah penjualan sudah diposting
            $stmt = $this->conn->prepare("SELECT post, sale_invoice, stock_applied FROM sale WHERE saleid = ? FOR UPDATE");
            $stmt->bind_param("i", $saleid);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 0) {
                throw new Exception("Data penjualan tidak ditemukan");
            }

            $sale_data = $result->fetch_assoc();
            if($sale_data['post'] == 1) {
                throw new Exception("Penjualan sudah diposting dan tidak dapat dihapus");
            }

            // Kembalikan stok hanya bila transaksi memang pernah menerapkannya.
            if ((int)($sale_data['stock_applied'] ?? 0) === 1) {
                $stmt = $this->conn->prepare("
                    SELECT produk_id, sale_qty 
                    FROM sale_det 
                    WHERE sale_id = ?
                ");
                $stmt->bind_param("i", $saleid);
                $stmt->execute();
                $items_result = $stmt->get_result();

                while($item = $items_result->fetch_assoc()) {
                    $update_stmt = $this->conn->prepare("
                        UPDATE produk 
                        SET stok = stok + ? 
                        WHERE id = ?
                    ");
                    $update_stmt->bind_param("di", $item['sale_qty'], $item['produk_id']);
                    $update_stmt->execute();
                }
            }

            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $rewardSystem->cancelPointsForTransaction($saleid);

            // Hapus transaksi terkait
            $stmt = $this->conn->prepare("DELETE FROM transaksi WHERE sale_id = ?");
            $stmt->bind_param("i", $saleid);
            $stmt->execute();

            // Hapus detail penjualan
            $stmt = $this->conn->prepare("DELETE FROM sale_det WHERE sale_id = ?");
            $stmt->bind_param("i", $saleid);
            $stmt->execute();

            // Hapus header penjualan
            $stmt = $this->conn->prepare("DELETE FROM sale WHERE saleid = ?");
            $stmt->bind_param("i", $saleid);
            $stmt->execute();

            $this->conn->commit();

            // Log activity
            $this->logActivity(
                "Hapus Penjualan", 
                "sale", 
                $saleid, 
                "Menghapus penjualan {$sale_data['sale_invoice']}"
            );

            return ['status' => 'success', 'message' => 'Penjualan berhasil dihapus'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => 'error', 'message' => $e->getMessage()];
        } finally {
            $this->conn->autocommit(TRUE);
        }
    }

    /**
     * Batalkan penjualan (soft delete)
     */
    public function batal_penjualan($id) {
        if (!$this->validateCSRF()) {
            return ['status' => 'error', 'message' => 'Token CSRF tidak valid'];
        }

        $id = intval($id);
        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'ID penjualan tidak valid'];
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE sale 
                SET status = 2, post = 0 
                WHERE saleid = ? AND post = 0
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                // Log activity
                $this->logActivity(
                    "Batal Penjualan", 
                    "sale", 
                    $id, 
                    "Membatalkan penjualan ID: {$id}"
                );

                return ['status' => 'success', 'message' => 'Penjualan berhasil dibatalkan'];
            } else {
                return ['status' => 'error', 'message' => 'Penjualan tidak dapat dibatalkan atau sudah diposting'];
            }

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }

    /**
     * Proses penjualan baru
     */
    public function process_sale($data) {
        if (!$this->validateCSRF()) {
            return ['status' => 'error', 'message' => 'Token CSRF tidak valid'];
        }

        $this->conn->autocommit(FALSE);

        try {
            // Validasi data
            if (empty($data['customer_id']) || empty($data['items']) || count($data['items']) === 0) {
                throw new Exception("Data pelanggan dan items tidak boleh kosong");
            }

            // Generate invoice number
            $invoice_no = $this->generateInvoiceNumber();

            // Calculate totals
            $totals = $this->calculateTotals($data['items'], $data['discount_perc'] ?? 0);

            // Insert sale header
            $sale_id = $this->insertSaleHeader($data, $invoice_no, $totals);
            
            // Insert sale details
            $this->insertSaleDetails($sale_id, $data['items']);
            
            // Update product stock
            $this->updateProductStock($data['items']);
            $stockAppliedStmt = $this->conn->prepare("UPDATE sale SET stock_applied = 1 WHERE saleid = ?");
            $stockAppliedStmt->bind_param('i', $sale_id);
            if (!$stockAppliedStmt->execute()) {
                throw new Exception('Gagal menandai penerapan stok transaksi');
            }
            $stockAppliedStmt->close();
            
            // Insert transaction record
            $this->insertTransactionRecord($sale_id, $data, $totals, $invoice_no);
            
            // Process member points if applicable
            $this->processMemberPoints($data['customer_id'], $sale_id, $totals['grand_total']);

            $this->conn->commit();

            // Log activity
            $this->logActivity(
                "Buat Penjualan", 
                "sale", 
                $sale_id, 
                "Membuat penjualan baru: {$invoice_no}"
            );

            return [
                'status' => 'success',
                'sale_id' => $sale_id,
                'invoice_no' => $invoice_no,
                'message' => 'Transaksi berhasil diproses'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } finally {
            $this->conn->autocommit(TRUE);
        }
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        $sequence = 1;

        // Get last invoice number today
        $sql = "SELECT sale_invoice FROM sale WHERE sale_invoice LIKE ? ORDER BY saleid DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $like_pattern = $prefix . $date . '%';
        $stmt->bind_param('s', $like_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $last_invoice = $result->fetch_assoc()['sale_invoice'];
            $last_sequence = intval(substr($last_invoice, -4));
            $sequence = $last_sequence + 1;
        }

        return $prefix . $date . sprintf('%04d', $sequence);
    }

    /**
     * Calculate transaction totals
     */
    private function calculateTotals($items, $discount_perc) {
        $subtotal = 0;
        
        foreach ($items as $item) {
            $item_subtotal = ($item['price'] * $item['quantity']);
            $item_discount = ($item_subtotal * ($item['discount'] ?? 0)) / 100;
            $subtotal += ($item_subtotal - $item_discount);
        }
        
        $discount_amount = ($subtotal * $discount_perc) / 100;
        $after_discount = $subtotal - $discount_amount;
        $ppn_amount = $after_discount * 0.11; // 11% PPN
        $grand_total = $after_discount + $ppn_amount;
        
        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount_amount,
            'ppn_amount' => $ppn_amount,
            'grand_total' => $grand_total
        ];
    }

    /**
     * Insert sale header
     */
    private function insertSaleHeader($data, $invoice_no, $totals) {
        $sql = "INSERT INTO sale SET
                sale_date = ?,
                sale_invoice = ?,
                cust_id = ?,
                sale_total = ?,
                sale_credit = ?,
                sale_disc = ?,
                bayar_id = ?,
                sale_jt = ?,
                ppn = ?,
                trans_harga = ?,
                sales_id = ?,
                discount_perc = ?,
                catatan = ?,
                bayar = ?,
                status = ?,
                user_id = ?,
                date_created = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        
        $credit = $data['bayar_id'] == 2 ? $totals['grand_total'] : 0;
        $status = $data['bayar_id'] == 1 ? 1 : 0;
        $user_id = $_SESSION['user_id'] ?? 1;
        $sales_id = !empty($data['sales_id']) ? $data['sales_id'] : NULL;
        
        $stmt->bind_param(
            "ssidddisiisdidiii",
            $data['sale_date'],
            $invoice_no,
            $data['customer_id'],
            $totals['grand_total'],
            $credit,
            $totals['discount_amount'],
            $data['bayar_id'],
            $data['sale_jt'],
            $totals['ppn_amount'],
            $data['trans_harga'],
            $sales_id,
            $data['discount_perc'] ?? 0,
            $data['catatan'] ?? '',
            $data['bayar'] ?? 0,
            $status,
            $user_id
        );
        
        $stmt->execute();
        return $this->conn->insert_id;
    }

    /**
     * Insert sale details
     */
    private function insertSaleDetails($sale_id, $items) {
        $sql = "INSERT INTO sale_det SET
                sale_id = ?,
                produk_id = ?,
                sale_qty = ?,
                sale_harga = ?,
                sale_pdisc = ?,
                satuan_id = ?,
                sale_capital = ?,
                ppn = ?,
                date_created = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($items as $item) {
            // Get product info for capital price and unit
            $product = $this->getProductInfo($item['id']);
            
            if (!$product) {
                throw new Exception("Produk dengan ID {$item['id']} tidak ditemukan");
            }

            // Check stock
            if ($product['stok'] < $item['quantity']) {
                throw new Exception("Stok produk {$product['nama_produk']} tidak mencukupi");
            }
            
            $stmt->bind_param(
                "iidddidi",
                $sale_id,
                $item['id'],
                $item['quantity'],
                $item['price'],
                $item['discount'] ?? 0,
                $product['id_satuan'],
                $product['harga_beli'],
                $product['is_ppn']
            );
            
            $stmt->execute();
        }
    }

    /**
     * Get product information
     */
    private function getProductInfo($product_id) {
        $sql = "SELECT id_satuan, harga_beli, is_ppn, stok, nama_produk FROM produk WHERE id = ? AND status = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Update product stock
     */
    private function updateProductStock($items) {
        $sql = "UPDATE produk SET stok = stok - ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        foreach ($items as $item) {
            $stmt->bind_param("di", $item['quantity'], $item['id']);
            $stmt->execute();
        }
    }

    /**
     * Insert transaction record
     */
    private function insertTransactionRecord($sale_id, $data, $totals, $invoice_no) {
        $sql = "INSERT INTO transaksi SET
                trans_date = ?,
                invoice_no = ?,
                client_id = ?,
                jenis_id = 1, -- Penjualan
                trans_total = ?,
                mode_id = ?,
                type_id = 1, -- Pendapatan
                sale_id = ?,
                user_id = ?,
                post = 0";
        
        $stmt = $this->conn->prepare($sql);
        $user_id = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param(
            "ssidii",
            $data['sale_date'],
            $invoice_no,
            $data['customer_id'],
            $totals['grand_total'],
            $data['bayar_id'],
            $sale_id,
            $user_id
        );
        
        $stmt->execute();
    }

    /**
     * Process member points
     */
    private function processMemberPoints($customer_id, $sale_id, $grand_total) {
        if ((int)$customer_id > 0) {
            require_once(__DIR__ . '/RewardSystem.php');
            $rewardSystem = new RewardSystem($this->conn, false);
            $rewardSystem->syncEarnPointsForTransaction((int)$customer_id, (int)$sale_id, (float)$grand_total);
        }
    }

    /**
     * Validate CSRF token
     */
    // private function validateCSRF() {
    //     if (isset($_SESSION['csrf_token']) && isset($_POST['csrf_token'])) {
    //         return $_SESSION['csrf_token'] === $_POST['csrf_token'];
    //     }
    //     return false;
    // }

    /**
     * Log activity
     */
    private function logActivity($aksi, $tabel, $record_id, $keterangan) {
        $sql = "INSERT INTO log_aktivitas SET
                user_id = ?,
                aksi = ?,
                tabel = ?,
                record_id = ?,
                keterangan = ?,
                ip_address = ?,
                user_agent = ?,
                created_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param(
            "issssss",
            $user_id,
            $aksi,
            $tabel,
            $record_id,
            $keterangan,
            $ip_address,
            $user_agent
        );
        
        $stmt->execute();
    }

    /**
     * Get products for sale form
     */
    public function get_products() {
        // Validasi session saja
        if (!isset($_SESSION['user_id'])) {
            return ['status' => 'error', 'message' => 'Unauthorized access'];
        }

        $sql = "SELECT p.*, k.nama_kategori, s.nama_satuan 
                FROM produk p 
                LEFT JOIN kategori k ON p.id_kategori = k.id 
                LEFT JOIN satuan s ON p.id_satuan = s.id 
                WHERE p.status = 1 
                ORDER BY p.nama_produk";
        
        $result = $this->conn->query($sql);
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        return [
            'status' => 'success',
            'data' => $products
        ];
    }
}

// Handle AJAX requests
if (isset($_GET['f']) && is_direct_script(__FILE__)) {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    $transaksi = new Transaksi();
    $function = request_action('none');
    
    // Initialize CSRF token if not exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    switch ($function) {
        case 'dt_penjualan':
            echo $transaksi->dt_penjualan();
            break;
            
        case 'get_sale_detail':
            $saleid = isset($_POST['saleid']) ? intval($_POST['saleid']) : 0;
            echo json_encode($transaksi->get_sale_detail($saleid));
            break;
            
        case 'delete_penjualan':
            http_response_code(410);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint transaksi lama sudah dinonaktifkan. Gunakan endpoint Penjualan yang aktif.'
            ]);
            break;

        case 'batal_penjualan':
            http_response_code(410);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint transaksi lama sudah dinonaktifkan. Gunakan endpoint Penjualan yang aktif.'
            ]);
            break;

        case 'process_sale':
            http_response_code(410);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint transaksi lama sudah dinonaktifkan. Gunakan Penjualan atau Penjualan Kasir.'
            ]);
            break;
            
        case 'get_products':
            echo json_encode($transaksi->get_products());
            break;

        case 'get_csrf_token':
            // Endpoint untuk mendapatkan CSRF token
            echo json_encode([
                'status' => 'success',
                'csrf_token' => $_SESSION['csrf_token']
            ]);
            break;
                        
        default:
            echo json_encode(['status' => 'error', 'message' => 'Function not found']);
    }
}
?>
