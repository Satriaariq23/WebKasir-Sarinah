<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/ajax_kasir.php
 *  DESCRIPTION: AJAX Handler untuk Penjualan Kasir
 *  AUTHOR: System
 * ==========================================================
 */

require_once('../../config.php');

require_admin_auth([1, 2], true);
require_csrf_for_unsafe_request(true);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$action = request_action('none');

function kasir_ppn_rate()
{
    global $_settings;

    $rate = isset($_settings) ? $_settings->info('ppn_rate') : false;
    if ($rate === false || $rate === '' || !is_numeric($rate)) {
        $rate = 11;
    }

    return max(0, min(100, (float)$rate)) / 100;
}

function kasir_price_with_item_ppn($price, $isPpn)
{
    $price = (float)$price;
    return ((int)$isPpn === 1) ? round($price * (1 + kasir_ppn_rate())) : $price;
}

function kasir_ppn_from_gross_amount($grossAmount, $isPpn)
{
    $grossAmount = (float)$grossAmount;
    if ((int)$isPpn !== 1 || $grossAmount == 0.0) {
        return 0.0;
    }

    return round($grossAmount - ($grossAmount / (1 + kasir_ppn_rate())));
}

switch ($action) {
    case 'get_outlets':
        getOutlets();
        break;
    case 'get_sales':
        getSales();
        break;
    case 'get_products':
        getProducts();
        break;
    case 'get_customers':
        getCustomers();
        break;
    case 'search_customers':
        searchCustomers();
        break;
    case 'save_sale':
    case 'delete_sale':
        http_response_code(410);
        echo json_encode([
            'status' => 'error',
            'msg' => 'Endpoint transaksi lama dinonaktifkan. Gunakan alur PenjualanKasir agar stok, piutang, dan poin tetap konsisten.'
        ]);
        break;
    default:
        echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
}

function safeDate($value, $fallback)
{
    $date = DateTime::createFromFormat('Y-m-d', (string)$value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function normalizedKasirPriceExpr($detailAlias, $productAlias, $detailTotalAlias, $saleAlias, $detailColumn, $productColumn)
{
    return "(CASE
                WHEN {$saleAlias}.sale_total > 0
                     AND COALESCE({$detailTotalAlias}.detail_total, 0) >= ({$saleAlias}.sale_total * 50)
                    THEN {$detailAlias}.{$detailColumn} / 100
                WHEN COALESCE({$productAlias}.{$productColumn}, 0) > 0
                     AND {$detailAlias}.{$detailColumn} >= ({$productAlias}.{$productColumn} * 50)
                    THEN {$detailAlias}.{$detailColumn} / 100
                ELSE {$detailAlias}.{$detailColumn}
            END)";
}

/**
 * Get list of outlets/stores
 */
function getOutlets()
{
    global $db;

    try {
        // You can customize this query based on your database structure
        $data = [
            ['id' => 'sarinah', 'nama' => 'Sarinah Logam Dukuhwringin'],
            ['id' => 'toko1', 'nama' => 'Toko 1'],
            ['id' => 'toko2', 'nama' => 'Toko 2']
        ];

        // If you have outlets table, use this:
        // $query = "SELECT id, nama FROM outlet WHERE status = 1 ORDER BY nama ASC";
        // $result = $db->query($query);
        // $data = [];
        // while($row = $result->fetch_assoc()) {
        //     $data[] = $row;
        // }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
}

/**
 * Get sales transactions from SALE table (Kasir only - KSR prefix)
 */
function getSales()
{
    global $db;

    $periodStart = safeDate($_POST['period_start'] ?? '', date('Y-m-d', strtotime('-3 months')));
    $periodEnd = safeDate($_POST['period_end'] ?? '', date('Y-m-d'));
    $filterOutlet = $_POST['filter_outlet'] ?? '';
    $filterBayar = $_POST['filter_bayar'] ?? '';
    $search = trim($_POST['search'] ?? '');
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    try {
        // FILTER: Hanya tampilkan transaksi KASIR (invoice KSR*)
        $whereConditions = [
            "DATE(s.sale_date) BETWEEN ? AND ?",
            "s.sale_invoice LIKE 'KSR%'" // FILTER KASIR
        ];
        $params = [$periodStart, $periodEnd];

        if (!empty($filterBayar)) {
            // bayar_id: 1=tunai/cash, 2=bank
            if ($filterBayar == 'cash' || $filterBayar == 'tunai') {
                $whereConditions[] = "s.bayar_id = 1";
            } elseif ($filterBayar == 'bank') {
                $whereConditions[] = "s.bayar_id = 2";
            }
        }

        if (!empty($search)) {
            $whereConditions[] = "(s.sale_invoice LIKE ? OR p.nama LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Count total records from sale table (only KSR)
        $countQuery = "SELECT COUNT(*) as total
                       FROM sale s
                       LEFT JOIN pelanggan p ON s.cust_id = p.id
                       WHERE $whereClause";
        $countResult = $db->fetchOne($countQuery, $params);
        $totalRecords = (int)($countResult['total'] ?? 0);
        $totalPages = ceil($totalRecords / $limit);

        $hargaJualExpr = normalizedKasirPriceExpr('sd', 'pr', 'dt', 's', 'sale_harga', 'harga_kasir');
        $hargaModalExpr = normalizedKasirPriceExpr('sd', 'pr', 'dt', 's', 'sale_capital', 'harga_newmodal');

        // Get data from SALE table (only Kasir - KSR*)
        $query = "
            SELECT 
                s.saleid,
                s.sale_invoice,
                s.sale_date,
                s.sale_total,
                CASE 
                    WHEN s.bayar_id = 1 THEN 'CASH'
                    WHEN s.bayar_id = 2 THEN 'BANK'
                    ELSE 'LAINNYA'
                END as bayar_type,
                s.bayar as bayar_total,
                0 as bayar_kembali,
                COALESCE(p.nama, 'Umum') as customer_name,
                'Sarinah Logam' as outlet_name,
                (SELECT COUNT(*) FROM sale_det sd WHERE sd.sale_id = s.saleid) as total_items,
                COALESCE((
                    SELECT SUM(({$hargaJualExpr} - {$hargaModalExpr}) * sd.sale_qty)
                    FROM sale_det sd
                    JOIN produk pr ON pr.id = sd.produk_id
                    LEFT JOIN (
                        SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                        FROM sale_det
                        GROUP BY sale_id
                    ) dt ON dt.sale_id = sd.sale_id
                    WHERE sd.sale_id = s.saleid
                ), 0) as laba
            FROM sale s
            LEFT JOIN pelanggan p ON s.cust_id = p.id
            WHERE $whereClause
            ORDER BY s.sale_date DESC, s.saleid DESC
            LIMIT ? OFFSET ?
        ";

        $queryParams = array_merge($params, [$limit, $offset]);
        $data = $db->fetchAll($query, $queryParams);

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'msg' => $e->getMessage(),
            'data' => [],
            'total_records' => 0,
            'total_pages' => 0
        ]);
    }
}

/**
 * Get products for POS
 */
function getProducts()
{
    global $db;

    $category = trim($_POST['category'] ?? 'all');
    $search = trim($_POST['search'] ?? '');

    try {
        $whereConditions = ["1=1"]; // Remove status check since produk table doesn't have status field
        $params = [];

        if ($category != 'all') {
            $whereConditions[] = "LOWER(k.nama_kategori) = LOWER(?)";
            $params[] = $category;
        }

        if (!empty($search)) {
            $whereConditions[] = "(p.nama_produk LIKE ? OR p.barcode LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "
            SELECT 
                p.id,
                p.barcode as code,
                p.nama_produk as nama,
                p.harga_kasir as harga_jual,
                p.P2,
                p.mqty2,
                p.P3,
                p.mqty3,
                p.P4,
                p.mqty4,
                p.stok,
                p.is_ppn,
                COALESCE(k.nama_kategori, 'Uncategorized') as kategori,
                COALESCE(s.nama_satuan, 'pcs') as satuan
            FROM produk p
            LEFT JOIN kategori k ON p.id_kategori = k.id
            LEFT JOIN satuan s ON p.id_satuan = s.id
            WHERE $whereClause
            ORDER BY p.nama_produk ASC
            LIMIT 100
        ";

        $data = $db->fetchAll($query, $params);
        foreach ($data as &$row) {
            $row['stok'] = (float)($row['stok'] ?? 0);
            $row['harga_jual_dasar'] = (float)($row['harga_jual'] ?? 0);
            $row['harga_jual'] = kasir_price_with_item_ppn($row['harga_jual_dasar'], $row['is_ppn'] ?? 0);
        }
        unset($row);

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage(), 'data' => []]);
    }
}

/**
 * Get customers
 */
function getCustomers()
{
    global $db;

    try {
        $query = "
            SELECT id, nama, telpon, alamat
            FROM pelanggan
            ORDER BY nama ASC
            LIMIT 100
        ";

        $result = $db->query($query);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage(), 'data' => []]);
    }
}

/**
 * Search customers for Select2 AJAX
 */
function searchCustomers()
{
    global $db;

    try {
        $searchTerm = $_GET['q'] ?? '';
        $data = [];

        // Only search if term is at least 2 characters (same as search produk)
        if (strlen(trim($searchTerm)) < 2) {
            // Return empty array if search term less than 2 characters
            echo json_encode(['status' => 'success', 'data' => $data]);
            return;
        }

        // Search by name or phone
        $searchTerm = '%' . $searchTerm . '%';
        $query = "
            SELECT 
                p.id, 
                p.nama, 
                p.telpon, 
                p.alamat,
                COALESCE(vcp.total_points, 0) as total_poin
            FROM pelanggan p
            LEFT JOIN v_customer_active_points vcp ON p.id = vcp.pelanggan_id
            WHERE (p.nama LIKE ? OR p.telpon LIKE ?) AND p.status = '1'
            ORDER BY p.nama ASC
            LIMIT 50
        ";
        $stmt = $db->prepare($query);
        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage(), 'data' => []]);
    }
}

/**
 * Save kasir transaction (separate from sales)
 */
function saveSale()
{
    global $db, $_settings;

    try {
        $rawInput = file_get_contents('php://input');

        $input = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        $customerIdInput = $input['customer_id'] ?? null;
        $customerId = (is_numeric($customerIdInput) && (int)$customerIdInput > 0)
            ? (int)$customerIdInput
            : null;
        $paymentMethod = in_array(($input['payment_method'] ?? 'tunai'), ['tunai', 'bank'], true)
            ? $input['payment_method']
            : 'tunai';
        $paymentAmount = floatval($input['payment_amount'] ?? 0);
        $note = substr(trim((string)($input['note'] ?? '')), 0, 500);
        $cart = $input['cart'] ?? [];
        $total = floatval($input['total'] ?? 0);
        $discount = floatval($input['discount'] ?? 0);
        $ppnTotal = 0.0;

        if (empty($cart)) {
            throw new Exception('Keranjang kosong');
        }

        $cartGross = 0.0;
        foreach ($cart as $item) {
            $cartGross += (float)($item['qty'] ?? 0) * (float)($item['price'] ?? 0);
        }

        foreach ($cart as $item) {
            $lineGross = (float)($item['qty'] ?? 0) * (float)($item['price'] ?? 0);
            $lineAfterDiscount = $lineGross;
            if ($discount > 0 && abs($cartGross) > 0.00001) {
                $lineAfterDiscount -= $discount * ($lineGross / $cartGross);
            }
            $ppnTotal += kasir_ppn_from_gross_amount(
                $lineAfterDiscount,
                (int)($item['is_ppn'] ?? 0)
            );
        }

        // Generate invoice number with KSR prefix
        $year = date('Y');
        $month = date('m');

        // Get last transaction number for this month from SALE table
        $query = "SELECT sale_invoice FROM sale 
                  WHERE sale_invoice LIKE 'KSR-$year-$month-%' 
                  ORDER BY sale_invoice DESC LIMIT 1";
        $result = $db->query($query);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastNumber = intval(substr($row['sale_invoice'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $invoiceNumber = "KSR-$year-$month-$newNumber";

        // Calculate subtotal (total before discount)
        $subtotal = $total + $discount;

        // Calculate change
        $change = $paymentMethod == 'tunai' ? ($paymentAmount - $total) : 0;

        // Convert payment method to bayar_id: 1=tunai, 2=bank
        $bayarId = ($paymentMethod == 'tunai') ? 1 : 2;

        // Get user info from session
        $userId = $_settings->userdata('id') ?? 1;
        $userName = $_settings->userdata('name') ?? 'Administrator';

        // Insert into SALE table (not kasir). Customer "Umum" is stored as NULL.
        if ($customerId === null) {
            $stmt = $db->conn->prepare("
                INSERT INTO sale (
                    sale_invoice, sale_date, sale_channel, cust_id, sale_total, sale_disc,
                    bayar_id, bayar, sale_ppn, user_id, date_created
                ) VALUES (?, NOW(), 'kasir', NULL, ?, ?, ?, ?, ?, ?, NOW())
            ");
        } else {
            $stmt = $db->conn->prepare("
                INSERT INTO sale (
                    sale_invoice, sale_date, sale_channel, cust_id, sale_total, sale_disc,
                    bayar_id, bayar, sale_ppn, user_id, date_created
                ) VALUES (?, NOW(), 'kasir', ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
        }

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->conn->error);
        }

        if ($customerId === null) {
            $stmt->bind_param(
                "sddiddi",
                $invoiceNumber,      // sale_invoice (KSR-2025-10-0001)
                $total,              // sale_total
                $discount,           // sale_disc
                $bayarId,            // bayar_id (1=CASH, 2=BANK)
                $paymentAmount,      // bayar
                $ppnTotal,           // sale_ppn
                $userId              // user_id
            );
        } else {
            $stmt->bind_param(
                "sidddidi",
                $invoiceNumber,      // sale_invoice (KSR-2025-10-0001)
                $customerId,         // cust_id
                $total,              // sale_total
                $discount,           // sale_disc
                $bayarId,            // bayar_id (1=CASH, 2=BANK)
                $paymentAmount,      // bayar
                $ppnTotal,           // sale_ppn
                $userId              // user_id
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $saleId = $db->conn->insert_id;
        $stmt->close();

        // Insert sale details into SALE_DET table (not kasir_det)
        $stmt = $db->conn->prepare("
            INSERT INTO sale_det (
                sale_id, produk_id, sale_qty, sale_harga, sale_capital,
                sale_purchase_cost, sale_pdisc, ppn_item, is_ppn, isi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed (detail): " . $db->conn->error);
        }

        $productCostStmt = $db->conn->prepare("
            SELECT harga_newmodal, harga_beli
            FROM produk
            WHERE id = ? AND status = 1
            LIMIT 1
        ");
        if (!$productCostStmt) {
            throw new Exception("Prepare failed (harga modal): " . $db->conn->error);
        }

        foreach ($cart as $item) {
            $produkId = (int)($item['id'] ?? 0);
            $qty = (float)($item['qty'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            $isPpn = (int)($item['is_ppn'] ?? 0);
            $isi = max(1, (int)($item['isi'] ?? 1));

            if ($produkId <= 0 || $qty <= 0 || $price < 0) {
                throw new Exception('Item keranjang tidak valid');
            }

            $productCostStmt->bind_param("i", $produkId);
            $productCostStmt->execute();
            $productCost = $productCostStmt->get_result()->fetch_assoc();
            if (!$productCost) {
                throw new Exception("Produk ID {$produkId} tidak ditemukan atau nonaktif");
            }

            $saleCapital = (float)($productCost['harga_newmodal'] ?? 0);
            $salePurchaseCost = (float)($productCost['harga_beli'] ?? 0);
            $lineGross = $qty * $price;
            $lineAfterDiscount = $lineGross;
            if ($discount > 0 && abs($cartGross) > 0.00001) {
                $lineAfterDiscount -= $discount * ($lineGross / $cartGross);
            }
            $ppnAmount = kasir_ppn_from_gross_amount($lineAfterDiscount, $isPpn);

            $saleDiscount = 0; // Diskon per item (default 0)

            $stmt->bind_param(
                "iiddddddii",
                $saleId,          // sale_id (dari INSERT sale sebelumnya)
                $produkId,        // produk_id
                $qty,             // sale_qty
                $price,           // sale_harga
                $saleCapital,     // sale_capital (New Modal)
                $salePurchaseCost,// sale_purchase_cost (harga beli)
                $saleDiscount,    // sale_pdisc (diskon per item)
                $ppnAmount,       // ppn_item
                $isPpn,           // snapshot status PPN barang
                $isi              // isi kemasan
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed (detail): " . $stmt->error);
            }

            // Stock will be updated by trigger (if exists)
        }

        $productCostStmt->close();
        $stmt->close();

        // INSERT MEMBER POINT (jika customer adalah member)
        if ($customerId > 0) {
            // Cek apakah customer adalah member
            $memberData = $db->fetchOne("SELECT type_customer FROM pelanggan WHERE id = ?", [$customerId]);
            if ($memberData) {
                if ($memberData['type_customer'] == 'MEMBER') {
                    // Hitung poin: 1 poin per 1000 rupiah
                    $poin = floor($total / 1000);

                    if ($poin > 0) {
                        // Insert member point
                        $stmtPoint = $db->conn->prepare("
                            INSERT INTO member_point (pelanggan_id, transaksi_id, poin, created_at)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmtPoint->bind_param("iii", $customerId, $saleId, $poin);
                        $stmtPoint->execute();
                        $stmtPoint->close();
                    }
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'msg' => 'Transaksi berhasil disimpan',
            'sale_id' => $saleId,
            'invoice' => $invoiceNumber,
            'change' => $change
        ]);
    } catch (Exception $e) {
        error_log("Error in saveSale: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'status' => 'error',
            'msg' => 'Transaksi gagal diproses. Silakan coba lagi.'
        ]);
    }
}

/**
 * Delete kasir transaction
 */
function deleteSale()
{
    global $db;

    try {
        $saleId = intval($_POST['saleid'] ?? 0);

        if ($saleId <= 0) {
            throw new Exception('Invalid sale ID');
        }

        // Verify it's a KSR transaction before deleting
        $checkResult = $db->fetchOne(
            "SELECT sale_invoice FROM sale WHERE saleid = ? AND sale_invoice LIKE 'KSR%'",
            [$saleId]
        );

        if (!$checkResult) {
            throw new Exception('Transaksi kasir tidak ditemukan');
        }

        // Delete sale details from SALE_DET (stock will be restored by trigger if exists)
        $stmt = $db->execute("DELETE FROM sale_det WHERE sale_id = ?", [$saleId]);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        // Delete sale transaction from SALE
        $stmt = $db->execute("DELETE FROM sale WHERE saleid = ?", [$saleId]);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        echo json_encode(['status' => 'success', 'msg' => 'Transaksi berhasil dihapus']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
}
