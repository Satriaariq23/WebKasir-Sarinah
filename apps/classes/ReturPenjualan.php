<?php
require_once('../config.php');
require_once('../classes/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

/**
 * ==========================================================
 *  FILE: classes/ReturPenjualan.php
 *  DESCRIPTION: Modul untuk Retur Penjualan
 * ==========================================================
 */
class ReturPenjualan extends DBConnection
{
    private $settings;
    private $error = null;

    public function __construct()
    {
        global $_settings;
        $this->settings = $_settings ?? null;
        parent::__construct();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** ======================= [ ENSURE DATABASE CONNECTION ] ======================= **/
    private function ensureConnection()
    {
        if (!$this->conn || $this->conn->connect_error) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $this->conn = $conn;
            } else {
                require_once '../config.php';
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

    private function normalizeSaleDetailPrices($detail, $saleTotal = 0)
    {
        $detailTotal = 0.0;
        foreach ($detail as $item) {
            $detailTotal += (float)($item['sale_qty'] ?? 0) * (float)($item['sale_harga'] ?? 0);
        }

        foreach ($detail as &$item) {
            $salePriceMaster = (float)($item['harga_kasir'] ?? 0);
            if ($salePriceMaster <= 0) {
                $salePriceMaster = (float)($item['harga_sales'] ?? 0);
            }

            $item['sale_harga_raw'] = $item['sale_harga'] ?? 0;
            $item['sale_capital_raw'] = $item['sale_capital'] ?? 0;
            $item['sale_harga'] = $this->normalizeLegacyUnitPrice($item['sale_harga'] ?? 0, $salePriceMaster, $detailTotal, $saleTotal);
            $item['sale_capital'] = $this->normalizeLegacyUnitPrice($item['sale_capital'] ?? 0, $item['harga_newmodal'] ?? 0, $detailTotal, $saleTotal);
        }
        unset($item);

        return $detail;
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

    /**
     * Bentuk detail dan nilai retur hanya dari snapshot transaksi asal.
     * Browser hanya berwenang mengirim produk, qty, dan kondisi barang.
     */
    private function prepareSaleReturnData($saleId, $currentReturnId, array $requestedProducts, array $sale)
    {
        $requests = [];
        foreach ($requestedProducts as $product) {
            $productId = $this->sanitizeInt($product['produk_id'] ?? 0);
            $qty = $this->sanitizeQuantity($product['retur_qty'] ?? 0);
            $condition = $this->sanitizeInt($product['retur_kondisi'] ?? 1);
            // Form mengirim semua baris detail penjualan. Baris dengan qty 0
            // bukan retur dan harus diabaikan, bukan dianggap data tidak valid.
            if ($qty <= 0) {
                continue;
            }
            if ($productId <= 0) {
                throw new Exception('Produk retur tidak valid');
            }
            if ($condition <= 0) {
                throw new Exception('Kondisi barang retur tidak valid');
            }
            if (isset($requests[$productId]) && $requests[$productId]['condition'] !== $condition) {
                throw new Exception("Kondisi retur produk #{$productId} tidak boleh berbeda dalam satu retur");
            }
            if (!isset($requests[$productId])) {
                $requests[$productId] = ['qty' => 0.0, 'condition' => $condition];
            }
            $requests[$productId]['qty'] += $qty;
        }
        if (!$requests) {
            throw new Exception('Minimal harus ada 1 produk yang diretur');
        }

        $detailStmt = $this->prepare(
            "SELECT sd.*, p.harga_kasir, p.harga_sales, p.harga_newmodal
               FROM sale_det sd
               LEFT JOIN produk p ON p.id = sd.produk_id
              WHERE sd.sale_id = ?
              ORDER BY sd.noid
              FOR UPDATE"
        );
        $detailStmt->bind_param('i', $saleId);
        $detailStmt->execute();
        $saleDetails = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $detailStmt->close();
        if (!$saleDetails) {
            throw new Exception('Detail transaksi asal tidak ditemukan');
        }
        $saleDetails = $this->normalizeSaleDetailPrices($saleDetails, $sale['sale_total'] ?? 0);

        $invoiceBase = 0.0;
        $rawPpnTotal = 0.0;
        $taxableBase = 0.0;
        foreach ($saleDetails as $index => &$detail) {
            $soldQty = (float)($detail['sale_qty'] ?? 0);
            if ($soldQty <= 0) {
                $detail['_line_net'] = 0.0;
                continue;
            }
            $lineBase = $soldQty * (float)($detail['sale_harga'] ?? 0);
            $itemDiscount = (float)($detail['sale_pdisc'] ?? 0);
            if ($itemDiscount < 0 || $itemDiscount > $lineBase + 0.01) {
                throw new Exception('Diskon detail transaksi asal tidak konsisten; retur tidak dapat dihitung otomatis');
            }
            $lineNet = max($lineBase - $itemDiscount, 0);
            $detail['_line_net'] = $lineNet;
            $invoiceBase += $lineNet;
            $rawPpnTotal += max((float)($detail['ppn_item'] ?? 0), 0);
            if ((int)($detail['is_ppn'] ?? 0) === 1) {
                $taxableBase += $lineNet;
            }
        }
        unset($detail);

        $saleTotal = (float)($sale['sale_total'] ?? 0);
        if ($invoiceBase <= 0 || $saleTotal <= 0) {
            throw new Exception('Nilai transaksi asal tidak valid untuk diretur');
        }
        // Faktor ini juga mengakomodasi diskon faktur dan transaksi lama yang PPN-nya eksklusif.
        $invoiceFactor = $saleTotal / $invoiceBase;
        $headerPpn = max((float)($sale['sale_ppn'] ?? $sale['ppn'] ?? 0), 0);
        $salePpnTotal = $headerPpn > 0 ? $headerPpn : $rawPpnTotal;

        foreach ($saleDetails as &$detail) {
            $soldQty = (float)($detail['sale_qty'] ?? 0);
            $detail['_net_unit'] = $soldQty > 0
                ? (($detail['_line_net'] * $invoiceFactor) / $soldQty)
                : 0.0;
            if ($soldQty <= 0 || (int)($detail['is_ppn'] ?? 0) !== 1) {
                $detail['_ppn_unit'] = 0.0;
            } elseif ($rawPpnTotal > 0) {
                $detail['_ppn_unit'] = ((float)($detail['ppn_item'] ?? 0) * ($headerPpn > 0 ? $headerPpn / $rawPpnTotal : 1)) / $soldQty;
            } elseif ($headerPpn > 0 && $taxableBase > 0) {
                $detail['_ppn_unit'] = ($headerPpn * ($detail['_line_net'] / $taxableBase)) / $soldQty;
            } else {
                $detail['_ppn_unit'] = 0.0;
            }
        }
        unset($detail);

        $returnedStmt = $this->prepare(
            "SELECT srd.produk_id, COALESCE(SUM(srd.retur_qty), 0) AS qty_returned
               FROM sale_retur_det srd
               INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
              WHERE sr.saleid = ? AND sr.returid <> ?
              GROUP BY srd.produk_id"
        );
        $returnedStmt->bind_param('ii', $saleId, $currentReturnId);
        $returnedStmt->execute();
        $returnedRows = $returnedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $returnedStmt->close();
        $alreadyReturned = [];
        foreach ($returnedRows as $row) {
            $alreadyReturned[(int)$row['produk_id']] = (float)$row['qty_returned'];
        }

        $preparedItems = [];
        $returnTotal = 0.0;
        $returnPpn = 0.0;
        foreach ($requests as $productId => $request) {
            $rows = array_values(array_filter($saleDetails, static function ($row) use ($productId) {
                return (int)($row['produk_id'] ?? 0) === (int)$productId && (float)($row['sale_qty'] ?? 0) > 0;
            }));
            if (!$rows) {
                throw new Exception("Produk #{$productId} tidak berasal dari transaksi yang dipilih");
            }

            $soldQty = array_sum(array_map(static function ($row) {
                return (float)$row['sale_qty'];
            }, $rows));
            $previousQty = max((float)($alreadyReturned[$productId] ?? 0), 0);
            $available = max($soldQty - $previousQty, 0);
            if ($request['qty'] > $available + 0.0001) {
                throw new Exception("Qty retur produk #{$productId} melebihi sisa yang dapat diretur ({$available})");
            }

            // Karena skema lama belum menyimpan sale_detail_id, alokasi dilakukan FIFO dan deterministik.
            $skip = $previousQty;
            $needed = $request['qty'];
            foreach ($rows as $row) {
                $rowQty = (float)$row['sale_qty'];
                if ($skip >= $rowQty - 0.000001) {
                    $skip -= $rowQty;
                    continue;
                }
                $rowAvailable = $rowQty - max($skip, 0);
                $skip = 0.0;
                $take = min($needed, $rowAvailable);
                if ($take <= 0) {
                    continue;
                }

                $netUnitRaw = (float)$row['_net_unit'];
                $netUnit = round($netUnitRaw, 2);
                $lineTotal = round($take * $netUnitRaw, 2);
                $linePpn = round($take * (float)$row['_ppn_unit'], 2);
                $preparedItems[] = [
                    'produk_id' => (int)$productId,
                    'retur_qty' => $take,
                    'sale_capital' => (float)($row['sale_capital'] ?? 0),
                    'sale_purchase_cost' => (float)($row['sale_purchase_cost'] ?? 0),
                    // Harga retur disimpan net agar laporan detail sama dengan header retur.
                    'sale_harga' => $netUnit,
                    'sale_pdisc' => 0.0,
                    'retur_kondisi' => $request['condition'],
                    'ppn' => (int)($row['is_ppn'] ?? 0),
                    'is_ppn' => (int)($row['is_ppn'] ?? 0),
                    'isi' => max(1, (int)($row['isi'] ?? 1)),
                ];
                $returnTotal += $lineTotal;
                $returnPpn += $linePpn;
                $needed -= $take;
                if ($needed <= 0.000001) {
                    break;
                }
            }
            if ($needed > 0.0001) {
                throw new Exception("Alokasi qty retur produk #{$productId} tidak dapat diselesaikan");
            }
        }

        $returnTotal = round($returnTotal, 2);
        $existingValueStmt = $this->prepare(
            "SELECT COALESCE(SUM(retur_total), 0) AS returned_value
               FROM sale_retur
              WHERE saleid = ? AND returid <> ?"
        );
        $existingValueStmt->bind_param('ii', $saleId, $currentReturnId);
        $existingValueStmt->execute();
        $existingReturnValue = (float)($existingValueStmt->get_result()->fetch_assoc()['returned_value'] ?? 0);
        $existingValueStmt->close();
        $remainingTransactionValue = round($saleTotal - $existingReturnValue, 2);
        if ($remainingTransactionValue <= 0) {
            throw new Exception('Seluruh nilai transaksi sudah diretur');
        }
        if ($returnTotal > $remainingTransactionValue + 1.0) {
            throw new Exception('Nilai retur terhitung melebihi sisa nilai transaksi asal');
        }
        // Serap selisih pembulatan sen pada retur terakhir tanpa mengubah data historis.
        $returnTotal = min($returnTotal, $remainingTransactionValue);
        if ($returnTotal <= 0) {
            throw new Exception('Total retur hasil perhitungan server harus lebih dari 0');
        }

        $returnPpn = round($returnPpn, 2);
        $existingPpnStmt = $this->prepare(
            "SELECT COALESCE(SUM(retur_ppn), 0) AS returned_ppn
               FROM sale_retur
              WHERE saleid = ? AND returid <> ?"
        );
        $existingPpnStmt->bind_param('ii', $saleId, $currentReturnId);
        $existingPpnStmt->execute();
        $existingReturnPpn = (float)($existingPpnStmt->get_result()->fetch_assoc()['returned_ppn'] ?? 0);
        $existingPpnStmt->close();
        $returnPpn = min($returnPpn, max(round($salePpnTotal - $existingReturnPpn, 2), 0));

        return [
            'items' => $preparedItems,
            'total' => $returnTotal,
            'ppn' => $returnPpn,
            'cust_id' => (int)($sale['cust_id'] ?? 0),
        ];
    }

    /** ======================= [ GET DATA RETUR PENJUALAN ] ======================= **/
    function get_data_retur()
    {
        try {
            $this->ensureConnection();

            $draw   = intval($_POST['draw'] ?? 1);
            $start  = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = trim($_POST['search']['value'] ?? '');

            // Filter
            $tgl_awal = $this->sanitizeDate($_POST['tgl_awal'] ?? date('Y-m-01'));
            $tgl_akhir = $this->sanitizeDate($_POST['tgl_akhir'] ?? date('Y-m-d'));
            $cust_id = $this->sanitizeInt($_POST['cust_id'] ?? 0);

            // Sorting
            $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 0);
            $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc');
            $orderDir = ($orderDir === 'desc') ? 'DESC' : 'ASC';
            $columns = [
                0 => 'sr.returid',
                1 => 'sr.retur_date',
                2 => 'sr.retur_invoice',
                3 => 'p.nama',
                4 => 'sr.retur_total',
                5 => 'sr.post'
            ];
            $orderColumn = $columns[$orderColumnIndex] ?? 'sr.returid';

            // WHERE builder
            $whereParts = [];
            $params = [];
            $types = "";

            // Filter pencarian
            if (!empty($search)) {
                $whereParts[] = "(sr.retur_invoice LIKE ? OR p.nama LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm]);
                $types .= "ss";
            }

            // Filter tanggal
            if (!empty($tgl_awal) && !empty($tgl_akhir)) {
                $whereParts[] = "(sr.retur_date BETWEEN ? AND ?)";
                $params[] = $tgl_awal;
                $params[] = $tgl_akhir;
                $types .= "ss";
            }

            // Filter pelanggan
            if ($cust_id > 0) {
                $whereParts[] = "sr.cust_id = ?";
                $params[] = $cust_id;
                $types .= "i";
            }

            $where = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

            // Total records
            $totalQuery = $this->query("SELECT COUNT(*) AS cnt FROM sale_retur");
            $totalRecords = $totalQuery ? $totalQuery->fetch_assoc()['cnt'] ?? 0 : 0;

            // Filtered records
            $countSql = "SELECT COUNT(*) AS cnt 
                         FROM sale_retur sr 
                         LEFT JOIN pelanggan p ON p.id = sr.cust_id
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

            // Query utama
            $sql = "SELECT 
                        sr.returid,
                        sr.retur_date,
                        sr.retur_invoice,
                        sr.cust_id,
                        sr.retur_total,
                        sr.retur_ppn,
                        sr.post,
                        sr.saleid,
                        p.nama AS nama_pelanggan,
                        p.kode AS kode_pelanggan,
                        s.sale_invoice
                    FROM sale_retur sr
                    LEFT JOIN pelanggan p ON p.id = sr.cust_id
                    LEFT JOIN sale s ON s.saleid = sr.saleid
                    $where
                    ORDER BY $orderColumn $orderDir
                    LIMIT ?, ?";

            $params[] = $start;
            $params[] = $length;
            $types .= "ii";

            $data = [];
            if ($stmt = $this->prepare($sql)) {
                if (!empty($types)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                $no = $start + 1;
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'no' => $no++,
                        'returid' => $row['returid'],
                        'retur_date' => date('d/m/Y', strtotime($row['retur_date'])),
                        'retur_invoice' => htmlspecialchars($row['retur_invoice']),
                        'nama_pelanggan' => htmlspecialchars($row['nama_pelanggan'] ?? 'CASH'),
                        'kode_pelanggan' => htmlspecialchars($row['kode_pelanggan'] ?? '-'),
                        'sale_invoice' => htmlspecialchars($row['sale_invoice'] ?? '-'),
                        'retur_total' => floatval($row['retur_total']),
                        'post' => intval($row['post']),
                        'status_badge' => $row['post'] == 1 ? '<span class="badge bg-success">Posted</span>' : '<span class="badge bg-warning">Draft</span>'
                    ];
                }
                $stmt->close();
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
            exit;
        } catch (Exception $e) {
            error_log("[ReturPenjualan.php] " . $e->getMessage());
            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /** ======================= [ GET PENJUALAN FOR SELECT2 ] ======================= **/
    function get_penjualan_list()
    {
        try {
            $this->ensureConnection();

            $search = trim($_GET['q'] ?? '');
            $page = intval($_GET['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;

            // Retur Penjualan ini khusus untuk transaksi dari kanal Sales.
            // Tanpa filter ini transaksi Kasir ikut muncul pada dropdown Select2.
            $whereParts = [
                "s.sale_status IN ('ACTIVE', 'LUNAS')",
                "s.sale_channel = 'sales'"
            ];
            $params = [];
            $types = "";

            if (!empty($search)) {
                $whereParts[] = "(s.sale_invoice LIKE ? OR p.nama LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm]);
                $types .= "ss";
            }

            $where = " WHERE " . implode(" AND ", $whereParts);

            $sql = "SELECT s.saleid, s.sale_invoice, s.sale_date, s.cust_id, s.sale_total, p.nama AS nama_pelanggan
                    FROM sale s
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    $where
                    ORDER BY s.sale_date DESC
                    LIMIT ?, ?";

            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";

            $results = [];
            if ($stmt = $this->prepare($sql)) {
                if (!empty($types)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $results[] = [
                        'id' => $row['saleid'],
                        'text' => $row['sale_invoice'] . ' - ' . ($row['nama_pelanggan'] ?? 'CASH') . ' (' . date('d/m/Y', strtotime($row['sale_date'])) . ')',
                        'sale_invoice' => $row['sale_invoice'],
                        'sale_date' => $row['sale_date'],
                        'cust_id' => $row['cust_id'],
                        'nama_pelanggan' => $row['nama_pelanggan'] ?? 'CASH',
                        'sale_total' => $row['sale_total']
                    ];
                }
                $stmt->close();
            }

            echo json_encode([
                'results' => $results,
                'pagination' => ['more' => count($results) >= $limit]
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ GET DETAIL PENJUALAN ] ======================= **/
    function get_penjualan_detail()
    {
        try {
            $this->ensureConnection();

            $sale_id = $this->sanitizeInt($_GET['sale_id'] ?? 0);
            if ($sale_id <= 0) {
                throw new Exception("ID Penjualan tidak valid");
            }

            // Get header
            $sql = "SELECT s.*, p.nama AS nama_pelanggan, p.kode AS kode_pelanggan
                    FROM sale s
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    WHERE s.saleid = ?
                      AND s.sale_status IN ('ACTIVE', 'LUNAS')
                      AND s.sale_channel = 'sales'";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$header) {
                throw new Exception("Data penjualan tidak ditemukan");
            }

            // Get detail
            $sql = "SELECT sd.*, COALESCE(sd.is_ppn, pr.is_ppn, 0) AS is_ppn,
                           pr.nama_produk, pr.barcode, pr.harga_beli, pr.harga_kasir, pr.harga_sales, pr.harga_newmodal, sat.nama_satuan
                    FROM sale_det sd
                    LEFT JOIN produk pr ON pr.id = sd.produk_id
                    LEFT JOIN satuan sat ON sat.id = pr.id_satuan
                    WHERE sd.sale_id = ?";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $detail = $this->normalizeSaleDetailPrices($detail, $header['sale_total'] ?? 0);

            // Get sudah di-retur
            $sql = "SELECT srd.produk_id, SUM(srd.retur_qty) AS qty_retur
                    FROM sale_retur_det srd
                    INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                    WHERE sr.saleid = ?
                    GROUP BY srd.produk_id";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $returData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $returMap = [];
            foreach ($returData as $r) {
                $returMap[$r['produk_id']] = floatval($r['qty_retur']);
            }

            // Combine detail with retur info
            foreach ($detail as &$d) {
                $d['qty_sudah_retur'] = $returMap[$d['produk_id']] ?? 0;
                $d['qty_sisa'] = floatval($d['sale_qty']) - ($returMap[$d['produk_id']] ?? 0);
            }

            echo json_encode([
                'status' => 'success',
                'header' => $header,
                'detail' => $detail
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ GENERATE NO RETUR ] ======================= **/
    function generate_retur_no()
    {
        try {
            $this->ensureConnection();

            $prefix = "RTR-" . date('Ymd') . "-";

            $sql = "SELECT retur_invoice FROM sale_retur WHERE retur_invoice LIKE ? ORDER BY returid DESC LIMIT 1";
            $stmt = $this->prepare($sql);
            $searchPrefix = $prefix . '%';
            $stmt->bind_param("s", $searchPrefix);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result) {
                $lastNo = intval(substr($result['retur_invoice'], -4));
                $newNo = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNo = $prefix . "0001";
            }

            echo json_encode(['status' => 'success', 'retur_no' => $newNo]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ SAVE RETUR PENJUALAN ] ======================= **/
    function save_retur()
    {
        try {
            $this->ensureConnection();
            $this->conn->begin_transaction();

            $returid = $this->sanitizeInt($_POST['returid'] ?? 0);
            $retur_date = $this->sanitizeDate($_POST['retur_date'] ?? date('Y-m-d'));
            $saleid = $this->sanitizeInt($_POST['saleid'] ?? 0);
            $cust_id = 0;
            $retur_total = 0.0;
            $retur_ppn = 0.0;
            $catatan = $this->sanitizeString($_POST['catatan'] ?? '');
            $products = $_POST['products'] ?? [];

            if ($saleid <= 0) {
                throw new Exception("Transaksi penjualan harus dipilih");
            }

            if (empty($products)) {
                throw new Exception("Minimal harus ada 1 produk yang diretur");
            }

            $saleStmt = $this->prepare("SELECT * FROM sale
                                        WHERE saleid = ?
                                          AND sale_status = 'ACTIVE'
                                          AND sale_channel = 'sales'
                                        LIMIT 1 FOR UPDATE");
            $saleStmt->bind_param('i', $saleid);
            $saleStmt->execute();
            $activeSale = $saleStmt->get_result()->fetch_assoc();
            $saleStmt->close();
            if (!$activeSale) {
                throw new Exception('Transaksi penjualan tidak aktif atau tidak ditemukan');
            }

            if ($returid > 0) {
                $returStmt = $this->prepare("SELECT post, saleid FROM sale_retur WHERE returid = ? LIMIT 1 FOR UPDATE");
                $returStmt->bind_param('i', $returid);
                $returStmt->execute();
                $existingRetur = $returStmt->get_result()->fetch_assoc();
                $returStmt->close();
                if (!$existingRetur) {
                    throw new Exception('Data retur tidak ditemukan');
                }
                if ((int)$existingRetur['post'] === 1) {
                    throw new Exception('Retur yang sudah diposting tidak dapat diedit');
                }
                if ((int)$existingRetur['saleid'] !== $saleid) {
                    throw new Exception('Transaksi asal retur tidak boleh diganti saat edit');
                }
            }

            $prepared = $this->prepareSaleReturnData($saleid, $returid, $products, $activeSale);
            $products = $prepared['items'];
            $retur_total = $prepared['total'];
            $retur_ppn = $prepared['ppn'];
            $cust_id = $prepared['cust_id'];

            $user_id = (int)($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? $_SESSION['login_id'] ?? 0);
            if ($user_id <= 0) {
                throw new Exception('User tidak terautentikasi');
            }

            if ($returid > 0) {
                // UPDATE
                $sql = "UPDATE sale_retur SET 
                            retur_date = ?, 
                            saleid = ?, 
                            cust_id = ?, 
                            retur_total = ?, 
                            retur_ppn = ?,
                            user_id = ?,
                            updated_at = NOW()
                        WHERE returid = ?";
                $stmt = $this->prepare($sql);
                $stmt->bind_param("siiddii", $retur_date, $saleid, $cust_id, $retur_total, $retur_ppn, $user_id, $returid);
                $stmt->execute();
                $stmt->close();

                // Delete old detail
                $this->query("DELETE FROM sale_retur_det WHERE retur_id = $returid");
            } else {
                // INSERT - Generate no retur
                $prefix = "RTR-" . date('Ymd') . "-";
                $sql = "SELECT retur_invoice FROM sale_retur WHERE retur_invoice LIKE ? ORDER BY returid DESC LIMIT 1";
                $stmt = $this->prepare($sql);
                $searchPrefix = $prefix . '%';
                $stmt->bind_param("s", $searchPrefix);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($result) {
                    $lastNo = intval(substr($result['retur_invoice'], -4));
                    $retur_invoice = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $retur_invoice = $prefix . "0001";
                }

                $sql = "INSERT INTO sale_retur (retur_date, retur_invoice, saleid, cust_id, retur_total, retur_ppn, user_id, post, date_created)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";
                $stmt = $this->prepare($sql);
                $stmt->bind_param("ssiiddi", $retur_date, $retur_invoice, $saleid, $cust_id, $retur_total, $retur_ppn, $user_id);
                $stmt->execute();
                $returid = $this->conn->insert_id;
                $stmt->close();
            }

            // Insert detail
            $sql = "INSERT INTO sale_retur_det (retur_id, produk_id, retur_qty, sale_capital, sale_purchase_cost, sale_harga, sale_pdisc, retur_kondisi, ppn, is_ppn, isi, date_created)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->prepare($sql);

            foreach ($products as $prod) {
                $produk_id = $this->sanitizeInt($prod['produk_id']);
                $retur_qty = $this->sanitizeQuantity($prod['retur_qty']);
                $sale_capital = $this->sanitizeFloat($prod['sale_capital'] ?? 0);
                $sale_purchase_cost = $this->sanitizeFloat($prod['sale_purchase_cost'] ?? 0);
                $sale_harga = $this->sanitizeFloat($prod['sale_harga'] ?? 0);
                $sale_pdisc = $this->sanitizeFloat($prod['sale_pdisc'] ?? 0);
                $retur_kondisi = $this->sanitizeInt($prod['retur_kondisi'] ?? 1);
                $ppn = $this->sanitizeInt($prod['ppn'] ?? 0);
                $is_ppn = $this->sanitizeInt($prod['is_ppn'] ?? $ppn);
                $isi = $this->sanitizeInt($prod['isi'] ?? 1);

                if ($retur_qty > 0) {
                    $stmt->bind_param("iidddddiiii", $returid, $produk_id, $retur_qty, $sale_capital, $sale_purchase_cost, $sale_harga, $sale_pdisc, $retur_kondisi, $ppn, $is_ppn, $isi);
                    $stmt->execute();
                }
            }
            $stmt->close();

            $this->conn->commit();

            echo json_encode([
                'status' => 'success',
                'msg' => 'Retur penjualan berhasil disimpan',
                'returid' => $returid
            ]);
            exit;
        } catch (Exception $e) {
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ POST RETUR ] ======================= **/
    function post_retur()
    {
        try {
            $this->ensureConnection();
            $this->conn->begin_transaction();

            $returid = $this->sanitizeInt($_POST['returid'] ?? 0);
            if ($returid <= 0) {
                throw new Exception("ID Retur tidak valid");
            }

            $headerStmt = $this->prepare("
                SELECT sr.returid, sr.saleid, sr.retur_total, sr.post,
                       s.sale_invoice, s.sale_total, s.cust_id, s.stock_applied
                FROM sale_retur sr
                INNER JOIN sale s ON s.saleid = sr.saleid
                WHERE sr.returid = ?
                LIMIT 1 FOR UPDATE
            ");
            $headerStmt->bind_param('i', $returid);
            $headerStmt->execute();
            $returHeader = $headerStmt->get_result()->fetch_assoc();
            $headerStmt->close();
            if (!$returHeader) {
                throw new Exception('Data retur atau transaksi asal tidak ditemukan');
            }
            if ((int)$returHeader['post'] === 1) {
                throw new Exception('Retur sudah pernah diposting');
            }

            // Get retur detail
            $sql = "SELECT srd.*, sr.saleid 
                    FROM sale_retur_det srd 
                    INNER JOIN sale_retur sr ON sr.returid = srd.retur_id 
                    WHERE srd.retur_id = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Seluruh transaksi sales aktif telah mengurangi stok saat dibuat.
            // Data historis belum selalu memiliki stock_applied = 1, sehingga
            // penanda tersebut tidak boleh menghalangi pengembalian stok retur.
            foreach ($details as $d) {
                if ($d['retur_kondisi'] != 1) {
                    continue;
                }
                $sql = "UPDATE produk SET stok = stok + ? WHERE id = ?";
                $stmt = $this->prepare($sql);
                $stockQty = (float)$d['retur_qty'] * max(1, (int)($d['isi'] ?? 1));
                $stmt->bind_param("di", $stockQty, $d['produk_id']);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new Exception('Gagal mengembalikan stok barang retur');
                }
                $stmt->close();
            }

            // Update status retur
            $sql = "UPDATE sale_retur SET post = 1, updated_at = NOW() WHERE returid = ? AND post = 0";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception('Retur gagal diposting atau sudah diproses pengguna lain');
            }
            $stmt->close();

            $saleId = (int)$returHeader['saleid'];
            $postedStmt = $this->prepare("SELECT COALESCE(SUM(retur_total), 0) AS total_retur FROM sale_retur WHERE saleid = ? AND post = 1");
            $postedStmt->bind_param('i', $saleId);
            $postedStmt->execute();
            $postedReturn = (float)($postedStmt->get_result()->fetch_assoc()['total_retur'] ?? 0);
            $postedStmt->close();
            $netTransaction = max((float)$returHeader['sale_total'] - $postedReturn, 0);

            $customerId = (int)($returHeader['cust_id'] ?? 0);
            if ($customerId > 0) {
                require_once(__DIR__ . '/RewardSystem.php');
                $rewardSystem = new RewardSystem($this->conn, false);
                $rewardSystem->syncEarnPointsForTransaction($customerId, $saleId, $netTransaction);
            }

            $invoice = (string)$returHeader['sale_invoice'];
            $piutangStmt = $this->prepare("SELECT id, total_diterima, tanggal_jatuh_tempo FROM piutang_pelanggan WHERE ref_penjualan = ? LIMIT 1 FOR UPDATE");
            $piutangStmt->bind_param('s', $invoice);
            $piutangStmt->execute();
            $piutang = $piutangStmt->get_result()->fetch_assoc();
            $piutangStmt->close();
            if ($piutang) {
                $received = (float)$piutang['total_diterima'];
                if ($received > $netTransaction) {
                    throw new Exception('Retur tidak dapat diposting karena nilai bersih piutang lebih kecil dari pembayaran yang sudah diterima');
                }
                $remaining = max($netTransaction - $received, 0);
                if ($remaining <= 0) {
                    $status = 'LUNAS';
                } elseif (!empty($piutang['tanggal_jatuh_tempo']) && strtotime($piutang['tanggal_jatuh_tempo']) < strtotime(date('Y-m-d'))) {
                    $status = 'OVERDUE';
                } else {
                    $status = $received > 0 ? 'SEBAGIAN' : 'BELUM';
                }
                $piutangId = (int)$piutang['id'];
                $updatePiutang = $this->prepare("UPDATE piutang_pelanggan SET total_piutang = ?, sisa_piutang = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $updatePiutang->bind_param('ddsi', $netTransaction, $remaining, $status, $piutangId);
                if (!$updatePiutang->execute()) {
                    throw new Exception('Gagal menyesuaikan piutang setelah retur');
                }
                $updatePiutang->close();
            }

            $this->conn->commit();

            echo json_encode(['status' => 'success', 'msg' => 'Retur berhasil diposting']);
            exit;
        } catch (Exception $e) {
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ DELETE RETUR ] ======================= **/
    function delete_retur()
    {
        try {
            $this->ensureConnection();
            $this->conn->begin_transaction();

            $returid = $this->sanitizeInt($_POST['returid'] ?? 0);
            if ($returid <= 0) {
                throw new Exception("ID Retur tidak valid");
            }

            // Check if posted
            $sql = "SELECT post FROM sale_retur WHERE returid = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $retur = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($retur && $retur['post'] == 1) {
                throw new Exception("Retur yang sudah diposting tidak dapat dihapus");
            }

            // Delete detail
            $this->query("DELETE FROM sale_retur_det WHERE retur_id = $returid");

            // Delete header
            $sql = "DELETE FROM sale_retur WHERE returid = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();

            echo json_encode(['status' => 'success', 'msg' => 'Retur berhasil dihapus']);
            exit;
        } catch (Exception $e) {
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /** ======================= [ GET RETUR DETAIL ] ======================= **/
    function get_retur_detail()
    {
        try {
            $this->ensureConnection();

            $returid = $this->sanitizeInt($_GET['returid'] ?? 0);
            if ($returid <= 0) {
                throw new Exception("ID Retur tidak valid");
            }

            // Get header
            $sql = "SELECT sr.*, p.nama AS nama_pelanggan, p.kode AS kode_pelanggan, s.sale_invoice
                    FROM sale_retur sr
                    LEFT JOIN pelanggan p ON p.id = sr.cust_id
                    LEFT JOIN sale s ON s.saleid = sr.saleid
                    WHERE sr.returid = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$header) {
                throw new Exception("Data retur tidak ditemukan");
            }

            // Get detail
            $sql = "SELECT srd.*, COALESCE(srd.is_ppn, srd.ppn, 0) AS is_ppn,
                           pr.nama_produk, pr.barcode, sat.nama_satuan
                    FROM sale_retur_det srd
                    LEFT JOIN produk pr ON pr.id = srd.produk_id
                    LEFT JOIN satuan sat ON sat.id = pr.id_satuan
                    WHERE srd.retur_id = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $returid);
            $stmt->execute();
            $detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'header' => $header,
                'detail' => $detail
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
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
    $ReturPenjualan = new ReturPenjualan();
    $action = request_action('none');

    $allowed_actions = [
        'get_data_retur',
        'get_penjualan_list',
        'get_penjualan_detail',
        'generate_retur_no',
        'save_retur',
        'post_retur',
        'delete_retur',
        'get_retur_detail'
    ];

    if (in_array($action, $allowed_actions)) {
        $ReturPenjualan->$action();
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'failed', 'msg' => 'No action specified']);
    }
} catch (Exception $e) {
    error_log("[RETUR PENJUALAN ROUTER ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
}
exit(0);
}
