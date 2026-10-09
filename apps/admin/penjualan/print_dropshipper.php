<?php
require_once('../../config.php');
require_login(false);

$saleid = (int)($_GET['id'] ?? 0);
if ($saleid <= 0) {
    exit('ID transaksi tidak valid');
}

$stmt = $conn->prepare("
    SELECT s.saleid, s.sale_date, s.sale_invoice, s.sale_channel, s.sale_nofak, s.catatan, s.user_id,
           p.nama AS pelanggan_nama, p.alamat AS pelanggan_alamat, p.kota AS pelanggan_kota,
           u.firstname AS sales_name
    FROM sale s
    LEFT JOIN pelanggan p ON p.id = s.cust_id
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.saleid = ? AND s.sale_status != 'DELETED'
    LIMIT 1
");
$stmt->bind_param('i', $saleid);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    exit('Transaksi tidak ditemukan');
}

$currentLevel = current_user_level();
$currentUserId = (int)($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
$isOwnedBySales = $currentLevel === 3 && (int)$sale['user_id'] === $currentUserId;
$isSupervisorOrAdmin = in_array($currentLevel, [1, 4], true);

if (!in_array($sale['sale_channel'], ['sales', 'online'], true)) {
    http_response_code(400);
    exit('Dropshipper hanya tersedia untuk transaksi Sales atau Sales Online');
}

if (!$isSupervisorOrAdmin && !$isOwnedBySales) {
    http_response_code(403);
    exit('Anda tidak memiliki akses untuk mencetak dropshipper transaksi ini');
}

$detailStmt = $conn->prepare("
    SELECT sd.sale_qty, p.barcode, p.nama_produk, st.nama_satuan
    FROM sale_det sd
    JOIN produk p ON p.id = sd.produk_id
    LEFT JOIN satuan st ON st.id = p.id_satuan
    WHERE sd.sale_id = ?
    ORDER BY sd.noid ASC
");
$detailStmt->bind_param('i', $saleid);
$detailStmt->execute();
$details = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$detailStmt->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dropshipper <?= e($sale['sale_invoice']) ?></title>
    <style>
        @page { size: 150mm 215mm; margin: 6mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
        .sheet { width: 138mm; margin: 0 auto; }
        .header { text-align: center; border-bottom: 1px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .title { font-size: 16px; font-weight: bold; letter-spacing: .5px; }
        .meta { display: grid; grid-template-columns: 28mm 1fr; gap: 2px 6px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 4px; vertical-align: top; }
        th { background: #eee; }
        .right { text-align: right; }
        .footer { display: flex; justify-content: space-between; margin-top: 20mm; text-align: center; }
        .sign { width: 42mm; }
        .line { border-top: 1px solid #111; margin-top: 18mm; padding-top: 3px; }
        .no-print { margin: 10px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print Dropshipper</button>
        <button onclick="window.close()">Tutup</button>
    </div>
    <div class="sheet">
        <div class="header">
            <div class="title">SURAT JALAN DROPSHIPPER</div>
            <div><?= e($_settings->info('name') ?: 'CV. Sarinah Logam') ?></div>
        </div>

        <div class="meta">
            <div>No Invoice</div><div>: <?= e($sale['sale_invoice']) ?></div>
            <div>Tanggal</div><div>: <?= e(date('d-m-Y', strtotime($sale['sale_date']))) ?></div>
            <div>Sales</div><div>: <?= e($sale['sales_name'] ?: '-') ?></div>
            <div>Penerima</div><div>: <?= e($sale['pelanggan_nama'] ?: '-') ?></div>
            <div>Alamat</div><div>: <?= e(trim(($sale['pelanggan_alamat'] ?: '') . ' ' . ($sale['pelanggan_kota'] ?: ''))) ?></div>
            <div>Catatan</div><div>: <?= e($sale['catatan'] ?: '-') ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:8mm">No</th>
                    <th style="width:28mm">Kode</th>
                    <th>Nama Barang</th>
                    <th style="width:18mm">Qty</th>
                    <th style="width:22mm">Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $i => $item): ?>
                    <tr>
                        <td class="right"><?= $i + 1 ?></td>
                        <td><?= e($item['barcode']) ?></td>
                        <td><?= e($item['nama_produk']) ?></td>
                        <td class="right"><?= e(rtrim(rtrim(number_format((float)$item['sale_qty'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td><?= e($item['nama_satuan'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="sign"><div>Pengirim</div><div class="line">Admin/Sales</div></div>
            <div class="sign"><div>Penerima</div><div class="line">Nama & TTD</div></div>
        </div>
    </div>
</body>
</html>
