<?php

/**
 * Laporan Class - Financial & Inventory Reports
 * Database schema: sale, sale_det, produk, users, pelanggan
 */

header('Content-Type: application/json; charset=utf-8');

// Load database connection
require_once __DIR__ . '/../config.php';

class Laporan
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    private function json_error($message)
    {
        echo json_encode(['error' => $message]);
        exit;
    }

    private function json_success($payload = [])
    {
        echo json_encode(array_merge(['status' => 'success'], $payload));
        exit;
    }

    private function ensure_setoran_kasir_table()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `setoran_kasir` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tanggal_setoran` date NOT NULL,
                  `kasir_id` int(50) NOT NULL,
                  `total_transaksi` decimal(15,2) NOT NULL DEFAULT 0.00,
                  `jumlah_setoran` decimal(15,2) NOT NULL DEFAULT 0.00,
                  `selisih` decimal(15,2) NOT NULL DEFAULT 0.00,
                  `metode_setoran` varchar(30) NOT NULL DEFAULT 'cash',
                  `status` enum('DRAFT','DISETOR','DIVERIFIKASI','BATAL') NOT NULL DEFAULT 'DISETOR',
                  `keterangan` text DEFAULT NULL,
                  `created_by` int(50) DEFAULT NULL,
                  `updated_by` int(50) DEFAULT NULL,
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_setoran_tanggal_kasir` (`tanggal_setoran`, `kasir_id`),
                  KEY `idx_setoran_tanggal` (`tanggal_setoran`),
                  KEY `idx_setoran_kasir` (`kasir_id`),
                  KEY `idx_setoran_status` (`status`),
                  CONSTRAINT `fk_setoran_kasir_user` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        if (!$this->conn->query($sql)) {
            $this->json_error('Create table error: ' . $this->conn->error);
        }
    }

    private function ensure_reward_redemption_table()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `reward_redemption` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `pelanggan_id` int(11) NOT NULL,
                  `reward_id` int(11) NOT NULL,
                  `points_used` int(11) NOT NULL,
                  `profile_score` decimal(5,2) DEFAULT NULL,
                  `status` enum('PENDING','APPROVED','USED','EXPIRED','REJECTED') DEFAULT 'PENDING',
                  `voucher_code` varchar(50) DEFAULT NULL,
                  `used_on_sale_id` int(11) DEFAULT NULL,
                  `approved_by` int(11) DEFAULT NULL,
                  `approved_at` datetime DEFAULT NULL,
                  `expired_at` datetime DEFAULT NULL,
                  `notes` text DEFAULT NULL,
                  `created_at` datetime DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_reward_redemption_voucher` (`voucher_code`),
                  KEY `idx_reward_redemption_pelanggan` (`pelanggan_id`),
                  KEY `idx_reward_redemption_reward` (`reward_id`),
                  KEY `idx_reward_redemption_status` (`status`),
                  KEY `idx_reward_redemption_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        if (!$this->conn->query($sql)) {
            $this->json_error('Create reward redemption table error: ' . $this->conn->error);
        }
    }

    private function validate_date($date)
    {
        $date = trim((string)$date);
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date ? $date : date('Y-m-d');
    }

    private function parse_amount($amount)
    {
        $amount = trim((string)$amount);
        if ($amount === '') {
            return 0.0;
        }

        $amount = str_replace(' ', '', $amount);
        if (strpos($amount, ',') !== false) {
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        }

        return is_numeric($amount) ? (float)$amount : null;
    }

    private function hpp_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        $legacyFallback = $this->historical_newmodal_unit_expression($detailAlias, $productAlias, $detailTotalAlias, $saleAlias);

        return "COALESCE(
                    NULLIF({$detailAlias}.sale_purchase_cost, 0),
                    NULLIF({$productAlias}.harga_beli, 0),
                    ($legacyFallback),
                    0
                ) * {$detailAlias}.sale_qty";
    }

    private function sale_amount_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        return "(CASE
                    WHEN ABS(COALESCE({$saleAlias}.sale_total, 0)) > 0
                         AND ABS(COALESCE({$detailTotalAlias}.detail_total, 0)) >= (ABS({$saleAlias}.sale_total) * 50)
                        THEN {$detailAlias}.sale_harga / 100
                    WHEN COALESCE({$productAlias}.harga_kasir, 0) > 0
                         AND ABS({$detailAlias}.sale_harga) >= (ABS({$productAlias}.harga_kasir) * 50)
                        THEN {$detailAlias}.sale_harga / 100
                    ELSE {$detailAlias}.sale_harga
                END) * {$detailAlias}.sale_qty";
    }

    private function signed_discount_expression($amountExpression, $discountExpression)
    {
        $discount = "LEAST(
                        GREATEST(COALESCE({$discountExpression}, 0), 0),
                        ABS({$amountExpression})
                    )";

        return "(CASE
                    WHEN ({$amountExpression}) < 0
                        THEN ({$amountExpression}) + ({$discount})
                    ELSE ({$amountExpression}) - ({$discount})
                END)";
    }

    private function net_sale_amount_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        $rawLine = $this->sale_amount_expression($detailAlias, $productAlias, $detailTotalAlias, $saleAlias);
        $lineAfterItemDiscount = $this->signed_discount_expression($rawLine, "{$detailAlias}.sale_pdisc");
        $invoiceDiscount = "GREATEST(COALESCE({$saleAlias}.sale_disc, 0), 0)";
        $invoiceBase = "(CASE
                            WHEN COALESCE({$saleAlias}.sale_total, 0) < 0
                                THEN COALESCE({$saleAlias}.sale_total, 0) - ({$invoiceDiscount})
                            ELSE COALESCE({$saleAlias}.sale_total, 0) + ({$invoiceDiscount})
                        END)";

        return "(({$lineAfterItemDiscount}) + CASE
                    WHEN ({$invoiceDiscount}) > 0 AND ABS({$invoiceBase}) > 0.00001
                        THEN (COALESCE({$saleAlias}.sale_total, 0) - ({$invoiceBase}))
                             * (({$lineAfterItemDiscount}) / ({$invoiceBase}))
                    ELSE 0
                END)";
    }

    private function historical_newmodal_unit_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        return "(CASE
                    WHEN COALESCE({$detailAlias}.sale_capital, 0) <> 0
                        THEN CASE
                            WHEN ABS(COALESCE({$saleAlias}.sale_total, 0)) > 0
                                 AND ABS(COALESCE({$detailTotalAlias}.detail_total, 0)) >= (ABS({$saleAlias}.sale_total) * 50)
                                THEN {$detailAlias}.sale_capital / 100
                            WHEN COALESCE({$productAlias}.harga_newmodal, 0) > 0
                                 AND ABS({$detailAlias}.sale_capital) >= (ABS({$productAlias}.harga_newmodal) * 50)
                                THEN {$detailAlias}.sale_capital / 100
                            ELSE {$detailAlias}.sale_capital
                        END
                    ELSE COALESCE({$productAlias}.harga_newmodal, 0)
                END)";
    }

    private function newmodal_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        $unitNewModal = $this->historical_newmodal_unit_expression($detailAlias, $productAlias, $detailTotalAlias, $saleAlias);
        return "($unitNewModal) * {$detailAlias}.sale_qty";
    }

    private function sales_profit_percent_expression($saleAlias = 's')
    {
        return "LEAST(
                    100,
                    GREATEST(
                        0,
                        COALESCE(
                            (SELECT CAST(su.sales_profit_percent AS DECIMAL(10,2))
                             FROM users su
                             WHERE su.id = {$saleAlias}.user_id
                             LIMIT 1),
                            (SELECT CAST(si.meta_value AS DECIMAL(10,2))
                             FROM system_info si
                             WHERE si.meta_field = 'sales_profit_percent'
                             LIMIT 1),
                            100
                        )
                    )
                )";
    }

    private function sales_profit_rate_expression($saleAlias = 's')
    {
        return "((" . $this->sales_profit_percent_expression($saleAlias) . ") / 100)";
    }

    private function ppn_rate_percent()
    {
        $rate = 11.0;
        $stmt = $this->conn->prepare("SELECT meta_value FROM system_info WHERE meta_field = 'ppn_rate' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (isset($row['meta_value']) && is_numeric($row['meta_value'])) {
                $rate = (float)$row['meta_value'];
            }
        }

        return max(0, min(100, $rate));
    }

    private function sales_profit_expression($detailAlias = 'd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        $saleAmount = $this->net_sale_amount_expression($detailAlias, $productAlias, $detailTotalAlias, $saleAlias);
        $netModal = $this->newmodal_expression($detailAlias, $productAlias, $detailTotalAlias, $saleAlias);
        $rate = $this->sales_profit_rate_expression($saleAlias);

        return "(($saleAmount) - ($netModal)) * $rate";
    }

    private function retur_profit_summary_join($alias = 'r')
    {
        return "LEFT JOIN (
                    SELECT sr.saleid,
                           COALESCE(SUM(sr.retur_total), 0) AS retur_total,
                           COALESCE(SUM(rc.retur_hpp), 0) AS retur_hpp,
                           COALESCE(SUM(rc.retur_newmodal), 0) AS retur_newmodal
                    FROM sale_retur sr
                    LEFT JOIN (
                        SELECT rd.retur_id,
                               SUM(
                                   rd.retur_qty * COALESCE(
                                       NULLIF(rd.sale_purchase_cost, 0),
                                       NULLIF(rp.harga_beli, 0),
                                       NULLIF(rd.sale_capital, 0),
                                       0
                                   )
                               ) AS retur_hpp,
                               SUM(
                                   rd.retur_qty * COALESCE(
                                       NULLIF(rd.sale_capital, 0),
                                       NULLIF(rp.harga_newmodal, 0),
                                       0
                                   )
                               ) AS retur_newmodal
                        FROM sale_retur_det rd
                        LEFT JOIN produk rp ON rp.id = rd.produk_id
                        GROUP BY rd.retur_id
                    ) rc ON rc.retur_id = sr.returid
                    WHERE sr.post = 1
                    GROUP BY sr.saleid
                ) {$alias} ON {$alias}.saleid = s.saleid";
    }

    private function current_user_id()
    {
        return (int)($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    private function get_kasir_name($kasir_id)
    {
        $stmt = $this->conn->prepare("SELECT firstname FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $kasir_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row['firstname'] ?? null;
    }

    private function get_setoran_sales_summary($date, $kasir_id)
    {
        $sql = "SELECT COALESCE(SUM(s.sale_total - COALESCE(r.retur_total, 0)), 0) as total_transaksi,
                       COUNT(*) as jumlah_transaksi
                FROM sale s
                LEFT JOIN (
                    SELECT saleid, SUM(retur_total) AS retur_total
                    FROM sale_retur
                    WHERE post = 1
                    GROUP BY saleid
                ) r ON r.saleid = s.saleid
                WHERE DATE(s.sale_date) = ?
                  AND COALESCE(s.user_id, 0) = ?
                  AND COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Summary prepare error: ' . $this->conn->error);

        $stmt->bind_param("si", $date, $kasir_id);
        if (!$stmt->execute()) $this->json_error('Summary execute error: ' . $stmt->error);

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total_transaksi' => (float)($row['total_transaksi'] ?? 0),
            'jumlah_transaksi' => (int)($row['jumlah_transaksi'] ?? 0)
        ];
    }

    public function get_laba_kotor()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $filter_date = $_POST['filter_date'] ?? date('Y-m-d');

        // Count total records
        $countSql = "SELECT COUNT(*) as cnt
                     FROM sale s
                     WHERE DATE(s.sale_date) = ?
                       AND s.sale_status = 'ACTIVE'";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        $stmt->bind_param("s", $filter_date);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        $hppExpr = $this->hpp_expression('d', 'p', 'dt', 's');
        $returJoin = $this->retur_profit_summary_join('r');
        $grossHppExpr = "COALESCE(SUM({$hppExpr}), 0)";
        $netSaleExpr = "(s.sale_total - COALESCE(r.retur_total, 0))";
        $netHppExpr = "(($grossHppExpr) - COALESCE(r.retur_hpp, 0))";

        // Get data
        $sql = "SELECT s.saleid, 
                       s.sale_date, 
                       s.sale_invoice, 
                       {$netSaleExpr} AS sale_total,
                       {$netHppExpr} AS total_hpp,
                       ({$netSaleExpr} - {$netHppExpr}) AS laba
                FROM sale s
                LEFT JOIN sale_det d ON s.saleid = d.sale_id
                LEFT JOIN produk p ON p.id = d.produk_id
                LEFT JOIN (
                    SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                    FROM sale_det
                    GROUP BY sale_id
                ) dt ON dt.sale_id = s.saleid
                {$returJoin}
                WHERE DATE(s.sale_date) = ?
                  AND s.sale_status = 'ACTIVE'
                GROUP BY s.saleid, s.sale_date, s.sale_invoice, s.sale_total,
                         r.retur_total, r.retur_hpp
                ORDER BY s.sale_date DESC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        $stmt->bind_param("sii", $filter_date, $start, $length);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'no' => $no++,
                'tanggal_penjualan' => date('d/m/Y', strtotime($row['sale_date'])),
                'no_invoice' => $row['sale_invoice'],
                'total_penjualan' => (float)$row['sale_total'],
                'total_hpp' => (float)$row['total_hpp'],
                'laba' => (float)$row['laba']
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_profit_penjualan()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);

        // Count total records
        $countSql = "SELECT COUNT(*) as cnt
                     FROM sale s
                     WHERE s.sale_status = 'ACTIVE'";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        $hppExpr = $this->hpp_expression('d', 'p', 'dt', 's');
        $newModalExpr = $this->newmodal_expression('d', 'p', 'dt', 's');
        $salesProfitRate = $this->sales_profit_rate_expression('s');
        $salesProfitPercent = $this->sales_profit_percent_expression('s');
        $returJoin = $this->retur_profit_summary_join('r');
        $grossHppExpr = "COALESCE(SUM({$hppExpr}), 0)";
        $grossNewModalExpr = "COALESCE(SUM({$newModalExpr}), 0)";
        $netSaleExpr = "(s.sale_total - COALESCE(r.retur_total, 0))";
        $netHppExpr = "(($grossHppExpr) - COALESCE(r.retur_hpp, 0))";
        $netNewModalExpr = "(($grossNewModalExpr) - COALESCE(r.retur_newmodal, 0))";

        // Get data
        $sql = "SELECT s.saleid, 
                       s.sale_date, 
                       s.sale_invoice, 
                       s.sale_channel,
                       COALESCE(u.firstname, u.username, '-') AS sales_name,
                       {$salesProfitPercent} AS sales_profit_percent,
                       {$netSaleExpr} AS sale_total,
                       {$netHppExpr} AS total_hpp,
                       ({$netSaleExpr} - {$netHppExpr}) AS profit,
                       CASE
                           WHEN s.sale_channel IN ('sales', 'online')
                               THEN (({$netSaleExpr} - {$netNewModalExpr}) * {$salesProfitRate})
                           ELSE 0
                       END AS profit_sales
                FROM sale s
                LEFT JOIN users u ON u.id = s.user_id
                LEFT JOIN sale_det d ON s.saleid = d.sale_id
                LEFT JOIN produk p ON p.id = d.produk_id
                LEFT JOIN (
                    SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                    FROM sale_det
                    GROUP BY sale_id
                ) dt ON dt.sale_id = s.saleid
                {$returJoin}
                WHERE s.sale_status = 'ACTIVE'
                GROUP BY s.saleid, s.sale_date, s.sale_invoice, s.sale_channel, s.user_id,
                         u.firstname, u.username, s.sale_total,
                         r.retur_total, r.retur_hpp, r.retur_newmodal
                ORDER BY s.sale_date DESC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        $stmt->bind_param("ii", $start, $length);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'no' => $no++,
                'sale_date' => date('d/m/Y', strtotime($row['sale_date'])),
                'sale_invoice' => $row['sale_invoice'],
                'sale_channel' => $row['sale_channel'],
                'sales_name' => $row['sales_name'],
                'sales_profit_percent' => (float)$row['sales_profit_percent'],
                'sale_total' => (float)$row['sale_total'],
                'total_hpp' => (float)$row['total_hpp'],
                'profit' => (float)$row['profit'],
                'profit_kantor' => (float)$row['profit'],
                'profit_sales' => (float)$row['profit_sales']
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_produk_terlaris()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_POST['end_date'] ?? date('Y-m-d');

        // Count
        $countSql = "SELECT COUNT(DISTINCT d.produk_id) as cnt 
                     FROM sale_det d
                     INNER JOIN sale s ON d.sale_id = s.saleid
                     WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.sale_status = 'ACTIVE'";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        $saleAmountExpr = $this->net_sale_amount_expression('d', 'p', 'dt', 's');

        // Ringkas penjualan per transaksi-produk terlebih dahulu agar satu retur tidak
        // terduplikasi ketika satu faktur memiliki lebih dari satu baris produk yang sama.
        $saleProductSql = "SELECT s.saleid, d.produk_id,
                                  SUM(d.sale_qty) AS sale_qty,
                                  SUM({$saleAmountExpr}) AS sale_total
                           FROM sale_det d
                           INNER JOIN sale s ON d.sale_id = s.saleid
                           LEFT JOIN produk p ON p.id = d.produk_id
                           LEFT JOIN (
                               SELECT sale_id, SUM(sale_qty * sale_harga) AS detail_total
                               FROM sale_det
                               GROUP BY sale_id
                           ) dt ON dt.sale_id = s.saleid
                           WHERE DATE(s.sale_date) BETWEEN ? AND ?
                             AND s.sale_status = 'ACTIVE'
                           GROUP BY s.saleid, d.produk_id";
        $returnProductSql = "SELECT sr.saleid, rd.produk_id,
                                    SUM(rd.retur_qty) AS retur_qty,
                                    SUM(CASE
                                            WHEN COALESCE(rt.detail_total, 0) <> 0
                                                THEN sr.retur_total * ((rd.retur_qty * rd.sale_harga) / rt.detail_total)
                                            ELSE rd.retur_qty * rd.sale_harga
                                        END) AS retur_total
                             FROM sale_retur sr
                             INNER JOIN sale_retur_det rd ON rd.retur_id = sr.returid
                             INNER JOIN (
                                 SELECT retur_id, SUM(retur_qty * sale_harga) AS detail_total
                                 FROM sale_retur_det
                                 GROUP BY retur_id
                             ) rt ON rt.retur_id = sr.returid
                             WHERE sr.post = 1
                             GROUP BY sr.saleid, rd.produk_id";

        // Retur penjualan yang sudah diposting adalah pengurang qty dan nilai terjual.
        $sql = "SELECT p.id,
                       p.nama_produk,
                       SUM(sp.sale_qty - COALESCE(rp.retur_qty, 0)) AS total_qty,
                       SUM(sp.sale_total - COALESCE(rp.retur_total, 0)) AS total_penjualan
                FROM ({$saleProductSql}) sp
                INNER JOIN produk p ON p.id = sp.produk_id
                LEFT JOIN ({$returnProductSql}) rp
                       ON rp.saleid = sp.saleid AND rp.produk_id = sp.produk_id
                GROUP BY p.id, p.nama_produk
                HAVING total_qty > 0
                ORDER BY total_qty DESC, total_penjualan DESC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        $stmt->bind_param("ssii", $start_date, $end_date, $start, $length);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'no' => $no++,
                'nama_barang' => $row['nama_produk'],
                'total_qty' => (float)$row['total_qty'],
                'total_penjualan' => (float)$row['total_penjualan']
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_setoran_kasir()
    {
        if (!$this->conn) $this->json_error('Database connection failed');
        $this->ensure_setoran_kasir_table();

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $filter_date = $this->validate_date($_POST['filter_date'] ?? date('Y-m-d'));

        $countSql = "SELECT COUNT(*) as cnt
                     FROM (
                       SELECT DATE(s.sale_date) as tanggal, COALESCE(s.user_id, 0) as kasir_id
                       FROM sale s
                       WHERE DATE(s.sale_date) = ?
                         AND COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'
                       GROUP BY DATE(s.sale_date), COALESCE(s.user_id, 0)
                       UNION
                       SELECT sk.tanggal_setoran as tanggal, sk.kasir_id
                       FROM setoran_kasir sk
                       WHERE sk.tanggal_setoran = ?
                     ) x";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        $stmt->bind_param("ss", $filter_date, $filter_date);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        $sql = "SELECT x.kasir_id,
                       x.tanggal,
                       COALESCE(u.firstname, 'Unknown') as nama_kasir,
                       COALESCE(ss.total_transaksi, 0) as total_penjualan,
                       COALESCE(ss.jumlah_transaksi, 0) as jumlah_transaksi,
                       sk.id as setoran_id,
                       COALESCE(sk.jumlah_setoran, 0) as jumlah_setoran,
                       (COALESCE(sk.jumlah_setoran, 0) - COALESCE(ss.total_transaksi, 0)) as selisih,
                       COALESCE(sk.keterangan, '') as keterangan,
                       COALESCE(inputter.firstname, '') as input_by,
                       sk.created_at,
                       sk.updated_at
                FROM (
                  SELECT DATE(s.sale_date) as tanggal, COALESCE(s.user_id, 0) as kasir_id
                  FROM sale s
                  WHERE DATE(s.sale_date) = ?
                    AND COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'
                  GROUP BY DATE(s.sale_date), COALESCE(s.user_id, 0)
                  UNION
                  SELECT sk.tanggal_setoran as tanggal, sk.kasir_id
                  FROM setoran_kasir sk
                  WHERE sk.tanggal_setoran = ?
                ) x
                LEFT JOIN (
                  SELECT DATE(s.sale_date) as tanggal,
                         COALESCE(s.user_id, 0) as kasir_id,
                         COALESCE(SUM(s.sale_total - COALESCE(r.retur_total, 0)), 0) as total_transaksi,
                         COUNT(*) as jumlah_transaksi
                  FROM sale s
                  LEFT JOIN (
                      SELECT saleid, SUM(retur_total) AS retur_total
                      FROM sale_retur
                      WHERE post = 1
                      GROUP BY saleid
                  ) r ON r.saleid = s.saleid
                  WHERE DATE(s.sale_date) = ?
                    AND COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'
                  GROUP BY DATE(s.sale_date), COALESCE(s.user_id, 0)
                ) ss ON ss.tanggal = x.tanggal AND ss.kasir_id = x.kasir_id
                LEFT JOIN setoran_kasir sk ON sk.tanggal_setoran = x.tanggal AND sk.kasir_id = x.kasir_id
                LEFT JOIN users u ON u.id = x.kasir_id
                LEFT JOIN users inputter ON inputter.id = sk.created_by
                ORDER BY x.tanggal DESC, nama_kasir ASC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        $stmt->bind_param("sssii", $filter_date, $filter_date, $filter_date, $start, $length);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $tanggalRaw = $row['tanggal'];
            $data[] = [
                'no' => $no++,
                'row_key' => $tanggalRaw . '-' . (int)$row['kasir_id'],
                'setoran_id' => (int)($row['setoran_id'] ?? 0),
                'kasir_id' => (int)$row['kasir_id'],
                'tanggal_raw' => $tanggalRaw,
                'tanggal' => date('d/m/Y', strtotime($tanggalRaw)),
                'nama_kasir' => $row['nama_kasir'],
                'jumlah_transaksi' => (int)$row['jumlah_transaksi'],
                'total_penjualan' => (float)$row['total_penjualan'],
                'jumlah_setoran' => (float)$row['jumlah_setoran'],
                'selisih' => (float)$row['selisih'],
                'keterangan' => $row['keterangan'],
                'input_by' => $row['input_by'],
                'created_at' => $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-',
                'updated_at' => $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-'
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_omset_penjualan()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $dateStart = $this->validate_date($_POST['date_start'] ?? date('Y-m-01'));
        $dateEnd = $this->validate_date($_POST['date_end'] ?? date('Y-m-d'));
        $mode = $_POST['mode'] ?? 'per_sales';
        $source = strtolower(trim((string)($_POST['source'] ?? 'sales')));
        if (!in_array($source, ['sales', 'online', 'kasir', 'all'], true)) {
            $source = 'sales';
        }
        $salesId = (int)($_POST['sales_id'] ?? 0);

        $where = ["s.sale_status = 'ACTIVE'", "DATE(s.sale_date) BETWEEN ? AND ?"];
        $params = [$dateStart, $dateEnd];
        $types = "ss";

        if ($source !== 'all') {
            $where[] = "s.sale_channel = ?";
            $params[] = $source;
            $types .= "s";
        }

        if ($salesId > 0) {
            $where[] = "s.user_id = ?";
            $params[] = $salesId;
            $types .= "i";
        }

        $whereSql = implode(' AND ', $where);

        $returJoin = $this->retur_profit_summary_join('r');
        $netOmsetExpr = "(s.sale_total - COALESCE(r.retur_total, 0))";

        if ($mode === 'per_penjualan') {
            $sql = "SELECT DATE_FORMAT(s.sale_date, '%d/%m/%Y') AS tanggal,
                           s.sale_invoice,
                           s.sale_channel,
                           CASE s.sale_channel
                               WHEN 'online' THEN 'Sales Online'
                               WHEN 'kasir' THEN 'Kasir'
                               ELSE 'Sales'
                           END AS channel_name,
                           COALESCE(u.firstname, u.username, '-') AS sales_name,
                           COALESCE(p.nama, '-') AS pelanggan,
                           {$netOmsetExpr} AS omset
                    FROM sale s
                    LEFT JOIN users u ON u.id = s.user_id
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    {$returJoin}
                    WHERE {$whereSql}
                    ORDER BY s.sale_date DESC, s.saleid DESC";
        } else {
            $sql = "SELECT COALESCE(u.firstname, u.username, '-') AS sales_name,
                           COUNT(*) AS jumlah_transaksi,
                           SUM({$netOmsetExpr}) AS omset
                    FROM sale s
                    LEFT JOIN users u ON u.id = s.user_id
                    {$returJoin}
                    WHERE {$whereSql}
                    GROUP BY s.user_id, sales_name
                    ORDER BY omset DESC";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Omset prepare error: ' . $this->conn->error);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) $this->json_error('Omset execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $row['omset'] = (float)($row['omset'] ?? 0);
            $total += $row['omset'];
            $data[] = $row;
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'summary' => [
                'total_omset' => $total,
                'jumlah_baris' => count($data)
            ]
        ]);
    }

    public function get_laporan_sales()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $dateStart = $this->validate_date($_POST['date_start'] ?? date('Y-m-d'));
        $dateEnd = $this->validate_date($_POST['date_end'] ?? date('Y-m-d'));
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        $filterType = ($_POST['filter_type'] ?? 'salesman') === 'pelanggan' ? 'pelanggan' : 'salesman';
        $reportType = ($_POST['report_type'] ?? 'detail') === 'total' ? 'total' : 'detail';
        $channel = strtolower(trim((string)($_POST['sale_channel'] ?? 'all')));
        if (!in_array($channel, ['all', 'sales', 'online'], true)) {
            $channel = 'all';
        }
        $salesmanId = (int)($_POST['salesman_id'] ?? 0);
        $pelangganId = (int)($_POST['pelanggan_id'] ?? 0);

        $where = [
            "COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'",
            "s.sale_channel IN ('sales', 'online')",
            "DATE(s.sale_date) BETWEEN ? AND ?"
        ];
        $params = [$dateStart, $dateEnd];
        $types = "ss";

        if ($channel !== 'all') {
            $where[] = "s.sale_channel = ?";
            $params[] = $channel;
            $types .= "s";
        }

        if ($filterType === 'salesman' && $salesmanId > 0) {
            $where[] = "s.user_id = ?";
            $params[] = $salesmanId;
            $types .= "i";
        }

        if ($filterType === 'pelanggan' && $pelangganId > 0) {
            $where[] = "s.cust_id = ?";
            $params[] = $pelangganId;
            $types .= "i";
        }

        if (function_exists('current_user_level') && current_user_level() === 3) {
            $where[] = "s.user_id = ?";
            $params[] = $this->current_user_id();
            $types .= "i";
        }

        $whereSql = implode(' AND ', $where);
        $rawLineTotalExpr = $this->sale_amount_expression('d', 'pr', 'dt', 's');
        $lineTotalExpr = $this->net_sale_amount_expression('d', 'pr', 'dt', 's');
        $unitPriceExpr = "(CASE WHEN d.sale_qty = 0 THEN 0 ELSE ({$rawLineTotalExpr}) / d.sale_qty END)";

        $returJoin = $this->retur_profit_summary_join('r');
        $netSaleExpr = "(s.sale_total - COALESCE(r.retur_total, 0))";
        $returnDetailAmountExpr = "(CASE
                                        WHEN COALESCE(rt.detail_total, 0) <> 0
                                            THEN sr.retur_total * ((rd.retur_qty * rd.sale_harga) / rt.detail_total)
                                        ELSE rd.retur_qty * rd.sale_harga
                                    END)";

        if ($reportType === 'total') {
            $orderBy = $filterType === 'pelanggan'
                ? "pelanggan ASC, s.sale_date ASC, s.saleid ASC"
                : "sales_name ASC, s.sale_date ASC, s.saleid ASC";

            $sql = "SELECT DATE_FORMAT(s.sale_date, '%d/%m/%Y') AS tanggal,
                           s.sale_invoice,
                           s.sale_channel,
                           COALESCE(s.sale_nofak, '') AS sale_nofak,
                           COALESCE(u.firstname, u.username, 'Tanpa Salesman') AS sales_name,
                           COALESCE(p.nama, 'Tanpa Pelanggan') AS pelanggan,
                           COALESCE(p.kode, '') AS pelanggan_kode,
                           {$netSaleExpr} AS total_penjualan
                    FROM sale s
                    LEFT JOIN users u ON u.id = s.user_id
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    {$returJoin}
                    WHERE {$whereSql}
                    ORDER BY {$orderBy}";
        } else {
            // Detail retur ditampilkan sebagai baris negatif pada faktur asal. Dengan
            // demikian qty serta subtotal laporan selalu sama dengan nilai bersih retur.
            $sql = "SELECT DATE_FORMAT(s.sale_date, '%d/%m/%Y') AS tanggal,
                           s.sale_invoice,
                           s.sale_channel,
                           COALESCE(s.sale_nofak, '') AS sale_nofak,
                           COALESCE(u.firstname, u.username, 'Tanpa Salesman') AS sales_name,
                           COALESCE(p.nama, '-') AS pelanggan,
                           COALESCE(p.kode, '') AS pelanggan_kode,
                           COALESCE(pr.barcode, '-') AS barcode,
                           COALESCE(pr.nama_produk, '-') AS nama_produk,
                           COALESCE(d.sale_qty, 0) AS qty,
                           {$unitPriceExpr} AS harga,
                           COALESCE(d.sale_pdisc, 0) AS diskon,
                           {$lineTotalExpr} AS subtotal
                    FROM sale s
                    INNER JOIN sale_det d ON d.sale_id = s.saleid
                    LEFT JOIN produk pr ON pr.id = d.produk_id
                    LEFT JOIN (
                        SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                        FROM sale_det
                        GROUP BY sale_id
                    ) dt ON dt.sale_id = s.saleid
                    LEFT JOIN users u ON u.id = s.user_id
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    WHERE {$whereSql}
                    UNION ALL
                    SELECT DATE_FORMAT(s.sale_date, '%d/%m/%Y') AS tanggal,
                           s.sale_invoice,
                           s.sale_channel,
                           COALESCE(s.sale_nofak, '') AS sale_nofak,
                           COALESCE(u.firstname, u.username, 'Tanpa Salesman') AS sales_name,
                           COALESCE(p.nama, '-') AS pelanggan,
                           COALESCE(p.kode, '') AS pelanggan_kode,
                           COALESCE(pr.barcode, '-') AS barcode,
                           CONCAT('RETUR - ', COALESCE(pr.nama_produk, '-')) AS nama_produk,
                           -COALESCE(rd.retur_qty, 0) AS qty,
                           CASE WHEN COALESCE(rd.retur_qty, 0) = 0 THEN 0
                                ELSE ({$returnDetailAmountExpr}) / rd.retur_qty END AS harga,
                           0 AS diskon,
                           -({$returnDetailAmountExpr}) AS subtotal
                    FROM sale_retur sr
                    INNER JOIN sale s ON s.saleid = sr.saleid
                    INNER JOIN sale_retur_det rd ON rd.retur_id = sr.returid
                    INNER JOIN (
                        SELECT retur_id, SUM(retur_qty * sale_harga) AS detail_total
                        FROM sale_retur_det
                        GROUP BY retur_id
                    ) rt ON rt.retur_id = sr.returid
                    LEFT JOIN produk pr ON pr.id = rd.produk_id
                    LEFT JOIN users u ON u.id = s.user_id
                    LEFT JOIN pelanggan p ON p.id = s.cust_id
                    WHERE sr.post = 1 AND {$whereSql}
                    ORDER BY tanggal ASC, sale_invoice ASC, qty DESC";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Laporan sales prepare error: ' . $this->conn->error);
        $queryParams = $reportType === 'detail' ? array_merge($params, $params) : $params;
        $queryTypes = $reportType === 'detail' ? $types . $types : $types;
        $stmt->bind_param($queryTypes, ...$queryParams);
        if (!$stmt->execute()) $this->json_error('Laporan sales execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $totalQty = 0;
        $totalPenjualan = 0;
        $invoiceMap = [];

        while ($row = $result->fetch_assoc()) {
            if ($reportType === 'total') {
                $row['total_penjualan'] = (float)($row['total_penjualan'] ?? 0);
                $totalPenjualan += $row['total_penjualan'];
                $invoiceMap[$row['sale_invoice']] = true;
            } else {
                $row['qty'] = (float)($row['qty'] ?? 0);
                $row['harga'] = (float)($row['harga'] ?? 0);
                $row['diskon'] = (float)($row['diskon'] ?? 0);
                $row['subtotal'] = (float)($row['subtotal'] ?? 0);
                $totalQty += $row['qty'];
                $totalPenjualan += $row['subtotal'];
                $invoiceMap[$row['sale_invoice']] = true;
            }

            $data[] = $row;
        }
        $stmt->close();

        $this->json_success([
            'data' => $data,
            'summary' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'filter_type' => $filterType,
                'report_type' => $reportType,
                'jumlah_transaksi' => count($invoiceMap),
                'jumlah_baris' => count($data),
                'total_qty' => $totalQty,
                'total_penjualan' => $totalPenjualan
            ]
        ]);
    }

    public function get_laporan_ppn()
    {
        if (!$this->conn) $this->json_error('Database connection failed');
        if (function_exists('current_user_level') && !in_array(current_user_level(), [1, 4], true)) {
            $this->json_error('Laporan PPN hanya dapat diakses oleh owner/admin.');
        }

        $dateStart = $this->validate_date($_POST['date_start'] ?? date('Y-m-01'));
        $dateEnd = $this->validate_date($_POST['date_end'] ?? date('Y-m-d'));
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        $taxType = strtolower(trim((string)($_POST['tax_type'] ?? 'all')));
        if (!in_array($taxType, ['all', 'ppn', 'non_ppn'], true)) {
            $taxType = 'all';
        }

        $source = strtolower(trim((string)($_POST['source'] ?? 'all')));
        if (!in_array($source, ['all', 'sales', 'online', 'kasir'], true)) {
            $source = 'all';
        }

        $ratePercent = $this->ppn_rate_percent();
        $rateDecimal = $ratePercent / 100;
        $divisor = 1 + $rateDecimal;
        $divisorSql = number_format($divisor, 8, '.', '');

        $lineNetExpr = $this->net_sale_amount_expression('d', 'pr', 'dt', 's');
        $isPpnExpr = "(CASE
                            WHEN COALESCE(d.is_ppn, 0) = 1
                                 OR ABS(COALESCE(d.ppn_item, 0)) > 0.0001
                                THEN 1
                            ELSE 0
                        END)";
        $calculatedPpnExpr = "ROUND(({$lineNetExpr}) - (({$lineNetExpr}) / {$divisorSql}), 0)";
        $ppnExpr = "(CASE
                        WHEN {$isPpnExpr} = 1 THEN
                            CASE
                                WHEN ABS(COALESCE(d.ppn_item, 0)) > 0.0001
                                    THEN COALESCE(d.ppn_item, 0)
                                ELSE {$calculatedPpnExpr}
                            END
                        ELSE 0
                    END)";
        $dppExpr = "(({$lineNetExpr}) - ({$ppnExpr}))";

        $saleWhere = [
            "COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'",
            "DATE(s.sale_date) BETWEEN ? AND ?"
        ];
        if ($taxType === 'ppn') {
            $saleWhere[] = "{$isPpnExpr} = 1";
        } elseif ($taxType === 'non_ppn') {
            $saleWhere[] = "{$isPpnExpr} = 0";
        }
        if ($source !== 'all') {
            $saleWhere[] = "s.sale_channel = '" . $source . "'";
        }

        $saleSql = "SELECT DATE_FORMAT(s.sale_date, '%d/%m/%Y') AS tanggal,
                           DATE_FORMAT(s.sale_date, '%Y-%m-%d %H:%i:%s') AS sort_date,
                           'PENJUALAN' AS jenis_transaksi,
                           s.sale_invoice AS invoice,
                           '' AS invoice_asal,
                           CASE s.sale_channel
                               WHEN 'online' THEN 'Sales Online'
                               WHEN 'kasir' THEN 'Kasir'
                               ELSE 'Sales'
                           END AS sumber,
                           COALESCE(u.firstname, u.username, '-') AS petugas,
                           COALESCE(pr.barcode, '-') AS barcode,
                           COALESCE(pr.nama_produk, '-') AS nama_produk,
                           CASE WHEN {$isPpnExpr} = 1 THEN 'PPN' ELSE 'NON PPN' END AS status_ppn,
                           COALESCE(d.sale_qty, 0) AS qty,
                           {$dppExpr} AS dpp,
                           {$ppnExpr} AS ppn,
                           {$lineNetExpr} AS total,
                           1 AS sort_sequence
                    FROM sale s
                    INNER JOIN sale_det d ON d.sale_id = s.saleid
                    LEFT JOIN produk pr ON pr.id = d.produk_id
                    LEFT JOIN (
                        SELECT sale_id, SUM(sale_qty * sale_harga) AS detail_total
                        FROM sale_det
                        GROUP BY sale_id
                    ) dt ON dt.sale_id = s.saleid
                    LEFT JOIN users u ON u.id = s.user_id
                    WHERE " . implode(' AND ', $saleWhere);

        $stmt = $this->conn->prepare($saleSql);
        if (!$stmt) $this->json_error('Laporan PPN penjualan prepare error: ' . $this->conn->error);
        $stmt->bind_param('ss', $dateStart, $dateEnd);
        if (!$stmt->execute()) $this->json_error('Laporan PPN penjualan execute error: ' . $stmt->error);
        $saleRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $returnIsPpnExpr = "(CASE WHEN COALESCE(rd.ppn, 0) = 1 THEN 1 ELSE 0 END)";
        $returnLineExpr = "(COALESCE(rd.retur_qty, 0) * COALESCE(rd.sale_harga, 0))";
        $returnPpnExpr = "(CASE
                            WHEN {$returnIsPpnExpr} = 1 THEN
                                CASE
                                    WHEN ABS(COALESCE(sr.retur_ppn, 0)) > 0.0001
                                         AND ABS(COALESCE(rt.ppn_gross, 0)) > 0.0001
                                        THEN -(COALESCE(sr.retur_ppn, 0) * ({$returnLineExpr} / rt.ppn_gross))
                                    ELSE -ROUND(({$returnLineExpr}) - (({$returnLineExpr}) / {$divisorSql}), 0)
                                END
                            ELSE 0
                        END)";
        $returnTotalExpr = "(-({$returnLineExpr}))";
        $returnDppExpr = "(({$returnTotalExpr}) - ({$returnPpnExpr}))";

        $returnWhere = [
            "COALESCE(sr.post, 0) = 1",
            "DATE(sr.retur_date) BETWEEN ? AND ?"
        ];
        if ($taxType === 'ppn') {
            $returnWhere[] = "{$returnIsPpnExpr} = 1";
        } elseif ($taxType === 'non_ppn') {
            $returnWhere[] = "{$returnIsPpnExpr} = 0";
        }
        if ($source !== 'all') {
            $returnWhere[] = "s.sale_channel = '" . $source . "'";
        }

        $returnSql = "SELECT DATE_FORMAT(sr.retur_date, '%d/%m/%Y') AS tanggal,
                             CONCAT(DATE_FORMAT(sr.retur_date, '%Y-%m-%d'), ' 23:59:59') AS sort_date,
                             'RETUR' AS jenis_transaksi,
                             sr.retur_invoice AS invoice,
                             COALESCE(s.sale_invoice, '') AS invoice_asal,
                             CASE s.sale_channel
                                 WHEN 'online' THEN 'Sales Online'
                                 WHEN 'kasir' THEN 'Kasir'
                                 ELSE 'Sales'
                             END AS sumber,
                             COALESCE(u.firstname, u.username, '-') AS petugas,
                             COALESCE(pr.barcode, '-') AS barcode,
                             COALESCE(pr.nama_produk, '-') AS nama_produk,
                             CASE WHEN {$returnIsPpnExpr} = 1 THEN 'PPN' ELSE 'NON PPN' END AS status_ppn,
                             -COALESCE(rd.retur_qty, 0) AS qty,
                             {$returnDppExpr} AS dpp,
                             {$returnPpnExpr} AS ppn,
                             {$returnTotalExpr} AS total,
                             2 AS sort_sequence
                      FROM sale_retur sr
                      INNER JOIN sale_retur_det rd ON rd.retur_id = sr.returid
                      LEFT JOIN sale s ON s.saleid = sr.saleid
                      LEFT JOIN produk pr ON pr.id = rd.produk_id
                      LEFT JOIN users u ON u.id = sr.user_id
                      LEFT JOIN (
                          SELECT rdx.retur_id,
                                 SUM(CASE
                                        WHEN COALESCE(rdx.ppn, 0) = 1
                                            THEN COALESCE(rdx.retur_qty, 0) * COALESCE(rdx.sale_harga, 0)
                                        ELSE 0
                                     END) AS ppn_gross
                          FROM sale_retur_det rdx
                          GROUP BY rdx.retur_id
                      ) rt ON rt.retur_id = sr.returid
                      WHERE " . implode(' AND ', $returnWhere);

        $stmt = $this->conn->prepare($returnSql);
        if (!$stmt) $this->json_error('Laporan PPN retur prepare error: ' . $this->conn->error);
        $stmt->bind_param('ss', $dateStart, $dateEnd);
        if (!$stmt->execute()) $this->json_error('Laporan PPN retur execute error: ' . $stmt->error);
        $returnRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $data = array_merge($saleRows, $returnRows);
        usort($data, function ($left, $right) {
            $dateComparison = strcmp($right['sort_date'], $left['sort_date']);
            if ($dateComparison !== 0) {
                return $dateComparison;
            }
            return ((int)$right['sort_sequence']) <=> ((int)$left['sort_sequence']);
        });

        $summary = [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'tax_type' => $taxType,
            'source' => $source,
            'ppn_rate' => $ratePercent,
            'dpp_ppn' => 0.0,
            'ppn' => 0.0,
            'total_ppn' => 0.0,
            'total_non_ppn' => 0.0,
            'grand_total' => 0.0,
            'jumlah_transaksi' => 0,
            'jumlah_baris' => count($data),
            'jumlah_baris_ppn' => 0,
            'jumlah_baris_non_ppn' => 0
        ];
        $invoiceMap = [];

        foreach ($data as &$row) {
            $row['qty'] = (float)($row['qty'] ?? 0);
            $row['dpp'] = (float)($row['dpp'] ?? 0);
            $row['ppn'] = (float)($row['ppn'] ?? 0);
            $row['total'] = (float)($row['total'] ?? 0);
            unset($row['sort_date'], $row['sort_sequence']);

            if ($row['status_ppn'] === 'PPN') {
                $summary['dpp_ppn'] += $row['dpp'];
                $summary['ppn'] += $row['ppn'];
                $summary['total_ppn'] += $row['total'];
                $summary['jumlah_baris_ppn']++;
            } else {
                $summary['total_non_ppn'] += $row['total'];
                $summary['jumlah_baris_non_ppn']++;
            }
            $summary['grand_total'] += $row['total'];
            $invoiceMap[$row['jenis_transaksi'] . ':' . $row['invoice']] = true;
        }
        unset($row);

        $summary['jumlah_transaksi'] = count($invoiceMap);

        $this->json_success([
            'data' => $data,
            'summary' => $summary
        ]);
    }

    public function get_setoran_summary()
    {
        if (!$this->conn) $this->json_error('Database connection failed');
        $this->ensure_setoran_kasir_table();

        $date = $this->validate_date($_POST['setoran_date'] ?? $_POST['filter_date'] ?? date('Y-m-d'));
        $kasir_id = (int)($_POST['kasir_id'] ?? 0);

        if ($kasir_id <= 0) {
            echo json_encode([
                'status' => 'failed',
                'msg' => 'Pilih kasir terlebih dahulu.'
            ]);
            exit;
        }

        $summary = $this->get_setoran_sales_summary($date, $kasir_id);

        $stmt = $this->conn->prepare("SELECT id, jumlah_setoran, keterangan FROM setoran_kasir WHERE tanggal_setoran = ? AND kasir_id = ? LIMIT 1");
        if (!$stmt) $this->json_error('Existing prepare error: ' . $this->conn->error);

        $stmt->bind_param("si", $date, $kasir_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->json_success([
            'data' => array_merge($summary, [
                'setoran_id' => (int)($existing['id'] ?? 0),
                'jumlah_setoran' => (float)($existing['jumlah_setoran'] ?? 0),
                'keterangan' => $existing['keterangan'] ?? ''
            ])
        ]);
    }

    public function save_setoran_kasir()
    {
        if (!$this->conn) $this->json_error('Database connection failed');
        $this->ensure_setoran_kasir_table();

        $date = $this->validate_date($_POST['setoran_date'] ?? date('Y-m-d'));
        $kasir_id = (int)($_POST['kasir_id'] ?? 0);
        $jumlah_setoran = $this->parse_amount($_POST['jumlah_setoran'] ?? 0);
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));

        if ($kasir_id <= 0) {
            echo json_encode(['status' => 'failed', 'msg' => 'Kasir wajib dipilih.']);
            exit;
        }

        if ($jumlah_setoran === null || $jumlah_setoran < 0) {
            echo json_encode(['status' => 'failed', 'msg' => 'Jumlah setoran tidak valid.']);
            exit;
        }

        $kasir_name = $this->get_kasir_name($kasir_id);
        if ($kasir_name === null) {
            echo json_encode(['status' => 'failed', 'msg' => 'Data kasir tidak ditemukan.']);
            exit;
        }

        $summary = $this->get_setoran_sales_summary($date, $kasir_id);
        $total_transaksi = $summary['total_transaksi'];
        $jumlah_transaksi = $summary['jumlah_transaksi'];
        $selisih = $jumlah_setoran - $total_transaksi;
        $user_id = $this->current_user_id();

        $sql = "INSERT INTO setoran_kasir
                    (tanggal_setoran, kasir_id, total_transaksi, jumlah_setoran, selisih, keterangan, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_transaksi = VALUES(total_transaksi),
                    jumlah_setoran = VALUES(jumlah_setoran),
                    selisih = VALUES(selisih),
                    keterangan = VALUES(keterangan),
                    updated_by = VALUES(updated_by)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Save prepare error: ' . $this->conn->error);

        $stmt->bind_param(
            "sidddsii",
            $date,
            $kasir_id,
            $total_transaksi,
            $jumlah_setoran,
            $selisih,
            $keterangan,
            $user_id,
            $user_id
        );

        if (!$stmt->execute()) {
            $this->json_error('Save execute error: ' . $stmt->error);
        }

        $stmt->close();

        $this->json_success([
            'msg' => 'Setoran kasir berhasil disimpan.',
            'data' => [
                'setoran_date' => $date,
                'kasir_id' => $kasir_id,
                'total_transaksi' => $total_transaksi,
                'jumlah_transaksi' => $jumlah_transaksi,
                'jumlah_setoran' => $jumlah_setoran,
                'selisih' => $selisih
            ]
        ]);
    }

    public function get_kartu_poin()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);

        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if ($pelanggan_id > 0) {
            $where .= " AND p.id = ?";
            $params[] = $pelanggan_id;
            $types .= "i";
        }

        // Count
        $countSql = "SELECT COUNT(*) as cnt FROM pelanggan p {$where}";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        // Data - Calculate points from customer_points table
        $sql = "SELECT p.id, 
                       p.nama,
                       COALESCE(SUM(cp.points), 0) as sisa_poin
                FROM pelanggan p
                LEFT JOIN customer_points cp ON p.id = cp.pelanggan_id 
                  AND (cp.expired_at IS NULL OR cp.expired_at > NOW())
                {$where}
                GROUP BY p.id, p.nama
                ORDER BY p.nama ASC
                LIMIT ?, ?";

        $allParams = array_merge($params, [$start, $length]);
        $allTypes = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        if ($allTypes) $stmt->bind_param($allTypes, ...$allParams);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'no' => $no++,
                'nama_pelanggan' => $row['nama'],
                'sisa_poin' => (int)$row['sisa_poin']
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_rekap_poin()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $filter_date = $_POST['filter_date'] ?? date('Y-m-d');

        // Ubah format tanggal jika perlu
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
            $filter_date = date('Y-m-d');
        }

        // Count total records per tanggal
        $countSql = "SELECT COUNT(DISTINCT DATE(cp.created_at)) as cnt 
                     FROM customer_points cp
                     WHERE DATE(cp.created_at) = ?";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        $stmt->bind_param("s", $filter_date);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $stmt->close();

        // Get data summary per tanggal
        $sql = "SELECT DATE(cp.created_at) as tanggal,
                       SUM(CASE WHEN cp.point_type = 'EARN' THEN cp.points ELSE 0 END) as total_poin_diberikan,
                       SUM(CASE WHEN cp.point_type = 'REDEEM' THEN ABS(cp.points) ELSE 0 END) as total_poin_digunakan,
                       COUNT(DISTINCT cp.pelanggan_id) as total_transaksi
                FROM customer_points cp
                WHERE DATE(cp.created_at) = ?
                GROUP BY DATE(cp.created_at)
                ORDER BY cp.created_at DESC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Main prepare error: ' . $this->conn->error);

        $stmt->bind_param("sii", $filter_date, $start, $length);
        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'no' => $no++,
                'tanggal' => $row['tanggal'] ? date('d/m/Y', strtotime($row['tanggal'])) : '-',
                'total_poin_diberikan' => (int)($row['total_poin_diberikan'] ?? 0),
                'total_poin_digunakan' => (int)($row['total_poin_digunakan'] ?? 0),
                'total_transaksi' => (int)($row['total_transaksi'] ?? 0)
            ];
        }

        $stmt->close();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    public function get_member_cards()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $search = trim($_POST['search'] ?? '');
        $customer_type = trim($_POST['customer_type'] ?? '');
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);

        // Build WHERE clause
        $where = " WHERE p.status = 1";
        $bindParams = [];
        $bindTypes = "";

        if ($search !== '') {
            $where .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
            $searchPattern = '%' . $search . '%';
            $bindParams[] = $searchPattern;
            $bindParams[] = $searchPattern;
            $bindTypes .= "ss";
        }

        if ($customer_type !== '') {
            $where .= " AND p.type_customer = ?";
            $bindParams[] = strtoupper($customer_type);
            $bindTypes .= "s";
        }

        // Count total records
        $countSql = "SELECT COUNT(*) as cnt FROM pelanggan p {$where}";
        $countStmt = $this->conn->prepare($countSql);
        if (!$countStmt) $this->json_error('Count prepare error: ' . $this->conn->error);

        if ($bindTypes) {
            call_user_func_array([$countStmt, 'bind_param'], array_merge([$bindTypes], $this->create_references($bindParams)));
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $totalRecords = $countResult['cnt'] ?? 0;
        $countStmt->close();

        // Query untuk ambil data member dengan poinnya
        $sql = "SELECT p.id,
                       p.kode,
                       p.nama,
                       p.kota,
                       p.type_customer,
                       CASE WHEN p.type_customer = 'MEMBER' THEN 'member' ELSE 'pelanggan' END as type,
                       COALESCE(SUM(cp.points), 0) as sisa_poin
                FROM pelanggan p
                LEFT JOIN customer_points cp ON p.id = cp.pelanggan_id 
                  AND (cp.expired_at IS NULL OR cp.expired_at > NOW())
                {$where}
                GROUP BY p.id, p.kode, p.nama, p.kota, p.type_customer
                ORDER BY p.nama ASC
                LIMIT ?, ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Query prepare error: ' . $this->conn->error);

        // Add LIMIT parameters
        $bindParams[] = $start;
        $bindParams[] = $length;
        $bindTypes .= "ii";

        if ($bindTypes) {
            call_user_func_array([$stmt, 'bind_param'], array_merge([$bindTypes], $this->create_references($bindParams)));
        }

        if (!$stmt->execute()) $this->json_error('Execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => (int)$row['id'],
                'kode' => $row['kode'],
                'nama' => $row['nama'],
                'kota' => $row['kota'],
                'type' => $row['type'],
                'sisa_poin' => (int)$row['sisa_poin']
            ];
        }

        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'total' => $totalRecords,
            'start' => $start,
            'loaded' => count($data),
            'has_more' => ($start + $length) < $totalRecords
        ]);
    }

    private function create_references(&$array)
    {
        $refs = [];
        foreach ($array as $key => $value) {
            $refs[$key] = &$array[$key];
        }
        return $refs;
    }

    public function get_tukar_poin()
    {
        if (!$this->conn) $this->json_error('Database connection failed');
        $this->ensure_reward_redemption_table();

        $draw = (int)($_POST['draw'] ?? 1);
        $start = max(0, (int)($_POST['start'] ?? 0));
        $length = (int)($_POST['length'] ?? 10);
        if ($length <= 0) {
            $length = 10;
        }

        $startDate = $this->validate_date($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $endDate = $this->validate_date($_POST['end_date'] ?? date('Y-m-d'));
        if (strtotime($startDate) > strtotime($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $searchPayload = $_POST['search'] ?? '';
        $search = is_array($searchPayload)
            ? trim((string)($searchPayload['value'] ?? ''))
            : trim((string)$searchPayload);

        $where = "WHERE DATE(rr.created_at) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = "ss";

        $searchSql = "";
        if ($search !== '') {
            $searchSql = " AND (
                p.nama LIKE ?
                OR p.kode LIKE ?
                OR ri.nama_reward LIKE ?
                OR rr.voucher_code LIKE ?
                OR rr.status LIKE ?
            )";
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like, $like);
            $types .= "sssss";
        }

        $countTotalSql = "SELECT COUNT(*) as total
                          FROM reward_redemption rr
                          {$where}";
        $countStmt = $this->conn->prepare($countTotalSql);
        if (!$countStmt) $this->json_error('Count prepare error: ' . $this->conn->error);
        $countStmt->bind_param("ss", $startDate, $endDate);
        if (!$countStmt->execute()) $this->json_error('Count execute error: ' . $countStmt->error);
        $recordsTotal = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $countFilteredSql = "SELECT COUNT(*) as total
                             FROM reward_redemption rr
                             LEFT JOIN pelanggan p ON p.id = rr.pelanggan_id
                             LEFT JOIN reward_items ri ON ri.id = rr.reward_id
                             {$where}{$searchSql}";
        $countFilteredStmt = $this->conn->prepare($countFilteredSql);
        if (!$countFilteredStmt) $this->json_error('Filtered count prepare error: ' . $this->conn->error);
        $countFilteredStmt->bind_param($types, ...$params);
        if (!$countFilteredStmt->execute()) $this->json_error('Filtered count execute error: ' . $countFilteredStmt->error);
        $recordsFiltered = (int)($countFilteredStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countFilteredStmt->close();

        $sql = "SELECT
                    rr.id,
                    rr.created_at,
                    rr.points_used,
                    rr.status,
                    rr.voucher_code,
                    COALESCE(p.nama, CONCAT('Pelanggan #', rr.pelanggan_id)) as nama_pelanggan,
                    p.kode as kode_pelanggan,
                    COALESCE(ri.nama_reward, CONCAT('Reward #', rr.reward_id)) as nama_hadiah
                FROM reward_redemption rr
                LEFT JOIN pelanggan p ON p.id = rr.pelanggan_id
                LEFT JOIN reward_items ri ON ri.id = rr.reward_id
                {$where}{$searchSql}
                ORDER BY rr.created_at DESC, rr.id DESC
                LIMIT ?, ?";

        $dataParams = array_merge($params, [$start, $length]);
        $dataTypes = $types . "ii";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) $this->json_error('Query prepare error: ' . $this->conn->error);
        $stmt->bind_param($dataTypes, ...$dataParams);
        if (!$stmt->execute()) $this->json_error('Query execute error: ' . $stmt->error);

        $result = $stmt->get_result();
        $data = [];
        $no = $start + 1;
        while ($row = $result->fetch_assoc()) {
            $customerName = trim((string)$row['nama_pelanggan']);
            if (!empty($row['kode_pelanggan'])) {
                $customerName .= ' (' . $row['kode_pelanggan'] . ')';
            }

            $data[] = [
                'no' => $no++,
                'tanggal' => $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-',
                'nama_pelanggan' => $customerName,
                'nama_hadiah' => $row['nama_hadiah'],
                'poin_digunakan' => (int)$row['points_used'],
                'status' => $row['status'],
                'voucher_code' => $row['voucher_code']
            ];
        }
        $stmt->close();

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    public function get_pelanggan_list()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $sql = "SELECT id, nama FROM pelanggan ORDER BY nama ASC";
        $result = $this->conn->query($sql);

        if (!$result) $this->json_error('Query error: ' . $this->conn->error);

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
    }

    public function get_rekap_point_member()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $search = trim($_POST['search'] ?? '');
        $min_points = max(0, (int)($_POST['min_points'] ?? 0));
        $sort_by = $_POST['sort_by'] ?? 'points_desc';

        // Build ORDER BY clause
        $order_clause = match ($sort_by) {
            'points_asc' => 'total_poin ASC',
            'name_asc' => 'p.nama ASC',
            'name_desc' => 'p.nama DESC',
            default => 'total_poin DESC'
        };

        $member_where = "(UPPER(COALESCE(p.type_customer, '')) = 'MEMBER'
            OR (
                p.kode LIKE 'MB%'
                AND NOT EXISTS (
                    SELECT 1
                    FROM pelanggan pm
                    WHERE pm.kode = p.kode
                      AND pm.id <> p.id
                      AND UPPER(COALESCE(pm.type_customer, '')) = 'MEMBER'
                )
            ))";

        $where = "WHERE p.status = 1 AND {$member_where}";
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $sql = "SELECT 
                    p.id as pelanggan_id,
                    p.kode,
                    p.nama,
                    p.alamat,
                    COALESCE(cp.poin_masuk, 0) as poin_total,
                    COALESCE(cp.poin_digunakan, 0) as poin_digunakan,
                    GREATEST(
                        COALESCE(cp.poin_masuk, 0) - COALESCE(cp.poin_digunakan, 0),
                        0
                    ) as total_poin
                FROM pelanggan p
                LEFT JOIN (
                    SELECT
                        pelanggan_id,
                        SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as poin_masuk,
                        SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as poin_digunakan
                    FROM customer_points
                    WHERE expired_at IS NULL OR expired_at > NOW() OR points < 0
                    GROUP BY pelanggan_id
                ) cp ON cp.pelanggan_id = p.id
                {$where}
                HAVING total_poin >= ?
                ORDER BY {$order_clause}";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'status' => 'failed',
                'msg' => 'Prepare error: ' . $this->conn->error
            ]);
            exit;
        }

        $params[] = $min_points;
        $types .= 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'pelanggan_id' => (int)$row['pelanggan_id'],
                'kode' => $row['kode'],
                'nama' => $row['nama'],
                'alamat' => $row['alamat'],
                'poin_total' => (int)$row['poin_total'],
                'poin_digunakan' => (int)$row['poin_digunakan'],
                'total_poin' => (int)$row['total_poin']
            ];
        }

        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'total' => count($data),
            'data' => $data
        ]);
    }

    public function get_member_point_history()
    {
        if (!$this->conn) $this->json_error('Database connection failed');

        $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : (isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0);

        if ($member_id <= 0) {
            echo json_encode([
                'status' => 'failed',
                'msg' => 'Invalid member ID'
            ]);
            exit;
        }

        $sql = "SELECT
                    cp.sale_id as transaksi_id,
                    cp.points as poin,
                    cp.created_at
                FROM customer_points cp
                WHERE cp.pelanggan_id = ?
                ORDER BY cp.created_at DESC
                LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'status' => 'failed',
                'msg' => 'Prepare error: ' . $this->conn->error
            ]);
            exit;
        }

        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'transaksi_id' => $row['transaksi_id'],
                'poin' => (int)$row['poin'],
                'created_at' => $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-'
            ];
        }

        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    }
}

// Handle AJAX requests
if (isset($_GET['f']) && is_direct_script(__FILE__)) {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Verify connection exists
    if (!$conn) {
        echo json_encode(['error' => 'Database not connected']);
        exit;
    }

    $laporan = new Laporan($conn);
    $function = request_action(null);

    if (!$function) {
        echo json_encode(['error' => 'Function parameter missing']);
        exit;
    }

    // Call the requested function
    switch ($function) {
        case 'get_laba_kotor':
            $laporan->get_laba_kotor();
            break;
        case 'get_profit_penjualan':
            $laporan->get_profit_penjualan();
            break;
        case 'get_omset_penjualan':
            $laporan->get_omset_penjualan();
            break;
        case 'get_laporan_sales':
            $laporan->get_laporan_sales();
            break;
        case 'get_laporan_ppn':
            $laporan->get_laporan_ppn();
            break;
        case 'get_produk_terlaris':
            $laporan->get_produk_terlaris();
            break;
        case 'get_setoran_kasir':
            $laporan->get_setoran_kasir();
            break;
        case 'get_setoran_summary':
            $laporan->get_setoran_summary();
            break;
        case 'save_setoran_kasir':
            $laporan->save_setoran_kasir();
            break;
        case 'get_kartu_poin':
            $laporan->get_kartu_poin();
            break;
        case 'get_rekap_poin':
            $laporan->get_rekap_poin();
            break;
        case 'get_tukar_poin':
            $laporan->get_tukar_poin();
            break;
        case 'get_member_cards':
            $laporan->get_member_cards();
            break;
        case 'get_pelanggan_list':
            $laporan->get_pelanggan_list();
            break;
        case 'get_rekap_point_member':
            $laporan->get_rekap_point_member();
            break;
        case 'get_member_point_history':
            $laporan->get_member_point_history();
            break;
        default:
            echo json_encode(['error' => 'Function not found: ' . $function]);
    }
    exit;
}
