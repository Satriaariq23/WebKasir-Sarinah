<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/DBConnection.php';
require_once __DIR__ . '/NumericInput.php';

/**
 * ==========================================================
 *  FILE: classes/Stok.php
 *  DESCRIPTION: Modul aman untuk Data Stok (Server-side)
 * ==========================================================
 */
class Stok extends DBConnection
{
    private function parseQuantity($value)
    {
        return NumericInput::quantity($value, 4);
    }

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Helper: bind params dinamis
     */
    private function bindParams($stmt, $types, $params)
    {
        if ($types === '' || empty($params)) return;
        $refs = [];
        $refs[] = $types;
        foreach ($params as $key => $val) {
            $refs[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    /**
     * ==========================
     *  GET DATA STOK
     * ==========================
     */
    public function get_data_stok()
    {
        try {
            // DataTables parameters
            $draw   = intval($_POST['draw'] ?? 0);
            $start  = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = trim($_POST['search']['value'] ?? '');

            // Filter kategori
            $filter_kategori = isset($_POST['filter_kategori']) && $_POST['filter_kategori'] !== ''
                ? intval($_POST['filter_kategori']) : null;

            // Column mapping untuk order
            $columnsMap = [
                0 => 'p.id',
                1 => 'p.barcode',
                2 => 'p.nama_produk',
                3 => 's.nama_satuan',
                4 => 'masuk',
                5 => 'keluar',
                6 => 'p.stok',
                7 => 'nilai_asset'
            ];

            $orderIndex = intval($_POST['order'][0]['column'] ?? 2);
            $orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
            $orderBy = $columnsMap[$orderIndex] ?? 'p.nama_produk';

            // =========================
            // Build WHERE + binding
            // =========================
            $where = [];
            $params = [];
            $types = '';

            if ($filter_kategori !== null) {
                $where[] = "p.id_kategori = ?";
                $params[] = $filter_kategori;
                $types .= 'i';
            }

            if ($searchValue !== '') {
                $where[] = "(p.barcode LIKE ? OR p.nama_produk LIKE ? OR k.nama_kategori LIKE ?)";
                $like = '%' . $searchValue . '%';
                $params = array_merge($params, [$like, $like, $like]);
                $types .= 'sss';
            }

            $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // =========================
            // Subquery total masuk / keluar dari transaksi resmi
            // =========================
            $subMasuk = "(
                COALESCE((SELECT SUM(pd.purc_qty * COALESCE(NULLIF(pd.isi, 0), 1))
                    FROM purc_det pd
                    INNER JOIN purc pu ON pu.purcid = pd.purc_id
                    WHERE pd.produk_id = p.id AND pu.purc_status <> 0), 0)
                +
                COALESCE((SELECT SUM(srd.retur_qty * COALESCE(NULLIF(srd.isi, 0), 1))
                    FROM sale_retur_det srd
                    INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                    WHERE srd.produk_id = p.id AND sr.post = 1), 0)
            )";
            $subKeluar = "(
                COALESCE((SELECT SUM(sd.sale_qty * COALESCE(NULLIF(sd.isi, 0), 1))
                    FROM sale_det sd
                    INNER JOIN sale sa ON sa.saleid = sd.sale_id
                    WHERE sd.produk_id = p.id AND sa.sale_status IN ('ACTIVE', 'LUNAS')), 0)
                +
                COALESCE((SELECT SUM(prd.retur_qty * COALESCE(NULLIF(prd.isi, 0), 1))
                    FROM purc_retur_det prd
                    INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                    WHERE prd.produk_id = p.id AND pr.status != 'DELETED'), 0)
            )";

            // =========================
            // recordsTotal
            // =========================
            $resTotal = $this->fetchOne("SELECT COUNT(*) AS cnt FROM produk");
            $recordsTotal = intval($resTotal['cnt'] ?? 0);

            // =========================
            // recordsFiltered
            // =========================
            $sqlFiltered = "
                SELECT COUNT(*) AS cnt
                FROM produk p
                LEFT JOIN kategori k ON k.id = p.id_kategori
                LEFT JOIN satuan s ON s.id = p.id_satuan
                $whereSQL
            ";
            $stmtFiltered = $this->prepare($sqlFiltered);
            if ($types !== '') $this->bindParams($stmtFiltered, $types, $params);
            $stmtFiltered->execute();
            $resFiltered = $stmtFiltered->get_result()->fetch_assoc();
            $recordsFiltered = intval($resFiltered['cnt'] ?? 0);
            $stmtFiltered->close();

            // =========================
            // Query utama (aman)
            // =========================
            $sql = "
                SELECT 
                    p.id,
                    p.barcode,
                    p.nama_produk,
                    k.nama_kategori,
                    s.nama_satuan AS satuan,
                    p.harga_beli,
                    p.stok AS sisa,
                    {$subMasuk} AS masuk,
                    {$subKeluar} AS keluar,
                    (p.stok - {$subMasuk} + {$subKeluar}) AS awal,
                    (p.stok * p.harga_beli) AS nilai_asset
                FROM produk p
                LEFT JOIN kategori k ON k.id = p.id_kategori
                LEFT JOIN satuan s ON s.id = p.id_satuan
                $whereSQL
                ORDER BY $orderBy $orderDir
                LIMIT ?, ?
            ";

            $stmt = $this->prepare($sql);
            $paramsExec = $params;
            $typesExec = $types . 'ii';
            $paramsExec[] = $start;
            $paramsExec[] = $length;

            $this->bindParams($stmt, $typesExec, $paramsExec);
            $stmt->execute();
            $result = $stmt->get_result();

            // =========================
            // Build data output
            // =========================
            $data = [];
            $no = $start + 1;
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'no' => $no++,
                    'barcode' => htmlspecialchars($row['barcode']),
                    'nama_produk' => htmlspecialchars($row['nama_produk']),
                    'nama_kategori' => htmlspecialchars($row['nama_kategori'] ?? '-'),
                    'satuan' => htmlspecialchars($row['satuan'] ?? '-'),
                    'awal' => floatval($row['awal']),
                    'masuk' => floatval($row['masuk']),
                    'keluar' => floatval($row['keluar']),
                    'sisa' => floatval($row['sisa']),
                    'nilai_asset' => floatval($row['nilai_asset'])
                ];
            }
            $stmt->close();

            // =========================
            // Response JSON
            // =========================
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
            exit;
        } catch (Exception $e) {
            error_log("[Stok.php] " . $e->getMessage());
            echo json_encode([
                'error' => 'Terjadi kesalahan saat mengambil data stok.',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * ==========================
     *  GET KARTU STOK
     * ==========================
     */
    public function get_kartu_stok()
    {
        try {
            $produk_id = intval($_POST['produk_id'] ?? 0);
            $tgl_awal = $_POST['tgl_awal'] ?? date('Y-m-01');
            $tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');

            if ($produk_id <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Produk tidak valid']);
                exit;
            }

            // Get produk info
            $stmt = $this->prepare("SELECT p.*, s.nama_satuan FROM produk p LEFT JOIN satuan s ON s.id = p.id_satuan WHERE p.id = ?");
            $stmt->bind_param("i", $produk_id);
            $stmt->execute();
            $produk = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$produk) {
                echo json_encode(['status' => 'error', 'msg' => 'Produk tidak ditemukan']);
                exit;
            }

            // Calculate saldo awal (stok sebelum tanggal awal)
            $sql = "SELECT 
                        COALESCE(SUM(CASE WHEN type = 'IN' THEN qty ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN type = 'OUT' THEN qty ELSE 0 END), 0) AS saldo
                    FROM (
                        SELECT purc_qty * COALESCE(NULLIF(pd.isi, 0), 1) AS qty, 'IN' AS type FROM purc_det pd
                        INNER JOIN purc pu ON pu.purcid = pd.purc_id
                        WHERE pd.produk_id = ? AND pu.purc_date < ? AND pu.purc_status <> 0
                        UNION ALL
                        SELECT sale_qty * COALESCE(NULLIF(sd.isi, 0), 1) AS qty, 'OUT' AS type FROM sale_det sd
                        INNER JOIN sale sa ON sa.saleid = sd.sale_id
                        WHERE sd.produk_id = ? AND sa.sale_date < ? AND sa.sale_status IN ('ACTIVE', 'LUNAS')
                        UNION ALL
                        SELECT srd.retur_qty * COALESCE(NULLIF(srd.isi, 0), 1) AS qty, 'IN' AS type FROM sale_retur_det srd
                        INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                        WHERE srd.produk_id = ? AND sr.retur_date < ? AND sr.post = 1
                        UNION ALL
                        SELECT prd.retur_qty * COALESCE(NULLIF(prd.isi, 0), 1) AS qty, 'OUT' AS type FROM purc_retur_det prd
                        INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                        WHERE prd.produk_id = ? AND pr.retur_date < ? AND pr.status != 'DELETED'
                    ) AS movements";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("isisisis", $produk_id, $tgl_awal, $produk_id, $tgl_awal, $produk_id, $tgl_awal, $produk_id, $tgl_awal);
            $stmt->execute();
            $saldoResult = $stmt->get_result()->fetch_assoc();
            $saldo_awal = floatval($saldoResult['saldo'] ?? 0);
            $stmt->close();

            // Get movements in date range
            $sql = "SELECT * FROM (
                        SELECT pu.purc_date AS tanggal,
                               CONVERT(pu.purc_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT(CONCAT('Pembelian dari ', COALESCE(sup.nama, '-')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               pd.purc_qty * COALESCE(NULLIF(pd.isi, 0), 1) AS masuk, 0 AS keluar
                        FROM purc_det pd
                        INNER JOIN purc pu ON pu.purcid = pd.purc_id
                        LEFT JOIN supplier sup ON sup.id = pu.supplier_id
                        WHERE pd.produk_id = ? AND pu.purc_date BETWEEN ? AND ? AND pu.purc_status <> 0
                        
                        UNION ALL
                        
                        SELECT sa.sale_date AS tanggal,
                               CONVERT(sa.sale_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT(CONCAT('Penjualan ke ', COALESCE(pel.nama, 'CASH')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               0 AS masuk, sd.sale_qty * COALESCE(NULLIF(sd.isi, 0), 1) AS keluar
                        FROM sale_det sd
                        INNER JOIN sale sa ON sa.saleid = sd.sale_id
                        LEFT JOIN pelanggan pel ON pel.id = sa.cust_id
                        WHERE sd.produk_id = ? AND sa.sale_date BETWEEN ? AND ? AND sa.sale_status IN ('ACTIVE', 'LUNAS')
                        
                        UNION ALL
                        
                        SELECT sr.retur_date AS tanggal,
                               CONVERT(sr.retur_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT('Retur Penjualan' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               srd.retur_qty * COALESCE(NULLIF(srd.isi, 0), 1) AS masuk, 0 AS keluar
                        FROM sale_retur_det srd
                        INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                        WHERE srd.produk_id = ? AND sr.retur_date BETWEEN ? AND ? AND sr.post = 1
                        
                        UNION ALL
                        
                        SELECT pr.retur_date AS tanggal,
                               CONVERT(pr.retur_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT('Retur Pembelian' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               0 AS masuk, prd.retur_qty * COALESCE(NULLIF(prd.isi, 0), 1) AS keluar
                        FROM purc_retur_det prd
                        INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                        WHERE prd.produk_id = ? AND pr.retur_date BETWEEN ? AND ? AND pr.status != 'DELETED'
                    ) AS movements
                    ORDER BY tanggal ASC, no_dokumen ASC";

            $stmt = $this->prepare($sql);
            $stmt->bind_param(
                "ississississ",
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir
            );
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'tanggal' => date('d/m/Y', strtotime($row['tanggal'])),
                    'no_dokumen' => $row['no_dokumen'],
                    'keterangan' => $row['keterangan'],
                    'masuk' => floatval($row['masuk']),
                    'keluar' => floatval($row['keluar'])
                ];
            }
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'produk' => $produk,
                'saldo_awal' => $saldo_awal,
                'data' => $data
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  PRINT KARTU STOK
     * ==========================
     */
    public function print_kartu_stok()
    {
        try {
            $produk_id = intval($_GET['produk_id'] ?? 0);
            $tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
            $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

            if ($produk_id <= 0) {
                die('<div class="alert alert-danger">Produk tidak valid</div>');
            }

            // Get produk info
            $stmt = $this->prepare("SELECT p.*, s.nama_satuan FROM produk p LEFT JOIN satuan s ON s.id = p.id_satuan WHERE p.id = ?");
            $stmt->bind_param("i", $produk_id);
            $stmt->execute();
            $produk = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$produk) {
                die('<div class="alert alert-danger">Produk tidak ditemukan</div>');
            }

            // Calculate saldo awal
            $sql = "SELECT 
                        COALESCE(SUM(CASE WHEN type = 'IN' THEN qty ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN type = 'OUT' THEN qty ELSE 0 END), 0) AS saldo
                    FROM (
                        SELECT purc_qty * COALESCE(NULLIF(pd.isi, 0), 1) AS qty, 'IN' AS type FROM purc_det pd
                        INNER JOIN purc pu ON pu.purcid = pd.purc_id
                        WHERE pd.produk_id = ? AND pu.purc_date < ? AND pu.purc_status <> 0
                        UNION ALL
                        SELECT sale_qty * COALESCE(NULLIF(sd.isi, 0), 1) AS qty, 'OUT' AS type FROM sale_det sd
                        INNER JOIN sale sa ON sa.saleid = sd.sale_id
                        WHERE sd.produk_id = ? AND sa.sale_date < ? AND sa.sale_status IN ('ACTIVE', 'LUNAS')
                        UNION ALL
                        SELECT srd.retur_qty * COALESCE(NULLIF(srd.isi, 0), 1) AS qty, 'IN' AS type FROM sale_retur_det srd
                        INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                        WHERE srd.produk_id = ? AND sr.retur_date < ? AND sr.post = 1
                        UNION ALL
                        SELECT prd.retur_qty * COALESCE(NULLIF(prd.isi, 0), 1) AS qty, 'OUT' AS type FROM purc_retur_det prd
                        INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                        WHERE prd.produk_id = ? AND pr.retur_date < ? AND pr.status != 'DELETED'
                    ) AS movements";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("isisisis", $produk_id, $tgl_awal, $produk_id, $tgl_awal, $produk_id, $tgl_awal, $produk_id, $tgl_awal);
            $stmt->execute();
            $saldoResult = $stmt->get_result()->fetch_assoc();
            $saldo_awal = floatval($saldoResult['saldo'] ?? 0);
            $stmt->close();

            // Get movements
            $sql = "SELECT * FROM (
                        SELECT pu.purc_date AS tanggal,
                               CONVERT(pu.purc_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT(CONCAT('Pembelian dari ', COALESCE(sup.nama, '-')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               pd.purc_qty * COALESCE(NULLIF(pd.isi, 0), 1) AS masuk, 0 AS keluar
                        FROM purc_det pd
                        INNER JOIN purc pu ON pu.purcid = pd.purc_id
                        LEFT JOIN supplier sup ON sup.id = pu.supplier_id
                        WHERE pd.produk_id = ? AND pu.purc_date BETWEEN ? AND ? AND pu.purc_status <> 0
                        
                        UNION ALL
                        
                        SELECT sa.sale_date AS tanggal,
                               CONVERT(sa.sale_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT(CONCAT('Penjualan ke ', COALESCE(pel.nama, 'CASH')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               0 AS masuk, sd.sale_qty * COALESCE(NULLIF(sd.isi, 0), 1) AS keluar
                        FROM sale_det sd
                        INNER JOIN sale sa ON sa.saleid = sd.sale_id
                        LEFT JOIN pelanggan pel ON pel.id = sa.cust_id
                        WHERE sd.produk_id = ? AND sa.sale_date BETWEEN ? AND ? AND sa.sale_status IN ('ACTIVE', 'LUNAS')
                        
                        UNION ALL
                        
                        SELECT sr.retur_date AS tanggal,
                               CONVERT(sr.retur_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT('Retur Penjualan' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               srd.retur_qty * COALESCE(NULLIF(srd.isi, 0), 1) AS masuk, 0 AS keluar
                        FROM sale_retur_det srd
                        INNER JOIN sale_retur sr ON sr.returid = srd.retur_id
                        WHERE srd.produk_id = ? AND sr.retur_date BETWEEN ? AND ? AND sr.post = 1
                        
                        UNION ALL
                        
                        SELECT pr.retur_date AS tanggal,
                               CONVERT(pr.retur_invoice USING utf8mb4) COLLATE utf8mb4_unicode_ci AS no_dokumen,
                               CONVERT('Retur Pembelian' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS keterangan,
                               0 AS masuk, prd.retur_qty * COALESCE(NULLIF(prd.isi, 0), 1) AS keluar
                        FROM purc_retur_det prd
                        INNER JOIN purc_retur pr ON pr.returid = prd.retur_id
                        WHERE prd.produk_id = ? AND pr.retur_date BETWEEN ? AND ? AND pr.status != 'DELETED'
                    ) AS movements
                    ORDER BY tanggal ASC, no_dokumen ASC";

            $stmt = $this->prepare($sql);
            $stmt->bind_param(
                "ississississ",
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir,
                $produk_id,
                $tgl_awal,
                $tgl_akhir
            );
            $stmt->execute();
            $result = $stmt->get_result();

            $saldo = $saldo_awal;
?>
            <!DOCTYPE html>
            <html>

            <head>
                <meta charset="utf-8">
                <title>Kartu Stok - <?php echo htmlspecialchars($produk['nama_produk']); ?></title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 20px;
                    }

                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                    }

                    .info {
                        margin-bottom: 15px;
                        border-bottom: 1px solid #ccc;
                        padding-bottom: 10px;
                    }

                    .info div {
                        margin: 5px 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }

                    th,
                    td {
                        border: 1px solid #000;
                        padding: 8px;
                        text-align: left;
                    }

                    th {
                        background-color: #f0f0f0;
                        font-weight: bold;
                    }

                    .text-center {
                        text-align: center;
                    }

                    .text-right {
                        text-align: right;
                    }

                    .number {
                        text-align: right;
                    }

                    @media print {
                        body {
                            margin: 0;
                        }
                    }
                </style>
            </head>

            <body>
                <div class="header">
                    <h2>KARTU STOK BARANG</h2>
                </div>

                <div class="info">
                    <div><strong>Kode:</strong> <?php echo htmlspecialchars($produk['barcode'] ?? '-'); ?></div>
                    <div><strong>Nama Barang:</strong> <?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                    <div><strong>Satuan:</strong> <?php echo htmlspecialchars($produk['nama_satuan'] ?? '-'); ?></div>
                    <div><strong>Periode:</strong> <?php echo date('d/m/Y', strtotime($tgl_awal)); ?> s/d <?php echo date('d/m/Y', strtotime($tgl_akhir)); ?></div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 5%">No</th>
                            <th style="width: 12%">Tanggal</th>
                            <th style="width: 15%">No. Dokumen</th>
                            <th>Keterangan</th>
                            <th class="text-right" style="width: 10%">Masuk</th>
                            <th class="text-right" style="width: 10%">Keluar</th>
                            <th class="text-right" style="width: 10%">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">-</td>
                            <td><?php echo date('d/m/Y', strtotime($tgl_awal)); ?></td>
                            <td>-</td>
                            <td><em>Saldo Awal</em></td>
                            <td class="text-right">-</td>
                            <td class="text-right">-</td>
                            <td class="text-right"><strong><?php echo number_format($saldo_awal, 4, ',', '.'); ?></strong></td>
                        </tr>
                        <?php
                        $no = 1;
                        while ($row = $result->fetch_assoc()) {
                            $masuk = floatval($row['masuk'] ?? 0);
                            $keluar = floatval($row['keluar'] ?? 0);
                            $saldo = $saldo + $masuk - $keluar;
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                <td><?php echo htmlspecialchars($row['no_dokumen'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                                <td class="text-right"><?php echo $masuk > 0 ? number_format($masuk, 4, ',', '.') : '-'; ?></td>
                                <td class="text-right"><?php echo $keluar > 0 ? number_format($keluar, 4, ',', '.') : '-'; ?></td>
                                <td class="text-right"><strong><?php echo number_format($saldo, 4, ',', '.'); ?></strong></td>
                            </tr>
                        <?php
                        }
                        if ($result->num_rows === 0) {
                        ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada transaksi pada periode ini</td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px; text-align: right;">
                    <p>Dicetak pada: <?php echo date('d/m/Y H:i'); ?></p>
                </div>

                <script>
                    window.print();
                </script>
            </body>

            </html>
<?php
            $stmt->close();
        } catch (Exception $e) {
            die('<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
        }
    }

    /**
     * ==========================
     *  GET PRODUCTS FOR OPNAME
     * ==========================
     */
    public function get_products_for_opname()
    {
        try {
            $kategori_id = intval($_POST['kategori_id'] ?? 0);
            $search = trim($_POST['q'] ?? $_POST['search'] ?? '');
            $limit = min(25, max(1, intval($_POST['limit'] ?? 10)));

            if (strlen($search) < 2) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [],
                    'msg' => 'Ketik minimal 2 huruf nama barang atau barcode'
                ]);
                exit;
            }

            $where = "WHERE p.status = 1";
            $params = [];
            $types = "";

            if ($kategori_id > 0) {
                $where .= " AND p.id_kategori = ?";
                $params[] = $kategori_id;
                $types .= "i";
            }

            $keywords = preg_split('/\s+/', $search);
            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                $where .= " AND (p.nama_produk LIKE ? OR p.barcode LIKE ?)";
                $like = '%' . $keyword . '%';
                $params[] = $like;
                $params[] = $like;
                $types .= "ss";
            }

            $sql = "SELECT p.id, p.barcode, p.nama_produk, p.stok, s.nama_satuan
                    FROM produk p
                    LEFT JOIN satuan s ON s.id = p.id_satuan
                    $where
                    ORDER BY p.nama_produk ASC
                    LIMIT ?";
            $params[] = $limit;
            $types .= "i";

            $stmt = $this->prepare($sql);
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  GET OPNAME LIST
     * ==========================
     */
    public function get_opname_list()
    {
        try {
            $tgl_awal = $_POST['tgl_awal'] ?? date('Y-m-01');
            $tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');
            $status = $_POST['status'] ?? '';

            $where = "WHERE so.opname_date BETWEEN ? AND ?";
            $params = [$tgl_awal, $tgl_akhir];
            $types = "ss";

            if (!empty($status)) {
                $where .= " AND so.status = ?";
                $params[] = $status;
                $types .= "s";
            }

            $sql = "SELECT so.*, 
                        (SELECT COUNT(*) FROM stok_opname_det WHERE opname_id = so.id) AS total_items
                    FROM stok_opname so
                    $where
                    ORDER BY so.opname_date DESC, so.id DESC";

            $stmt = $this->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'id' => $row['id'],
                    'opname_date' => date('d/m/Y', strtotime($row['opname_date'])),
                    'opname_no' => $row['opname_no'],
                    'keterangan' => $row['keterangan'],
                    'status' => $row['status'],
                    'total_items' => $row['total_items']
                ];
            }
            $stmt->close();

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  SAVE OPNAME
     * ==========================
     */
    public function save_opname()
    {
        try {
            $this->connect();
            $this->conn->begin_transaction();

            $opname_id = intval($_POST['opname_id'] ?? 0);
            $opname_date = $_POST['opname_date'] ?? date('Y-m-d');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $items = $_POST['items'] ?? [];
            $update_stock = intval($_POST['update_stock'] ?? 0) === 1;

            if (empty($items)) {
                throw new Exception("Minimal harus ada 1 item");
            }

            if ($opname_id > 0) {
                // UPDATE
                $statusStmt = $this->prepare("SELECT status FROM stok_opname WHERE id = ? FOR UPDATE");
                $statusStmt->bind_param("i", $opname_id);
                $statusStmt->execute();
                $existingOpname = $statusStmt->get_result()->fetch_assoc();
                $statusStmt->close();
                if (!$existingOpname) {
                    throw new Exception("Stok opname tidak ditemukan");
                }
                if ($existingOpname['status'] !== 'DRAFT') {
                    throw new Exception("Stok opname yang sudah diposting tidak dapat diedit");
                }
                $sql = "UPDATE stok_opname SET opname_date = ?, keterangan = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->prepare($sql);
                $stmt->bind_param("ssi", $opname_date, $keterangan, $opname_id);
                $stmt->execute();
                $stmt->close();

                // Delete old detail
                $this->query("DELETE FROM stok_opname_det WHERE opname_id = $opname_id");
            } else {
                // INSERT - Generate no
                $prefix = "SO-" . date('Ymd') . "-";
                $sql = "SELECT opname_no FROM stok_opname WHERE opname_no LIKE ? ORDER BY id DESC LIMIT 1";
                $stmt = $this->prepare($sql);
                $searchPrefix = $prefix . '%';
                $stmt->bind_param("s", $searchPrefix);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($result) {
                    $lastNo = intval(substr($result['opname_no'], -4));
                    $opname_no = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $opname_no = $prefix . "0001";
                }

                $user_id = $_SESSION['login_id'] ?? 1;

                $sql = "INSERT INTO stok_opname (opname_date, opname_no, keterangan, user_id, status, created_at)
                        VALUES (?, ?, ?, ?, 'DRAFT', NOW())";
                $stmt = $this->prepare($sql);
                $stmt->bind_param("sssi", $opname_date, $opname_no, $keterangan, $user_id);
                $stmt->execute();
                $opname_id = $this->conn->insert_id;
                $stmt->close();
            }

            // Insert detail
            $sql = "INSERT INTO stok_opname_det (opname_id, produk_id, stok_sistem, stok_fisik, keterangan)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->prepare($sql);

            $seenProducts = [];
            foreach ($items as $item) {
                $produk_id = intval($item['produk_id']);
                $stok_fisik = $this->parseQuantity($item['stok_fisik'] ?? 0);
                $ket = trim($item['keterangan'] ?? '');

                if ($produk_id <= 0) {
                    throw new Exception("Produk stok opname tidak valid");
                }
                if ($stok_fisik < 0) {
                    throw new Exception("Stok fisik tidak boleh minus");
                }
                if (isset($seenProducts[$produk_id])) {
                    throw new Exception("Produk yang sama tidak boleh dicatat dua kali dalam satu stok opname");
                }
                $seenProducts[$produk_id] = true;

                // Get current stok sistem
                $stokStmt = $this->prepare("SELECT stok FROM produk WHERE id = ?");
                $stokStmt->bind_param("i", $produk_id);
                $stokStmt->execute();
                $stokResult = $stokStmt->get_result()->fetch_assoc();
                if (!$stokResult) {
                    $stokStmt->close();
                    throw new Exception("Produk stok opname tidak ditemukan");
                }
                $stok_sistem = floatval($stokResult['stok'] ?? 0);
                $stokStmt->close();

                $stmt->bind_param("iidds", $opname_id, $produk_id, $stok_sistem, $stok_fisik, $ket);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menyimpan detail stok opname: " . $stmt->error);
                }
            }
            $stmt->close();

            if ($update_stock) {
                $stmtUpdateStok = $this->prepare("UPDATE produk SET stok = stok + (? - ?) WHERE id = ?");
                foreach ($items as $item) {
                    $produk_id = intval($item['produk_id']);
                    $stok_fisik = $this->parseQuantity($item['stok_fisik'] ?? 0);
                    $stok_sistem = 0.0;
                    $detailStockStmt = $this->prepare("SELECT stok_sistem FROM stok_opname_det WHERE opname_id = ? AND produk_id = ? LIMIT 1");
                    $detailStockStmt->bind_param("ii", $opname_id, $produk_id);
                    $detailStockStmt->execute();
                    $detailStock = $detailStockStmt->get_result()->fetch_assoc();
                    $detailStockStmt->close();
                    $stok_sistem = (float)($detailStock['stok_sistem'] ?? 0);
                    $stmtUpdateStok->bind_param("ddi", $stok_fisik, $stok_sistem, $produk_id);
                    if (!$stmtUpdateStok->execute()) {
                        throw new Exception("Gagal menerapkan selisih stok opname: " . $stmtUpdateStok->error);
                    }
                }
                $stmtUpdateStok->close();

                $stmtStatus = $this->prepare("UPDATE stok_opname SET status = 'POSTED', updated_at = NOW() WHERE id = ?");
                $stmtStatus->bind_param("i", $opname_id);
                $stmtStatus->execute();
                $stmtStatus->close();
            }

            $this->conn->commit();

            echo json_encode([
                'status' => 'success',
                'id' => $opname_id,
                'msg' => $update_stock
                    ? 'Stok opname berhasil disimpan dan stok barang diperbarui'
                    : 'Stok opname berhasil disimpan'
            ]);
            exit;
        } catch (Throwable $e) {
            if ($this->conn) {
                $this->conn->rollback();
            }
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  GET OPNAME DETAIL
     * ==========================
     */
    public function get_opname_detail()
    {
        try {
            $id = intval($_GET['id'] ?? 0);

            $stmt = $this->prepare("SELECT * FROM stok_opname WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$header) {
                echo json_encode(['status' => 'error', 'msg' => 'Data tidak ditemukan']);
                exit;
            }

            $sql = "SELECT sod.*, p.barcode, p.nama_produk
                    FROM stok_opname_det sod
                    LEFT JOIN produk p ON p.id = sod.produk_id
                    WHERE sod.opname_id = ?";
            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode(['status' => 'success', 'header' => $header, 'detail' => $detail]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  POST OPNAME
     * ==========================
     */
    public function post_opname()
    {
        try {
            $this->connect();
            $this->conn->begin_transaction();

            $id = intval($_POST['id'] ?? 0);

            $headerStmt = $this->prepare("SELECT status FROM stok_opname WHERE id = ? FOR UPDATE");
            $headerStmt->bind_param("i", $id);
            $headerStmt->execute();
            $header = $headerStmt->get_result()->fetch_assoc();
            $headerStmt->close();
            if (!$header) {
                throw new Exception("Stok opname tidak ditemukan");
            }
            if ($header['status'] !== 'DRAFT') {
                throw new Exception("Stok opname sudah pernah diposting");
            }

            // Get detail
            $stmt = $this->prepare("SELECT * FROM stok_opname_det WHERE opname_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (!$details) {
                throw new Exception("Stok opname tidak memiliki detail");
            }

            // Update stok produk
            foreach ($details as $d) {
                $stmt = $this->prepare("UPDATE produk SET stok = stok + (? - ?) WHERE id = ?");
                $stmt->bind_param("ddi", $d['stok_fisik'], $d['stok_sistem'], $d['produk_id']);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menerapkan selisih stok opname: " . $stmt->error);
                }
                $stmt->close();
            }

            // Update status
            $stmt = $this->prepare("UPDATE stok_opname SET status = 'POSTED', updated_at = NOW() WHERE id = ? AND status = 'DRAFT'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception("Stok opname gagal diposting atau sudah diproses pengguna lain");
            }
            $stmt->close();

            $this->conn->commit();

            echo json_encode(['status' => 'success', 'msg' => 'Stok opname berhasil diposting']);
            exit;
        } catch (Throwable $e) {
            if ($this->conn) {
                $this->conn->rollback();
            }
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  DELETE OPNAME
     * ==========================
     */
    public function delete_opname()
    {
        try {
            $this->connect();
            $this->conn->begin_transaction();

            $id = intval($_POST['id'] ?? 0);

            // Check status
            $stmt = $this->prepare("SELECT status FROM stok_opname WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result && $result['status'] === 'POSTED') {
                throw new Exception("Stok opname yang sudah diposting tidak dapat dihapus");
            }

            // Delete detail
            $this->query("DELETE FROM stok_opname_det WHERE opname_id = $id");

            // Delete header
            $stmt = $this->prepare("DELETE FROM stok_opname WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();

            echo json_encode(['status' => 'success', 'msg' => 'Stok opname berhasil dihapus']);
            exit;
        } catch (Throwable $e) {
            if ($this->conn) {
                $this->conn->rollback();
            }
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  GET PRODUCTS LIST (FOR SELECT2)
     * ==========================
     */
    public function get_products_list()
    {
        try {
            $search = trim($_POST['q'] ?? $_GET['q'] ?? '');
            $page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
            $limit = 50;
            $offset = ($page - 1) * $limit;

            $sql = "SELECT id, barcode, nama_produk, 
                        CONCAT(nama_produk, ' (', barcode, ')') AS text
                    FROM produk
                    WHERE status = 1";

            $params = [];
            $types = "";

            if (!empty($search)) {
                $keywords = preg_split('/\s+/', $search);
                foreach ($keywords as $keyword) {
                    if ($keyword === '') {
                        continue;
                    }
                    $sql .= " AND (nama_produk LIKE ? OR barcode LIKE ?)";
                    $like = '%' . $keyword . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $types .= "ss";
                }
            }

            $sql .= " ORDER BY nama_produk ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            $stmt = $this->prepare($sql);
            if (!empty($types)) {
                $this->bindParams($stmt, $types, $params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'id' => $row['id'],
                    'text' => $row['text']
                ];
            }
            $stmt->close();

            echo json_encode([
                'data' => $data,
                'results' => $data,
                'pagination' => [
                    'more' => count($data) === $limit
                ]
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * ==========================
     *  GET PRODUCT SATUAN
     * ==========================
     */
    public function get_product_satuan()
    {
        try {
            $produk_id = intval($_POST['produk_id'] ?? 0);

            $sql = "SELECT s.nama_satuan FROM produk p
                    LEFT JOIN satuan s ON s.id = p.id_satuan
                    WHERE p.id = ?";

            $stmt = $this->prepare($sql);
            $stmt->bind_param("i", $produk_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'satuan' => $result['nama_satuan'] ?? '-'
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
    $Stok = new Stok();
    $action = request_action('none');

    // List of actions that don't return JSON (print functions)
    $print_actions = ['print_kartu_stok'];

    if (in_array($action, $print_actions)) {
        // Remove JSON header for print functions
        header_remove('Content-Type');
    }

    switch ($action) {
        case 'get_data_stok':
            $Stok->get_data_stok();
            break;
        case 'get_kartu_stok':
            $Stok->get_kartu_stok();
            break;
        case 'print_kartu_stok':
            $Stok->print_kartu_stok();
            break;
        case 'get_products_for_opname':
            $Stok->get_products_for_opname();
            break;
        case 'get_opname_list':
            $Stok->get_opname_list();
            break;
        case 'save_opname':
            $Stok->save_opname();
            break;
        case 'get_opname_detail':
            $Stok->get_opname_detail();
            break;
        case 'post_opname':
            $Stok->post_opname();
            break;
        case 'delete_opname':
            $Stok->delete_opname();
            break;
        case 'get_products_list':
            $Stok->get_products_list();
            break;
        case 'get_product_satuan':
            $Stok->get_product_satuan();
            break;
        default:
            http_response_code(404);
            echo json_encode(['status' => 'failed', 'msg' => 'Action not found']);
            break;
    }
} catch (Exception $e) {
    error_log('[STOK ROUTER ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
}
exit(0);
}
