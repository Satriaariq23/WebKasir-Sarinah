<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/print_kasir.php
 *  DESCRIPTION: Print nota untuk transaksi kasir
 *  AUTHOR: System
 * ==========================================================
 */

require_once(__DIR__ . '/../../config.php');
require_login(false);

// Ambil ID dari parameter
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$sale_id) {
    die("Invalid sale ID");
}

// Database connection
$host = DB_SERVER;
$port = DB_PORT;
$dbname = DB_NAME;
$username = DB_USERNAME;
$password = DB_PASSWORD;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('[print_kasir] ' . $e->getMessage());
    die("Connection failed");
}

// Get sale transaction (KASIR only - KSR prefix)
$stmt = $pdo->prepare("
    SELECT 
        s.saleid,
        s.sale_invoice as no_transaksi,
        s.sale_date as tanggal,
        s.cust_id as id_pelanggan,
        s.sale_total as total,
        s.sale_disc as diskon,
        s.bayar_id,
        s.bayar_total as bayar,
        s.bayar_kembali,
        s.sale_ppn as ppn_total,
        COALESCE(p.nama, 'Umum') as customer_name,
        COALESCE(p.telpon, '-') as customer_phone,
        COALESCE(u.username, '-') as kasir_nama
    FROM sale s
    LEFT JOIN pelanggan p ON s.cust_id = p.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.saleid = ? AND s.sale_invoice LIKE 'KSR%'
");
$stmt->execute([$sale_id]);
$kasir = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$kasir) {
    die("Transaction not found or not a Kasir transaction");
}

// Subtotal sebelum diskon faktur. Pada retur, diskon mengurangi nilai refund
// sehingga subtotal berada lebih jauh dari nol daripada total akhirnya.
$kasir['subtotal'] = (float)$kasir['total'] < 0
    ? (float)$kasir['total'] - (float)$kasir['diskon']
    : (float)$kasir['total'] + (float)$kasir['diskon'];

function apply_signed_discount($amount, $discount)
{
    $amount = (float)$amount;
    $discount = min(max((float)$discount, 0), abs($amount));
    return $amount < 0 ? $amount + $discount : $amount - $discount;
}

function normalize_legacy_unit_price($stored_price, $master_price = 0, $detail_total = 0, $sale_total = 0)
{
    $stored_price = (float)$stored_price;
    $master_price = (float)$master_price;
    $detail_total = (float)$detail_total;
    $sale_total = (float)$sale_total;

    if ($stored_price <= 0) {
        return 0.0;
    }

    if ($sale_total > 0 && $detail_total >= ($sale_total * 50)) {
        return $stored_price / 100;
    }

    if ($master_price > 0 && $stored_price >= ($master_price * 50)) {
        return $stored_price / 100;
    }

    return $stored_price;
}

function format_qty($qty)
{
    $formatted = number_format((float)$qty, 4, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
}

// Get sale details
$stmt = $pdo->prepare("
    SELECT 
        sd.noid as id,
        sd.produk_id as id_produk,
        p.barcode,
        p.nama_produk as nama_produk,
        k.kategori,
        sd.sale_qty as qty,
        s.satuan,
        sd.sale_harga as harga,
        sd.sale_pdisc as item_discount,
        p.harga_kasir,
        p.harga_sales,
        0 as is_ppn,
        sd.ppn_item as ppn_amount
    FROM sale_det sd
    JOIN produk p ON p.id = sd.produk_id
    LEFT JOIN kategori k ON k.id = p.id_kategori
    LEFT JOIN satuan s ON s.id = p.id_satuan
    WHERE sd.sale_id = ?
    ORDER BY sd.noid ASC
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$detail_total = 0;
foreach ($items as $item) {
    $detail_total += (float)$item['qty'] * (float)$item['harga'];
}
foreach ($items as &$item) {
    $master_price = (float)($item['harga_kasir'] ?: $item['harga_sales'] ?: 0);
    $item['harga'] = normalize_legacy_unit_price($item['harga'], $master_price, $detail_total, $kasir['total']);
    $item['subtotal'] = apply_signed_discount(
        (float)$item['qty'] * (float)$item['harga'],
        (float)($item['item_discount'] ?? 0)
    );
}
unset($item);

// Get company info from system_info
$stmt = $pdo->prepare("SELECT meta_field, meta_value FROM system_info");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['meta_field']] = $row['meta_value'];
}

$company_name = trim((string)($settings['name'] ?? '')) ?: 'Sarinah Logam';
$company_address = trim((string)($settings['alamat'] ?? ''));
if ($company_address === '') {
    $company_address = trim((string)($settings['address'] ?? ''));
}
$company_phone = trim((string)($settings['telpon'] ?? ''));
if ($company_phone === '') {
    $company_phone = trim((string)($settings['contact'] ?? ''));
}
if ($company_phone === '') {
    $company_phone = trim((string)($settings['phone'] ?? ''));
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Print Nota - <?php echo $kasir['no_transaksi'] ?></title>
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                margin: 0;
                padding: 5mm;
            }

            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
        }

        .header h2 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            margin: 2px 0;
        }

        .info {
            margin-bottom: 10px;
            font-size: 11px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .items {
            margin: 10px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
        }

        .item {
            margin: 5px 0;
        }

        .item-name {
            font-weight: bold;
        }

        .item-saving {
            margin-top: 1px;
            font-size: 10px;
            font-weight: bold;
        }

        .item-detail {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-top: 2px;
        }

        .totals {
            margin: 10px 0;
            font-size: 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .total-row.grand {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 2px dashed #000;
            padding-top: 10px;
            font-size: 11px;
        }

        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        .no-print button {
            padding: 10px 20px;
            margin: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
        }

        .btn-print {
            background: #4CAF50;
            color: white;
        }

        .btn-close {
            background: #f44336;
            color: white;
        }
    </style>
</head>

<body>
    <!-- Print Controls -->
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print</button>
        <button class="btn-close" onclick="window.close()">✖️ Close</button>
    </div>

    <!-- Nota Content -->
    <div class="header">
        <h2><?php echo e(strtoupper($company_name)) ?></h2>
        <?php if ($company_address !== ''): ?>
            <p><?php echo e($company_address) ?></p>
        <?php endif; ?>
        <?php if ($company_phone !== ''): ?>
            <p>Telp: <?php echo e($company_phone) ?></p>
        <?php endif; ?>
    </div>

    <div class="info">
        <div class="info-row">
            <span>No. Nota</span>
            <span><strong><?php echo $kasir['no_transaksi'] ?></strong></span>
        </div>
        <div class="info-row">
            <span>Tanggal</span>
            <span><?php echo date('d/m/Y H:i', strtotime($kasir['tanggal'])) ?></span>
        </div>
        <div class="info-row">
            <span>Kasir</span>
            <span><?php echo $kasir['kasir_nama'] ?></span>
        </div>
        <div class="info-row">
            <span>Customer</span>
            <span><?php echo $kasir['customer_name'] ?></span>
        </div>
    </div>

    <div class="items">
        <?php foreach ($items as $item): ?>
            <div class="item">
                <div class="item-name"><?php echo e($item['nama_produk']) ?></div>
                <?php if ((float)$item['qty'] > 0 && (float)($item['item_discount'] ?? 0) > 0): ?>
                    <div class="item-saving">Anda hemat Rp. <?php echo number_format((float)$item['item_discount'], 0, ',', '.') ?></div>
                <?php endif; ?>
                <div class="item-detail">
                    <span><?php echo format_qty($item['qty']) ?> x Rp<?php echo number_format($item['harga'], 0, ',', '.') ?></span>
                    <span><strong>Rp<?php echo number_format($item['subtotal'], 0, ',', '.') ?></strong></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>Rp<?php echo number_format($kasir['subtotal'], 0, ',', '.') ?></span>
        </div>

        <?php if ($kasir['diskon'] > 0): ?>
            <div class="total-row">
                <span>Diskon</span>
                <span>-Rp<?php echo number_format($kasir['diskon'], 0, ',', '.') ?></span>
            </div>
        <?php endif; ?>

        <div class="total-row grand">
            <span>TOTAL</span>
            <span>Rp<?php echo number_format($kasir['total'], 0, ',', '.') ?></span>
        </div>

        <div class="total-row">
            <span>Bayar (<?php echo $kasir['bayar_id'] == 1 ? 'Tunai' : 'Bank' ?>)</span>
            <span>Rp<?php echo number_format($kasir['bayar'], 0, ',', '.') ?></span>
        </div>

        <?php if ($kasir['bayar_kembali'] > 0): ?>
            <div class="total-row">
                <span>Kembali</span>
                <span>Rp<?php echo number_format($kasir['bayar_kembali'], 0, ',', '.') ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Terima Kasih Atas Kunjungan Anda</p>
        <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
        <p style="margin-top: 10px;">--- STRUK INI ADALAH BUKTI PEMBAYARAN YANG SAH ---</p>
    </div>

    <script>
        // Auto print on load (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>

</html>
