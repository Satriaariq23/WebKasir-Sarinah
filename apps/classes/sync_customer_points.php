<?php
/**
 * ============================================================
 * SYNC CUSTOMER POINTS - FIX FREKUENSI & TOTAL BELANJA = 0
 * ============================================================
 * Script ini untuk mensinkronkan ulang data customer_points
 * dari tabel sale. Gunakan ketika ada masalah:
 * - Frekuensi transaksi = 0
 * - Total belanja = 0
 * - Data di modal tidak sesuai dengan di luar modal
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/RewardProfileMatching.php';

if (php_sapi_name() !== 'cli') {
    require_admin_auth(1, false);
    require_csrf_for_unsafe_request(false);
}

$db = new DBConnection();

echo "<pre>";
echo "============================================================\n";
echo "SYNC CUSTOMER POINTS FROM SALES TABLE\n";
echo "============================================================\n\n";

// Ask user: Partial or Full sync?
$sync_type = isset($_GET['type']) ? $_GET['type'] : 'partial';

if ($sync_type === 'full') {
    echo "⚠️  FULL SYNC MODE - AKAN MENGHAPUS SEMUA POIN DAN REBUILD DARI AWAL\n\n";
    
    if (isset($_POST['confirm']) && $_POST['confirm'] === '1') {
        echo "Memulai full sync...\n\n";
        
        // BACKUP dulu
        echo "[Step 1] Backup data lama...\n";
        $db->query("DROP TABLE IF EXISTS customer_points_backup");
        $db->query("CREATE TABLE customer_points_backup LIKE customer_points");
        $db->query("INSERT INTO customer_points_backup SELECT * FROM customer_points");
        echo "✓ Backup berhasil\n\n";
        
        // DELETE semua poin
        echo "[Step 2] Menghapus semua poin lama...\n";
        $db->query("DELETE FROM customer_points");
        echo "✓ Semua poin dihapus\n\n";
        
        // REBUILD dari sale
        echo "[Step 3] Rebuild poin dari tabel sale...\n";
        $sql = "SELECT saleid, cust_id, sale_total, sale_date FROM sale WHERE sale_status NOT IN ('DELETED', 'VOID', 'CANCELED') ORDER BY sale_date ASC";
        $result = $db->query($sql);
        
        $total_sales = 0;
        $total_rebuilt = 0;
        
        while ($row = $result->fetch_assoc()) {
            $total_sales++;
            $sale_id = (int)$row['saleid'];
            $cust_id = (int)$row['cust_id'];
            $sale_total = (float)$row['sale_total'];
            $sale_date = $row['sale_date'];
            
            if ($sale_total >= 100000) {
                $points = (int)($sale_total / 10000);
                $expired_at = date('Y-m-d H:i:s', strtotime($sale_date . ' +12 months'));
                
                $insert_sql = "
                    INSERT INTO customer_points 
                    (pelanggan_id, sale_id, point_type, points, transaction_amount, description, expired_at, created_at)
                    VALUES ({$cust_id}, {$sale_id}, 'EARN', {$points}, {$sale_total}, 'Sinkronisasi penuh #$sale_id', '{$expired_at}', NOW())
                ";
                $db->query($insert_sql);
                $total_rebuilt++;
            }
        }
        
        echo "✓ Proses rebuild selesai\n";
        echo "   - Total transaksi diproses: $total_sales\n";
        echo "   - Total poin direkonstruksi: $total_rebuilt\n\n";
        
        // RECALCULATE scores
        echo "[Step 4] Recalculate reward scores...\n";
        $reward = new RewardProfileMatching();
        $reward->calculateAllCustomerScores(date('m'), date('Y'));
        echo "✓ Scores recalculated\n\n";
        
        echo "============================================================\n";
        echo "✅ FULL SYNC COMPLETED SUCCESSFULLY!\n";
        echo "============================================================\n";
        echo "Backup data tersimpan di: customer_points_backup\n";
        
    } else {
        echo "Konfirmasi full sync?\n";
        echo "Data lama akan di-backup ke customer_points_backup\n\n";
        echo "<form method='POST'>\n";
        echo "<input type='hidden' name='confirm' value='1'>\n";
        echo "<button type='submit' style='padding:10px; background:red; color:white; border:none; cursor:pointer;'>\n";
        echo "Yakin, Lanjutkan FULL SYNC\n";
        echo "</button>\n";
        echo "</form>\n";
    }
    
} else {
    // PARTIAL SYNC: Fix yang orphaned
    echo "PARTIAL SYNC MODE - Fix customer yang punya poin tapi tidak ada transaksi\n\n";
    
    echo "[Step 1] Mencari customer yang orphaned...\n";
    $sql_orphaned = "
        SELECT DISTINCT cp.pelanggan_id, p.kode, p.nama
        FROM customer_points cp
        LEFT JOIN pelanggan p ON cp.pelanggan_id = p.id
        WHERE NOT EXISTS (
            SELECT 1 FROM sale s 
            WHERE s.cust_id = cp.pelanggan_id 
            AND s.sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
        )
        GROUP BY cp.pelanggan_id
    ";
    
    $result_orphaned = $db->query($sql_orphaned);
    $orphaned_list = [];
    
    echo "Ditemukan customer orphaned:\n";
    echo str_pad("ID", 6) . " | " . str_pad("Kode", 8) . " | " . str_pad("Nama", 30) . " | " . str_pad("Poin", 8) . "\n";
    echo str_repeat("-", 70) . "\n";
    
    while ($row = $result_orphaned->fetch_assoc()) {
        $orphaned_list[] = $row['pelanggan_id'];
        
        // Get poin
        $sql_poin = "SELECT SUM(points) as total FROM customer_points WHERE pelanggan_id = " . $row['pelanggan_id'];
        $res_poin = $db->query($sql_poin);
        $poin_total = $res_poin->fetch_assoc()['total'];
        
        echo str_pad($row['pelanggan_id'], 6) . " | " 
             . str_pad($row['kode'] ?? '-', 8) . " | " 
             . str_pad(substr($row['nama'], 0, 28), 30) . " | " 
             . str_pad($poin_total, 8) . "\n";
    }
    
    if (empty($orphaned_list)) {
        echo "✅ Tidak ada customer orphaned! Data sudah konsisten.\n";
    } else {
        echo "\n[Step 2] Sync data untuk setiap customer...\n";
        
        $rebuild_count = 0;
        $issue_count = 0;
        
        foreach ($orphaned_list as $cust_id) {
            // Check: Apakah ada transaksi?
            $sql_check = "SELECT COUNT(*) as cnt FROM sale WHERE cust_id = $cust_id AND sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')";
            $res_check = $db->query($sql_check);
            $row_check = $res_check->fetch_assoc();
            
            if ($row_check['cnt'] > 0) {
                echo "  - Customer $cust_id: Ditemukan " . $row_check['cnt'] . " transaksi. Rebuild poin...\n";
                
                // Delete poin lama
                $db->query("DELETE FROM customer_points WHERE pelanggan_id = $cust_id");
                
                // Get semua transaksi
                $sql_sales = "SELECT saleid, sale_total, sale_date FROM sale WHERE cust_id = $cust_id AND sale_status NOT IN ('DELETED', 'VOID', 'CANCELED') ORDER BY sale_date ASC";
                $res_sales = $db->query($sql_sales);
                
                while ($sale = $res_sales->fetch_assoc()) {
                    $sale_id = (int)$sale['saleid'];
                    $sale_total = (float)$sale['sale_total'];
                    $sale_date = $sale['sale_date'];
                    
                    if ($sale_total >= 100000) {
                        $points = (int)($sale_total / 10000);
                        $expired_at = date('Y-m-d H:i:s', strtotime($sale_date . ' +12 months'));
                        
                        $db->query("
                            INSERT INTO customer_points 
                            (pelanggan_id, sale_id, point_type, points, transaction_amount, description, expired_at, created_at)
                            VALUES ($cust_id, $sale_id, 'EARN', $points, $sale_total, 'Sinkronisasi transaksi #$sale_id', '$expired_at', NOW())
                        ");
                        $rebuild_count++;
                    }
                }
                echo "    ✓ Selesai\n";
            } else {
                echo "  - Customer $cust_id: TIDAK ADA TRANSAKSI (Poin mungkin dari sumber lain)\n";
                $issue_count++;
            }
        }
        
        echo "\n[Step 3] Recalculate reward scores...\n";
        $reward = new RewardProfileMatching();
        $reward->calculateAllCustomerScores(date('m'), date('Y'));
        echo "✓ Scores recalculated\n\n";
        
        echo "============================================================\n";
        echo "✅ PARTIAL SYNC COMPLETED!\n";
        echo "============================================================\n";
        echo "Hasil:\n";
        echo "  - Customer dengan poin diperbaharui: " . $rebuild_count . "\n";
        echo "  - Customer tanpa transaksi: " . $issue_count . "\n";
        echo "  - Total affected: " . count($orphaned_list) . "\n";
    }
}

echo "\n</pre>";
?>
