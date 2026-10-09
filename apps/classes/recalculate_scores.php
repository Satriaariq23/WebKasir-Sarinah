<?php
/**
 * ============================================================
 * RECALCULATE ALL CUSTOMER REWARD SCORES
 * dengan konstanta MAX_FREQUENCY_TRANSACTION = 20 (BENAR)
 * ============================================================
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/RewardProfileMatching.php';

if (php_sapi_name() !== 'cli') {
    require_admin_auth(1, false);
}

echo "<pre>";
echo "============================================================\n";
echo "RECALCULATE CUSTOMER REWARD SCORES\n";
echo "============================================================\n\n";

$reward = new RewardProfileMatching();

echo "Menghitung ulang scores untuk semua pelanggan...\n";
echo "- Menggunakan konstanta MAX_FREQUENCY_TRANSACTION = 20\n";
echo "- Periode: " . date('F Y') . "\n\n";

$result = $reward->calculateAllCustomerScores(date('m'), date('Y'));

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n============================================================\n";
echo "✅ RECALCULATION COMPLETED!\n";
echo "============================================================\n\n";

echo "Silakan refresh halaman Ranking Point untuk melihat hasil terbaru.\n";

echo "\n</pre>";
?>
