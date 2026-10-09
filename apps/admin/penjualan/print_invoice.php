<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/print_invoice.php
 *  DESCRIPTION: Smart print router untuk detect invoice type (KSR/INV)
 *  AUTHOR: System
 * ==========================================================
 */

require_once(__DIR__ . '/../../config.php');
require_login(false);

// Debug helper
error_log("=== print_invoice.php DEBUG ===");
error_log("__DIR__: " . __DIR__);
error_log("Config path: " . __DIR__ . '/../../config.php');

$sale_id = intval($_GET['id'] ?? 0);

if (!$sale_id || $sale_id <= 0) {
    die("❌ Invalid sale ID");
}

// Get global connection
global $conn;
if (!$conn || !($conn instanceof mysqli)) {
    error_log("Connection issue: " . gettype($conn));
    die("❌ Database connection failed");
}

// Query to get invoice type
$check_query = "SELECT sale_invoice, user_id FROM sale WHERE saleid = ? LIMIT 1";
$stmt = $conn->prepare($check_query);
if (!$stmt) {
    die("❌ Database error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    die("❌ Transaction not found");
}

if (current_user_level() === 3 && (int)($row['user_id'] ?? 0) !== (int)($_SESSION['userdata']['id'] ?? 0)) {
    die("Anda tidak memiliki akses ke transaksi ini");
}

$invoice = $row['sale_invoice'] ?? '';
$invoice_upper = strtoupper($invoice);

error_log("Invoice: " . $invoice . " | Type: " . $invoice_upper);

// Route berdasarkan tipe invoice
if (strpos($invoice_upper, 'KSR') === 0) {
    // Invoice Kasir - gunakan PenjualanKasir class print_receipt
    $kasir_path = __DIR__ . '/../../classes/PenjualanKasir.php';
    error_log("KSR route detected. Path: " . $kasir_path . " | Exists: " . (file_exists($kasir_path) ? 'YES' : 'NO'));

    require_once($kasir_path);
    $_GET['id'] = $sale_id;
    $kasir = new PenjualanKasir();
    $kasir->print_receipt();
} elseif (strpos($invoice_upper, 'INV') === 0) {
    // Invoice Sales - gunakan print_nota_penjualan.php
    $nota_path = __DIR__ . '/print_nota_penjualan.php';
    error_log("INV route detected. Path: " . $nota_path . " | Exists: " . (file_exists($nota_path) ? 'YES' : 'NO'));

    include($nota_path);
} else {
    die("❌ Unknown invoice type: " . htmlspecialchars($invoice));
}
exit;
