<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    require_admin_auth(1, false);
}

$db = new DBConnection();

echo "=== ADDING RANKING TEST DATA ===\n\n";

// Get members yang belum punya poin
$result = $db->query('SELECT id, nama FROM pelanggan WHERE status = 1 AND id NOT IN (SELECT DISTINCT pelanggan_id FROM customer_points) LIMIT 20');

$idx = 0;
while ($member = $result->fetch_assoc()) {
    $points = rand(50, 250);
    $amount = $points * 10000 + rand(0, 9000);

    $stmt = $db->prepare("
        INSERT INTO customer_points 
        (pelanggan_id, sale_id, points, transaction_amount, expired_at, created_at) 
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 12 MONTH), NOW())
    ");
    $sale_id = 5000 + $idx;
    $stmt->bind_param("iiii", $member['id'], $sale_id, $points, $amount);
    $stmt->execute();
    $stmt->close();

    echo "✓ {$member['nama']}: {$points} poin\n";
    $idx++;
}

// Final count
$result = $db->query('SELECT COUNT(*) as cnt FROM customer_points');
$row = $result->fetch_assoc();
echo "\nTotal Point Records: " . $row['cnt'] . "\n";

// Show current ranking (TOP 10)
echo "\n" . str_repeat("=", 70) . "\n";
echo "CURRENT TOP 10 RANKING\n";
echo str_repeat("=", 70) . "\n";

$result = $db->query("
    SELECT 
        ROW_NUMBER() OVER (ORDER BY SUM(cp.points) DESC) as ranking,
        p.id,
        p.kode,
        p.nama,
        COALESCE(SUM(cp.points), 0) as total_points
    FROM pelanggan p
    LEFT JOIN customer_points cp ON p.id = cp.pelanggan_id
    WHERE p.status = 1
    GROUP BY p.id, p.kode, p.nama
    HAVING COALESCE(SUM(cp.points), 0) > 0
    ORDER BY total_points DESC
    LIMIT 10
");

$no = 1;
while ($row = $result->fetch_assoc()) {
    printf("%2d. %-30s | %8d Poin\n", $no++, substr($row['nama'], 0, 30), $row['total_points']);
}

echo "\n✅ Data siap untuk ditampilkan di ranking point!\n";
