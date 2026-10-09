<?php
require_once(__DIR__ . '/../../config.php');
require_login(false);

$purcid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($purcid <= 0) {
    die('ID pembelian tidak valid');
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function qty_format($value)
{
    $value = (float)$value;
    if (floor($value) == $value) {
        return number_format($value, 0, ',', '.');
    }

    return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
}

function tanggal_indo($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    $time = strtotime((string)$date);
    if ($time === false) {
        return '-';
    }

    return date('d/m/Y', $time);
}

$stmt = $conn->prepare("
    SELECT
        p.purcid,
        p.purc_date,
        p.purc_invoice,
        p.purc_nofak,
        p.purc_datefak,
        p.purc_jt,
        p.purc_disc,
        p.ppn AS purc_ppn,
        p.purc_total,
        COALESCE((
            SELECT SUM(pr.retur_total)
            FROM purc_retur pr
            WHERE pr.purc_id = p.purcid AND pr.status = 'ACTIVE'
        ), 0) AS retur_total_active,
        p.catatan,
        p.bayar_id,
        p.purc_status,
        s.nama AS supplier_name,
        s.alamat AS supplier_address,
        s.kota AS supplier_city,
        s.telpon AS supplier_phone
    FROM purc p
    LEFT JOIN supplier s ON s.id = p.supplier_id
    WHERE p.purcid = ? AND p.purc_status <> 0
");
$stmt->bind_param('i', $purcid);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    die('Data pembelian tidak ditemukan atau sudah dihapus');
}

$stmt = $conn->prepare("
    SELECT
        pd.noid,
        pd.produk_id,
        pd.purc_qty,
        pd.purc_harga,
        pd.ppn,
        pd.isi,
        pr.barcode,
        pr.nama_produk,
        st.nama_satuan
    FROM purc_det pd
    LEFT JOIN produk pr ON pr.id = pd.produk_id
    LEFT JOIN satuan st ON st.id = pr.id_satuan
    WHERE pd.purc_id = ?
    ORDER BY pd.noid ASC
");
$stmt->bind_param('i', $purcid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$settings = [];
$res = $conn->query("SELECT meta_field, meta_value FROM system_info");
while ($row = $res->fetch_assoc()) {
    $settings[$row['meta_field']] = $row['meta_value'];
}

$company_name = $settings['name'] ?? 'CV. Sarinah Logam';
$company_address = $settings['address'] ?? '';
$company_phone = $settings['contact'] ?? '';
$jenis_transaksi = ((int)$purchase['bayar_id'] === 2) ? 'Kredit' : 'Tunai';
$status_label = ((int)$purchase['purc_status'] === 2) ? 'POSTED' : 'AKTIF';

$subtotal = 0;
$total_ppn = 0;
$total_qty = 0;
foreach ($items as $item) {
    $qty = (float)$item['purc_qty'];
    $harga = (float)$item['purc_harga'];
    $subtotal += $qty * $harga;
    $total_ppn += (float)$item['ppn'];
    $total_qty += $qty;
}

$diskon = (float)$purchase['purc_disc'];
$header_ppn = (float)$purchase['purc_ppn'];
if ($total_ppn <= 0 && $header_ppn > 0) {
    $total_ppn = $header_ppn;
}

$grand_total = (float)$purchase['purc_total'];
$retur_total = (float)$purchase['retur_total_active'];
$net_total = $grand_total - $retur_total;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Invoice Pembelian <?= h($purchase['purc_invoice']) ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                margin: 0;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0 auto;
            padding: 16px;
            max-width: 960px;
            color: #111827;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .sheet {
            background: #fff;
            padding: 24px;
            border: 1px solid #d7dce5;
        }

        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding: 10px 0;
            background: #f5f7fb;
        }

        .toolbar button {
            border: 0;
            border-radius: 4px;
            padding: 9px 14px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
        }

        .btn-print {
            background: #16a34a;
        }

        .btn-close {
            background: #dc2626;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .company h1 {
            margin: 0 0 6px;
            font-size: 22px;
            text-transform: uppercase;
        }

        .company div,
        .meta div {
            color: #374151;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h2 {
            margin: 0 0 8px;
            font-size: 20px;
            letter-spacing: 0;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            color: #fff;
            background: #2563eb;
            font-weight: 700;
        }

        .badge.cash {
            background: #16a34a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 18px;
        }

        .info-box {
            border: 1px solid #d7dce5;
            padding: 12px;
        }

        .info-box h3 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-box td {
            padding: 3px 0;
            vertical-align: top;
        }

        .info-box td:first-child {
            width: 120px;
            color: #4b5563;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items th {
            background: #eef2f7;
            border: 1px solid #cfd6e2;
            padding: 8px 6px;
            text-align: center;
            font-size: 11px;
        }

        .items td {
            border: 1px solid #d7dce5;
            padding: 7px 6px;
            vertical-align: top;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .totals {
            margin-top: 14px;
            display: flex;
            justify-content: flex-end;
        }

        .totals table {
            width: 340px;
            border-collapse: collapse;
        }

        .totals td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .totals tr:last-child td {
            border-top: 2px solid #111827;
            border-bottom: 3px double #111827;
            font-weight: 700;
            font-size: 14px;
        }

        .note {
            margin-top: 12px;
            color: #374151;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 42px;
            text-align: center;
        }

        .line {
            border-bottom: 1px solid #111827;
            height: 48px;
            margin-bottom: 6px;
        }

        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #d7dce5;
            color: #6b7280;
            text-align: center;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button class="btn-print" type="button" onclick="window.print()">Print</button>
        <button class="btn-close" type="button" onclick="window.close()">Close</button>
    </div>

    <div class="sheet">
        <div class="header">
            <div class="company">
                <h1><?= h($company_name) ?></h1>
                <div><?= h($company_address) ?></div>
                <div><?= h($company_phone) ?></div>
            </div>
            <div class="doc-title">
                <h2>INVOICE PEMBELIAN</h2>
                <div class="meta">
                    <div><strong><?= h($purchase['purc_invoice']) ?></strong></div>
                    <div>Status: <?= h($status_label) ?></div>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>Informasi Faktur</h3>
                <table>
                    <tr>
                        <td>Tanggal</td>
                        <td>: <?= h(tanggal_indo($purchase['purc_date'])) ?></td>
                    </tr>
                    <tr>
                        <td>No. Faktur Supplier</td>
                        <td>: <?= h($purchase['purc_nofak'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td>Tgl. Faktur Supplier</td>
                        <td>: <?= h(tanggal_indo($purchase['purc_datefak'])) ?></td>
                    </tr>
                    <tr>
                        <td>Jenis Transaksi</td>
                        <td>: <span class="badge <?= (int)$purchase['bayar_id'] === 1 ? 'cash' : '' ?>"><?= h($jenis_transaksi) ?></span></td>
                    </tr>
                    <?php if ((int)$purchase['bayar_id'] === 2): ?>
                    <tr>
                        <td>Jatuh Tempo</td>
                        <td>: <?= h(tanggal_indo($purchase['purc_jt'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="info-box">
                <h3>Supplier</h3>
                <table>
                    <tr>
                        <td>Nama</td>
                        <td>: <strong><?= h($purchase['supplier_name'] ?: '-') ?></strong></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>: <?= h($purchase['supplier_address'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td>Kota</td>
                        <td>: <?= h($purchase['supplier_city'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td>Telepon</td>
                        <td>: <?= h($purchase['supplier_phone'] ?: '-') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 36px;">NO</th>
                    <th style="width: 90px;">KODE</th>
                    <th>NAMA BARANG</th>
                    <th style="width: 70px;">QTY</th>
                    <th style="width: 70px;">SATUAN</th>
                    <th style="width: 100px;">HARGA</th>
                    <th style="width: 90px;">PPN</th>
                    <th style="width: 110px;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="center">Tidak ada item pembelian</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $index => $item): ?>
                        <?php $line_subtotal = (float)$item['purc_qty'] * (float)$item['purc_harga']; ?>
                        <tr>
                            <td class="center"><?= $index + 1 ?></td>
                            <td class="center"><?= h($item['barcode'] ?: '-') ?></td>
                            <td><?= h($item['nama_produk'] ?: '-') ?></td>
                            <td class="right"><?= h(qty_format($item['purc_qty'])) ?></td>
                            <td class="center"><?= h($item['nama_satuan'] ?: '-') ?></td>
                            <td class="right"><?= h(rupiah($item['purc_harga'])) ?></td>
                            <td class="right"><?= h(rupiah($item['ppn'])) ?></td>
                            <td class="right"><?= h(rupiah($line_subtotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal (<?= h(qty_format($total_qty)) ?> item)</td>
                    <td class="right"><?= h(rupiah($subtotal)) ?></td>
                </tr>
                <tr>
                    <td>Diskon Faktur</td>
                    <td class="right">-<?= h(rupiah($diskon)) ?></td>
                </tr>
                <tr>
                    <td>PPN</td>
                    <td class="right"><?= h(rupiah($total_ppn)) ?></td>
                </tr>
                <?php if ($retur_total > 0): ?>
                <tr>
                    <td>Retur Pembelian Aktif</td>
                    <td class="right">-<?= h(rupiah($retur_total)) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Nilai Bersih Setelah Retur</td>
                    <td class="right"><?= h(rupiah($net_total)) ?></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($purchase['catatan'])): ?>
            <div class="note"><strong>Catatan:</strong> <?= h($purchase['catatan']) ?></div>
        <?php endif; ?>

        <div class="signatures">
            <div>
                <div>Dicatat oleh,</div>
                <div class="line"></div>
                <div>Admin</div>
            </div>
            <div>
                <div>Diperiksa oleh,</div>
                <div class="line"></div>
                <div>Gudang</div>
            </div>
            <div>
                <div>Supplier,</div>
                <div class="line"></div>
                <div><?= h($purchase['supplier_name'] ?: 'Supplier') ?></div>
            </div>
        </div>

        <div class="footer">
            Dicetak pada <?= h(date('d/m/Y H:i:s')) ?>
        </div>
    </div>
</body>

</html>
