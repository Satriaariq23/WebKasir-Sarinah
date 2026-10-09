<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/print_nota_penjualan.php
 *  DESCRIPTION: Print nota penjualan sales dengan design profesional
 *  AUTHOR: System
 * ==========================================================
 */

// Check if included from routing system
if (!isset($conn) || !isset($_settings)) {
    // Fallback: Try to load config if not included
    if (!isset($conn)) {
        require_once('../../config.php');
    }
}

if (function_exists('require_login')) {
    require_login(false);
}

// Verify database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (!$conn->ping()) {
        global $db;
        if (isset($db)) {
            $conn = $db->conn;
        } else {
            die("<div class='alert alert-danger'>❌ Database connection error</div>");
        }
    }
}

// Get ID dari parameter - support both $_GET dan GET parameter dari page routing
$id_penjualan = 0;
if (isset($_GET['id'])) {
    $id_penjualan = (int)$_GET['id'];
}

if (!$id_penjualan) {
    die("<div class='alert alert-danger'><strong>Error:</strong> ID Penjualan tidak valid. Silakan kembali ke halaman penjualan.</div>");
}

// Get sale transaction
$query = "
    SELECT 
        s.saleid,
        s.sale_invoice,
        s.sale_date,
        s.sale_jt,
        s.cust_id,
        s.sale_total,
        s.sale_disc,
        s.discount_perc,
        s.sale_ppn,
        s.tax_perc,
        s.catatan,
        s.sale_status,
        COALESCE(p.nama, 'Umum') as customer_name,
        COALESCE(p.alamat, '-') as customer_address,
        COALESCE(p.telpon, '-') as customer_phone,
        CASE WHEN s.bayar_id = 1 THEN 'Tunai' ELSE 'Kredit' END as jenis_bayar,
        COALESCE(s.bayar_id, 1) as bayar_id
    FROM sale s
    LEFT JOIN pelanggan p ON s.cust_id = p.id
    WHERE s.saleid = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("<div class='alert alert-danger'><strong>Database Error:</strong> Gagal prepare query - " . htmlspecialchars($conn->error) . "</div>");
}

$stmt->bind_param("i", $id_penjualan);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("<div class='alert alert-danger'><strong>Database Error:</strong> Gagal execute query - " . htmlspecialchars($stmt->error) . "</div>");
}

$sale = $result->fetch_assoc();
$stmt->close();

if (!$sale) {
    die("<div class='alert alert-danger'><strong>Error:</strong> Data penjualan dengan ID <strong>" . htmlspecialchars($id_penjualan) . "</strong> tidak ditemukan</div>");
}

$sale['sale_total'] = (float)$sale['sale_total'];
$sale['sale_disc'] = (float)$sale['sale_disc'];
$sale['sale_ppn'] = (float)$sale['sale_ppn'];

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

// Get sale details - REMOVED subtotal_item from SQL, will calculate in PHP
$query_details = "
    SELECT 
        sd.noid,
        sd.produk_id,
        p.barcode,
        p.nama_produk,
        k.nama_kategori as kategori,
        sd.sale_qty as qty,
        sat.nama_satuan as satuan,
        sd.sale_harga as harga,
        p.harga_kasir,
        p.harga_sales,
        sd.ppn_item as ppn_amount,
        p.is_ppn
    FROM sale_det sd
    JOIN produk p ON p.id = sd.produk_id
    LEFT JOIN kategori k ON k.id = p.id_kategori
    LEFT JOIN satuan sat ON sat.id = p.id_satuan
    WHERE sd.sale_id = ?
    ORDER BY sd.noid ASC
";

$stmt_details = $conn->prepare($query_details);
if (!$stmt_details) {
    die("<div class='alert alert-danger'><strong>Database Error:</strong> Gagal prepare detail query - " . htmlspecialchars($conn->error) . "</div>");
}

$stmt_details->bind_param("i", $id_penjualan);
if (!$stmt_details->execute()) {
    die("<div class='alert alert-danger'><strong>Database Error:</strong> Gagal execute detail query - " . htmlspecialchars($stmt_details->error) . "</div>");
}

$result_details = $stmt_details->get_result();
$items = [];

if (!$result_details) {
    die("<div class='alert alert-danger'><strong>Database Error:</strong> Gagal get result detail query</div>");
}

error_log("Print Nota - Retrieved " . $result_details->num_rows . " items for saleid=$id_penjualan");

while ($row = $result_details->fetch_assoc()) {
    $row['qty'] = (float)$row['qty'];
    $row['harga'] = (float)$row['harga'];
    $items[] = $row;
}

$stmt_details->close();

// Get company info langsung dari $_settings object
$company_name = $_settings->info('name') ?? 'CV. Sarinah Logam';
$company_address = $_settings->info('address') ?? 'Dukuhwringin';
$company_phone = $_settings->info('contact') ?? $_settings->info('phone') ?? 'Telp/WA: 08 1234567890';

// Format date in Indonesian
$hari_indo = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$tanggal_obj = new DateTime($sale['sale_date']);
$hari = $tanggal_obj->format('l');
$tanggal_formatted = ($hari_indo[$hari] ?? $hari) . $tanggal_obj->format(', d-m-Y');

// Format jatuh tempo
$jatuh_tempo_formatted = '-';
if (!empty($sale['sale_jt'])) {
    $jt_obj = new DateTime($sale['sale_jt']);
    $hari_jt = $jt_obj->format('l');
    $jatuh_tempo_formatted = ($hari_indo[$hari_jt] ?? $hari_jt) . $jt_obj->format(', d-m-Y');
}

// Calculate totals
$total_qty = 0;
$subtotal_items = 0;
$detail_total_raw = 0;

foreach ($items as $item) {
    $detail_total_raw += (float)$item['qty'] * (float)$item['harga'];
}

foreach ($items as &$item) {
    $qty = (float)$item['qty'];
    $master_price = (float)($item['harga_sales'] ?: $item['harga_kasir'] ?: 0);
    $harga = normalize_legacy_unit_price($item['harga'], $master_price, $detail_total_raw, $sale['sale_total']);

    $item['harga'] = $harga;
    $item['subtotal_item'] = $qty * $harga;

    $total_qty += $qty;
    $subtotal_items += $item['subtotal_item'];
}
unset($item);

error_log("Final Totals: qty=$total_qty | subtotal=$subtotal_items | sale_total=" . $sale['sale_total']);

// Helper function untuk format Rupiah
function formatRupiah($amount)
{
    if (!is_numeric($amount)) {
        return '0';
    }
    return number_format((float)$amount, 0, ',', '.');
}

// Start output buffering untuk error handling
ob_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penjualan - <?php echo htmlspecialchars($sale['sale_invoice']); ?></title>
    <link href="<?php echo base_url; ?>../theme/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @media print {
            @page {
                size: 150mm 215mm;
                margin: 3mm;
            }

            * {
                margin: 0 !important;
                padding: 0 !important;
                font-family: 'Arial', 'Segoe UI', sans-serif;
            }

            html,
            body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                font-family: 'Arial', 'Segoe UI', sans-serif;
            }

            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            /* Sembunyikan semua elemen navbar, header, footer, dan icon */
            nav,
            header,
            footer,
            .navbar,
            .header,
            .footer,
            .bottom-nav,
            .mobile-nav,
            [role="navigation"],
            [role="banner"],
            .topnav,
            .sidenav,
            .sidebar {
                display: none !important;
            }

            /* Sembunyikan semua icon dan tombol sistem */
            .bi,
            .fas,
            .far,
            .fal,
            .fab,
            .icon,
            .icons,
            [class*="icon"],
            [class*="nav"] {
                display: none !important;
            }

            .print-container {
                width: 100%;
                height: 215mm;
                padding: 3mm;
                margin: 0;
                box-shadow: none;
                page-break-after: avoid;
                page-break-inside: avoid;
                overflow: hidden;
            }

            /* Pastikan hanya print-container yang terlihat */
            body>* {
                display: none !important;
            }

            body>.print-container {
                display: block !important;
            }

            /* Kompres spacing sangat ketat */
            .print-container .nota-header {
                margin: 0;
                padding: 3px 0;
                border-bottom: 1px solid #000;
                font-size: 12px;
            }

            .print-container .nota-header h1 {
                font-size: 14px;
                margin: 0;
            }

            .print-container .nota-header p {
                font-size: 8px;
                margin: 1px 0;
            }

            .print-container .nota-title {
                margin: 2px 0;
                padding: 0;
                font-size: 11px;
            }

            .print-container .info-section {
                margin: 4px 0;
                padding: 0;
                gap: 4px;
                font-size: 9px;
            }

            .print-container .info-box {
                border: 1px solid #000;
                padding: 4px;
                margin: 0;
            }

            .print-container .info-box div {
                margin: 2px 0;
                padding: 0;
            }

            .print-container .info-box strong {
                font-size: 8px;
            }

            .print-container .items-table {
                margin: 3px 0;
                padding: 0;
                font-size: 9px;
                width: 100%;
            }

            .print-container .items-table th,
            .print-container .items-table td {
                padding: 2px;
                border: none;
                border-bottom: 1px solid #ddd;
            }

            .print-container .items-table thead {
                border-bottom: 2px solid #000;
            }

            .print-container .totals-section {
                width: 45%;
                margin: 2px 0;
                padding: 0;
            }

            .print-container .totals-table {
                font-size: 9px;
                border: 1px solid #000;
                margin: 0;
                padding: 0;
            }

            .print-container .totals-table td {
                padding: 3px;
                margin: 0;
            }

            .print-container .catatan-section {
                margin: 2px 0;
                padding: 3px;
                border: 1px solid #000;
                font-size: 8px;
            }

            .print-container .signature-section {
                display: flex;
                gap: 3px;
                margin: 4px 0;
                padding: 0;
                font-size: 8px;
            }

            .print-container .signature-box {
                padding: 2px;
                border: 1px solid #000;
                font-size: 8px;
                margin: 0;
            }

            .print-container .signature-box .line {
                height: 12px;
                margin: 3px 0 2px 0;
            }

            .print-container .signature-box p {
                margin: 1px 0;
                padding: 0;
                font-size: 8px;
            }

            .clearfix {
                margin: 0;
                padding: 0;
            }
        }

        html,
        body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Arial', 'Segoe UI', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            background-color: #f5f5f5;
            padding: 10px;
        }

        .no-print {
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border: 2px solid #0066cc;
            border-radius: 4px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .no-print button {
            padding: 10px 20px;
            margin: 0;
            font-size: 13px;
            cursor: pointer;
            border: 1px solid #0066cc;
            border-radius: 4px;
            background: #0066cc;
            color: white;
            font-weight: bold;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .no-print button:hover {
            background: #0052a3;
        }

        .no-print button.btn-close {
            background: #dc3545;
            border-color: #dc3545;
        }

        .no-print button.btn-close:hover {
            background: #c82333;
        }

        .print-container {
            background: white;
            padding: 6mm;
            max-width: 150mm;
            height: auto;
            margin: 0 auto;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }

        /* Header - Compact */
        .nota-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 4px;
        }

        .nota-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            color: #000;
        }

        .nota-header p {
            font-size: 8px;
            margin: 1px 0;
            color: #333;
        }

        .nota-title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            margin: 3px 0;
        }

        /* Info Section - Grid Layout */
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 9px;
        }

        .info-box {
            border: 1px solid #000;
            padding: 4px;
        }

        .info-box div {
            margin-bottom: 2px;
        }

        .info-box strong {
            display: block;
            font-weight: bold;
            color: #000;
            font-size: 8px;
        }

        .info-box .value {
            color: #000;
            font-weight: bold;
            margin-top: 1px;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 9px;
        }

        .items-table thead {
            background-color: #f0f0f0;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
        }

        .items-table th {
            padding: 3px;
            text-align: left;
            font-weight: bold;
            border: none;
            font-size: 8px;
        }

        .items-table td {
            padding: 2px;
            border: none;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 2px solid #000;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-right {
            text-align: right;
        }

        .items-table .text-bold {
            font-weight: bold;
        }

        /* Totals - Right aligned */
        .totals-section {
            float: right;
            width: 45%;
            margin-bottom: 4px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            border: 1px solid #000;
        }

        .totals-table td {
            padding: 3px 4px;
            border: none;
        }

        .totals-table .label {
            text-align: left;
            width: 55%;
        }

        .totals-table .amount {
            text-align: right;
            width: 45%;
            font-weight: bold;
        }

        .totals-table .total-row {
            background-color: #f0f0f0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: bold;
            font-size: 10px;
        }

        /* Catatan */
        .catatan-section {
            clear: both;
            margin-top: 3px;
            padding: 3px;
            border: 1px solid #000;
            font-size: 8px;
            word-wrap: break-word;
        }

        .catatan-section strong {
            font-weight: bold;
        }

        /* Signature */
        .signature-section {
            clear: both;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            gap: 3px;
        }

        .signature-box {
            text-align: center;
            width: 23%;
            border: 1px solid #000;
            padding: 2px;
        }

        .signature-box .line {
            border-top: 1px solid #000;
            height: 12px;
            margin: 2px 0 2px 0;
        }

        .signature-box p {
            margin: 1px 0;
            font-weight: bold;
            font-size: 8px;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .badge {
            display: inline-block;
            padding: 1px 3px;
            border: 1px solid #000;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-tunai {
            background-color: #fff;
            color: #000;
        }

        .badge-kredit {
            background-color: #fff;
            color: #000;
        }

        .footer {
            text-align: center;
            margin-top: 2px;
            padding-top: 2px;
            border-top: 1px solid #000;
            font-size: 8px;
            color: #333;
        }
    </style>
</head>

<body>
    <!-- Print Controls -->
    <div class="no-print">
        <button onclick="window.print()" style="background: #28a745; border-color: #28a745;">
            🖨️ PRINT NOTA
        </button>
        <button class="btn-close" onclick="window.close()">
            ✕ TUTUP
        </button>
    </div>

    <!-- Nota Content - Full Landscape -->
    <div class="print-container">
        <!-- Header -->
        <div class="nota-header">
            <h1><?php echo strtoupper(htmlspecialchars($company_name)); ?></h1>
            <p><?php echo htmlspecialchars($company_address); ?> | <?php echo htmlspecialchars($company_phone); ?></p>
        </div>

        <div class="nota-title">NOTA PENJUALAN</div>

        <!-- Info Section - 3 Kolom -->
        <div class="info-section">
            <!-- Kolom 1: Nomor & Tanggal -->
            <div class="info-box">
                <div>
                    <strong>No. Nota: </strong>
                    <span class="value"><?php echo htmlspecialchars($sale['sale_invoice']); ?></span>
                </div>
                <div>
                    <strong>Tanggal: </strong>
                    <?php echo htmlspecialchars($tanggal_formatted); ?>
                </div>
                <div>
                    <strong>Jenis: </strong>
                    <span class="badge <?php echo $sale['bayar_id'] == 1 ? 'badge-tunai' : 'badge-kredit'; ?>">
                        <?php echo htmlspecialchars($sale['jenis_bayar']); ?>
                    </span>
                </div>
            </div>

            <!-- Kolom 2: Pelanggan -->
            <div class="info-box">
                <div>
                    <strong>Pelanggan: </strong>
                    <span class="value"><?php echo htmlspecialchars($sale['customer_name']); ?></span>
                </div>
                <div>
                    <strong>Alamat: </strong>
                    <?php echo htmlspecialchars($sale['customer_address']); ?>
                </div>
                <div>
                    <strong>Telepon: </strong>
                    <?php echo htmlspecialchars($sale['customer_phone']); ?>
                </div>
            </div>

            <!-- Kolom 3: Jatuh Tempo (jika kredit) -->
            <div class="info-box">
                <div>
                    <strong>Grand Total:</strong>
                </div>
                <div style="font-size: 12px; font-weight: bold; color: #000;">
                    Rp<?php echo formatRupiah($sale['sale_total']); ?>
                </div>
                <?php if ($sale['bayar_id'] != 1 && !empty($sale['sale_jt'])): ?>
                    <div style="margin-top: 4px;">
                        <strong>Jatuh Tempo:</strong>
                        <br><?php echo htmlspecialchars($jatuh_tempo_formatted); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 4%;">No.</th>
                    <th style="width: 10%;">Kode</th>
                    <th style="width: 38%;">Nama Barang</th>
                    <th style="width: 8%;" class="text-center">Qty</th>
                    <th style="width: 8%;" class="text-center">Satuan</th>
                    <th style="width: 12%;" class="text-right">Harga</th>
                    <th style="width: 12%;" class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                foreach ($items as $item):
                    $qty = (float)$item['qty'];
                    $harga = (float)$item['harga'];
                    $subtotal_item = (float)$item['subtotal_item'];

                    $qty_formatted = (int)$qty; // Tampilkan sebagai angka bulat tanpa koma
                    $harga_formatted = formatRupiah($harga);
                    $subtotal_formatted = formatRupiah($subtotal_item);
                ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($item['barcode'] ?? '-'); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(strtoupper($item['nama_produk'])); ?></strong>
                        </td>
                        <td class="text-center"><?php echo $qty_formatted; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars(strtoupper($item['satuan'] ?? '-')); ?></td>
                        <td class="text-right">Rp<?php echo $harga_formatted; ?></td>
                        <td class="text-right text-bold">Rp<?php echo $subtotal_formatted; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="clearfix">
            <div class="totals-section">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal (<?php echo (int)$total_qty; ?> item)</td>
                        <td class="amount">Rp<?php echo formatRupiah($subtotal_items); ?></td>
                    </tr>
                    <?php if ((float)$sale['sale_disc'] > 0): ?>
                        <tr>
                            <td class="label">Diskon<?php echo $sale['discount_perc'] > 0 ? ' (' . htmlspecialchars($sale['discount_perc']) . '%)' : ''; ?></td>
                            <td class="amount">-Rp<?php echo formatRupiah($sale['sale_disc']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td class="label"><strong>TOTAL</strong></td>
                        <td class="amount"><strong>Rp<?php echo formatRupiah($sale['sale_total']); ?></strong></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($sale['catatan'])): ?>
                <div class="catatan-section">
                    <strong>Catatan:</strong> <?php echo htmlspecialchars($sale['catatan']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Signature Section - Compact -->
        <div class="signature-section">
            <div class="signature-box">
                <p>Disiapkan oleh,</p>
                <div class="line"></div>
                <p>Admin</p>
            </div>
            <div class="signature-box">
                <p>Diterima oleh,</p>
                <div class="line"></div>
                <p>(...........................)</p>
            </div>
            <div class="signature-box">
                <p>Mengetahui,</p>
                <div class="line"></div>
                <p><?php echo htmlspecialchars($company_name); ?></p>
            </div>
            <div class="signature-box">
                <p>Dicetak: <?php echo date('d/m/Y H:i'); ?></p>
                <div class="line"></div>
                <p>Sistem</p>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.focus();
        };
    </script>
</body>

</html>
<?php
// Flush output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
