<?php

/**
 * ==========================================================
 *  FILE: classes/RewardSystem.php
 *  DESCRIPTION: Reward System dengan Algoritma Profile Matching
 *  AUTHOR: GPT-5 🤖
 *  
 *  ALGORITMA PROFILE MATCHING:
 *  1. Hitung GAP antara profil pelanggan dengan profil ideal
 *  2. Konversi GAP ke bobot
 *  3. Hitung Core Factor (NCF) dan Secondary Factor (NSF)
 *  4. Hitung Nilai Akhir = (60% x NCF) + (40% x NSF)
 *  5. Ranking pelanggan berdasarkan nilai akhir
 * ==========================================================
 */

require_once __DIR__ . '/../config.php';

class RewardSystem extends DBConnection
{

    private $settings = [];
    private $gapMapping = [];
    private $criteria = [];

    // Bobot default Core Factor dan Secondary Factor
    const CORE_FACTOR_WEIGHT = 0.60;
    const SECONDARY_FACTOR_WEIGHT = 0.40;

    public function __construct($connection = null, $ensureSchema = true)
    {
        parent::__construct();
        if ($connection instanceof mysqli) {
            $this->conn = $connection;
        }
        if ($ensureSchema) {
            $this->ensureRedemptionTable();
        }
        $this->loadSettings();
        $this->loadGapMapping();
        $this->loadCriteria();
    }

    private function ensureRedemptionTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS reward_redemption (
            id INT(11) NOT NULL AUTO_INCREMENT,
            pelanggan_id INT(11) NOT NULL,
            reward_id INT(11) NOT NULL,
            points_used INT(11) NOT NULL,
            profile_score DECIMAL(5,2) DEFAULT NULL,
            status ENUM('PENDING','APPROVED','USED','EXPIRED','REJECTED') DEFAULT 'PENDING',
            voucher_code VARCHAR(50) DEFAULT NULL,
            used_on_sale_id INT(11) DEFAULT NULL,
            approved_by INT(11) DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            expired_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_reward_redemption_voucher (voucher_code),
            KEY idx_reward_redemption_pelanggan (pelanggan_id),
            KEY idx_reward_redemption_reward (reward_id),
            KEY idx_reward_redemption_status (status),
            KEY idx_reward_redemption_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $this->query($sql);
    }

    /* =====================================================
       LOAD CONFIGURATIONS
    ===================================================== */

    private function loadSettings()
    {
        $result = $this->query("SELECT setting_key, setting_value FROM reward_settings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }

    private function loadGapMapping()
    {
        $result = $this->query("SELECT gap_value, weight FROM profile_matching_gap ORDER BY gap_value");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->gapMapping[$row['gap_value']] = floatval($row['weight']);
            }
        }
    }

    private function loadCriteria()
    {
        $result = $this->query("SELECT * FROM profile_matching_criteria WHERE is_active = 1");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->criteria[$row['criteria_name']] = $row;
            }
        }
    }

    public function getSetting($key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
    
    /* =====================================================
       POINT MANAGEMENT
    ===================================================== */

    /**
     * Tambah poin dari transaksi
     */
    public function earnPointsFromTransaction($pelangganId, $saleId, $transactionAmount)
    {
        return $this->syncEarnPointsForTransaction($pelangganId, $saleId, $transactionAmount);
    }

    /**
     * Sinkronkan poin EARN sebuah transaksi secara idempoten.
     * Method ini aman dipanggil saat transaksi penjualan dibuat maupun diedit.
     */
    public function syncEarnPointsForTransaction($pelangganId, $saleId, $transactionAmount)
    {
        $pelangganId = (int)$pelangganId;
        $saleId = (int)$saleId;
        $transactionAmount = (float)$transactionAmount;

        if ($pelangganId <= 0 || $saleId <= 0) {
            throw new Exception('Pelanggan atau transaksi poin tidak valid');
        }

        $delete = $this->prepare("DELETE FROM customer_points WHERE sale_id = ? AND point_type = 'EARN'");
        $delete->bind_param('i', $saleId);
        if (!$delete->execute()) {
            throw new Exception('Gagal membersihkan poin transaksi lama: ' . $delete->error);
        }
        $delete->close();

        // Bersihkan ledger lama untuk transaksi yang sudah memakai ledger kanonis.
        $legacyDelete = $this->prepare("DELETE FROM member_point WHERE transaksi_id = ?");
        $legacyDelete->bind_param('i', $saleId);
        if (!$legacyDelete->execute()) {
            throw new Exception('Gagal membersihkan poin legacy: ' . $legacyDelete->error);
        }
        $legacyDelete->close();

        if (!$this->getSetting('reward_active', 1)) {
            return ['status' => 'skipped', 'points_earned' => 0, 'msg' => 'Sistem reward tidak aktif'];
        }

        $minTransaction = floatval($this->getSetting('min_transaction_point', 50000));
        if ($transactionAmount < $minTransaction) {
            return ['status' => 'skipped', 'points_earned' => 0, 'msg' => 'Transaksi di bawah minimum'];
        }

        $pointPerRupiah = floatval($this->getSetting('point_per_rupiah', 10000));
        if ($pointPerRupiah <= 0) {
            throw new Exception('Pengaturan nilai rupiah per poin harus lebih dari nol');
        }
        $points = floor($transactionAmount / $pointPerRupiah);
        $maxPoints = intval($this->getSetting('max_points_per_transaction', 0));
        if ($maxPoints > 0) {
            $points = min($points, $maxPoints);
        }

        if ($points <= 0) {
            return ['status' => 'skipped', 'points_earned' => 0, 'msg' => 'Tidak ada poin yang didapat'];
        }

        // Hitung tanggal expired
        $expiryMonths = intval($this->getSetting('point_expiry_months', 12));
        $expiredAt = date('Y-m-d H:i:s', strtotime("+{$expiryMonths} months"));

        $stmt = $this->prepare("
            INSERT INTO customer_points 
            (pelanggan_id, sale_id, point_type, points, transaction_amount, description, expired_at)
            VALUES (?, ?, 'EARN', ?, ?, ?, ?)
        ");

        $description = "Poin dari transaksi #$saleId";
        $stmt->bind_param("iiidss", $pelangganId, $saleId, $points, $transactionAmount, $description, $expiredAt);

        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan poin transaksi: ' . $stmt->error);
        }
        $stmt->close();

        return [
            'status' => 'success',
            'points_earned' => $points,
            'msg' => "Berhasil mendapatkan $points poin"
        ];
    }

    /** Hapus poin EARN ketika transaksi dibatalkan/dihapus. */
    public function cancelPointsForTransaction($saleId)
    {
        $saleId = (int)$saleId;
        if ($saleId <= 0) {
            throw new Exception('Transaksi poin tidak valid');
        }

        $stmt = $this->prepare("DELETE FROM customer_points WHERE sale_id = ? AND point_type = 'EARN'");
        $stmt->bind_param('i', $saleId);
        if (!$stmt->execute()) {
            throw new Exception('Gagal membatalkan poin transaksi: ' . $stmt->error);
        }
        $removed = $stmt->affected_rows;
        $stmt->close();

        $legacyStmt = $this->prepare("DELETE FROM member_point WHERE transaksi_id = ?");
        $legacyStmt->bind_param('i', $saleId);
        if (!$legacyStmt->execute()) {
            throw new Exception('Gagal membatalkan poin legacy: ' . $legacyStmt->error);
        }
        $removed += $legacyStmt->affected_rows;
        $legacyStmt->close();

        return ['status' => 'success', 'removed' => $removed];
    }

    /**
     * Kurangi poin (redeem)
     */
    public function redeemPoints($pelangganId, $points, $description = '')
    {
        $currentPoints = $this->getCustomerPoints($pelangganId);

        if ($currentPoints < $points) {
            return ['status' => 'failed', 'msg' => 'Poin tidak mencukupi'];
        }

        $stmt = $this->prepare("
            INSERT INTO customer_points 
            (pelanggan_id, point_type, points, description)
            VALUES (?, 'REDEEM', ?, ?)
        ");

        $negativePoints = -abs($points);
        $stmt->bind_param("iis", $pelangganId, $negativePoints, $description);

        if ($stmt->execute()) {
            return ['status' => 'success', 'msg' => "Berhasil menggunakan $points poin"];
        }

        return ['status' => 'failed', 'msg' => 'Gagal redeem poin'];
    }

    /**
     * Dapatkan total poin aktif pelanggan
     */
    public function getCustomerPoints($pelangganId)
    {
        $stmt = $this->prepare("
            SELECT GREATEST(COALESCE(SUM(points), 0), 0) AS total_points
            FROM customer_points
            WHERE pelanggan_id = ?
              AND (expired_at IS NULL OR expired_at > NOW() OR points < 0)
        ");
        $stmt->bind_param("i", $pelangganId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return intval($result['total_points']);
    }

    /**
     * Dapatkan riwayat poin pelanggan
     */
    public function getPointHistory($pelangganId, $limit = 50)
    {
        $stmt = $this->prepare("
            SELECT cp.*, s.sale_invoice
            FROM customer_points cp
            LEFT JOIN sale s ON s.saleid = cp.sale_id
            WHERE cp.pelanggan_id = ?
            ORDER BY cp.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $pelangganId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /* =====================================================
       PROFILE MATCHING ALGORITHM
    ===================================================== */

    /**
     * Konversi nilai aktual ke skala (1-5)
     */
    private function convertToScale($value, $minValue, $maxValue, $idealValue)
    {
        if ($maxValue == $minValue) return 3; // Default middle value

        // Normalisasi nilai ke range 0-1
        $normalized = ($value - $minValue) / ($maxValue - $minValue);

        // Konversi ke skala 1-5
        $scale = round($normalized * 4) + 1;

        return max(1, min(5, $scale));
    }

    /**
     * Konversi nilai ideal ke skala
     */
    private function getIdealScale($criteria)
    {
        return $this->convertToScale(
            $criteria['ideal_value'],
            $criteria['min_value'],
            $criteria['max_value'],
            $criteria['ideal_value']
        );
    }

    /**
     * Hitung GAP
     */
    private function calculateGap($actualScale, $idealScale)
    {
        return $actualScale - $idealScale;
    }

    /**
     * Konversi GAP ke bobot
     */
    private function gapToWeight($gap)
    {
        // Batasi GAP ke range yang ada di mapping
        $gap = max(-4, min(4, $gap));
        return $this->gapMapping[$gap] ?? 1.0;
    }

    /**
     * Dapatkan statistik transaksi pelanggan untuk periode tertentu
     */
    public function getCustomerStats($pelangganId, $month, $year)
    {
        $stmt = $this->prepare("
            SELECT 
                COUNT(saleid) AS frekuensi_belanja,
                COALESCE(SUM(sale_total), 0) AS total_transaksi
            FROM sale
            WHERE cust_id = ?
            AND MONTH(sale_date) = ?
            AND YEAR(sale_date) = ?
            AND sale_status IN ('LUNAS', 'ACTIVE')
        ");
        $stmt->bind_param("iii", $pelangganId, $month, $year);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Hitung Profile Matching untuk satu pelanggan
     */
    public function calculateProfileMatching($pelangganId, $month = null, $year = null)
    {
        $month = $month ?? date('n');
        $year = $year ?? date('Y');

        // Dapatkan statistik pelanggan
        $stats = $this->getCustomerStats($pelangganId, $month, $year);

        $result = [
            'pelanggan_id' => $pelangganId,
            'period_month' => $month,
            'period_year' => $year,
            'total_transaksi' => floatval($stats['total_transaksi']),
            'frekuensi_belanja' => intval($stats['frekuensi_belanja']),
            'gaps' => [],
            'weights' => [],
            'ncf' => 0,
            'nsf' => 0,
            'final_score' => 0
        ];

        $coreFactors = [];
        $secondaryFactors = [];

        // Hitung untuk setiap kriteria
        foreach ($this->criteria as $name => $criteria) {
            // Dapatkan nilai aktual
            $actualValue = 0;
            if ($name === 'total_transaksi') {
                $actualValue = $stats['total_transaksi'];
            } else if ($name === 'frekuensi_belanja') {
                $actualValue = $stats['frekuensi_belanja'];
            }

            // Konversi ke skala
            $actualScale = $this->convertToScale(
                $actualValue,
                $criteria['min_value'],
                $criteria['max_value'],
                $criteria['ideal_value']
            );

            $idealScale = $this->getIdealScale($criteria);

            // Hitung GAP
            $gap = $this->calculateGap($actualScale, $idealScale);
            $weight = $this->gapToWeight($gap);

            $result['gaps'][$name] = $gap;
            $result['weights'][$name] = $weight;

            // Kelompokkan berdasarkan jenis faktor
            if ($criteria['criteria_type'] === 'CORE') {
                $coreFactors[] = $weight;
            } else {
                $secondaryFactors[] = $weight;
            }
        }

        // Hitung NCF (Nilai Core Factor)
        if (count($coreFactors) > 0) {
            $result['ncf'] = array_sum($coreFactors) / count($coreFactors);
        }

        // Hitung NSF (Nilai Secondary Factor)
        if (count($secondaryFactors) > 0) {
            $result['nsf'] = array_sum($secondaryFactors) / count($secondaryFactors);
        }

        // Hitung Nilai Akhir
        $result['final_score'] = (self::CORE_FACTOR_WEIGHT * $result['ncf']) +
            (self::SECONDARY_FACTOR_WEIGHT * $result['nsf']);

        return $result;
    }

    /**
     * Hitung Profile Matching untuk semua pelanggan aktif
     */
    public function calculateAllCustomersMatching($month = null, $year = null)
    {
        $month = $month ?? date('n');
        $year = $year ?? date('Y');

        // Dapatkan semua pelanggan aktif (dengan atau tanpa transaksi)
        $sql = "
            SELECT DISTINCT p.id, p.kode, p.nama
            FROM pelanggan p
            LEFT JOIN sale s ON s.cust_id = p.id AND MONTH(s.sale_date) = ? AND YEAR(s.sale_date) = ?
            WHERE p.status = 1
            AND p.type_customer IN ('PELANGGAN', 'MEMBER')
            ORDER BY p.nama ASC
        ";

        $stmt = $this->prepare($sql);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $results = [];
        foreach ($customers as $customer) {
            $matching = $this->calculateProfileMatching($customer['id'], $month, $year);
            $matching['kode'] = $customer['kode'];
            $matching['nama'] = $customer['nama'];
            $results[] = $matching;
        }

        // Sort berdasarkan final_score descending
        usort($results, function ($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });

        // Tambahkan ranking
        $rank = 1;
        foreach ($results as &$r) {
            $r['ranking'] = $rank++;
        }

        return $results;
    }

    /**
     * Simpan hasil Profile Matching ke database
     */
    public function saveMatchingResults($results)
    {
        $stmt = $this->prepare("
            INSERT INTO profile_matching_results 
            (pelanggan_id, period_month, period_year, total_transaksi, frekuensi_belanja,
             gap_transaksi, gap_frekuensi, ncf, nsf, final_score, ranking, eligible_rewards)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_transaksi = VALUES(total_transaksi),
            frekuensi_belanja = VALUES(frekuensi_belanja),
            gap_transaksi = VALUES(gap_transaksi),
            gap_frekuensi = VALUES(gap_frekuensi),
            ncf = VALUES(ncf),
            nsf = VALUES(nsf),
            final_score = VALUES(final_score),
            ranking = VALUES(ranking),
            eligible_rewards = VALUES(eligible_rewards)
        ");

        foreach ($results as $r) {
            $eligibleRewards = $this->getEligibleRewards($r['final_score']);
            $rewardsJson = json_encode(array_column($eligibleRewards, 'id'));

            $gapTransaksi = $r['gaps']['total_transaksi'] ?? 0;
            $gapFrekuensi = $r['gaps']['frekuensi_belanja'] ?? 0;

            $stmt->bind_param(
                "iiidiiidddis",
                $r['pelanggan_id'],
                $r['period_month'],
                $r['period_year'],
                $r['total_transaksi'],
                $r['frekuensi_belanja'],
                $gapTransaksi,
                $gapFrekuensi,
                $r['ncf'],
                $r['nsf'],
                $r['final_score'],
                $r['ranking'],
                $rewardsJson
            );
            $stmt->execute();
        }

        return true;
    }
    
    /* =====================================================
       REWARD CATALOG & REDEMPTION
    ===================================================== */

    /**
     * Dapatkan daftar reward yang tersedia
     */
    public function getRewardCatalog($activeOnly = true)
    {
        // Membaca dari reward_items (sistem utama)
        $sql = "SELECT 
                    id,
                    nama_reward as reward_name,
                    poin_dibutuhkan as points_required,
                    stok as stock,
                    status,
                    CASE WHEN status='active' THEN 1 ELSE 0 END as is_active,
                    deskripsi as description,
                    gambar as image,
                    created_at,
                    updated_at
                FROM reward_items";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        } else {
            $sql .= " WHERE status != 'deleted'";
        }
        $sql .= " ORDER BY poin_dibutuhkan ASC";

        $result = $this->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Dapatkan reward yang berhak berdasarkan skor
     */
    public function getEligibleRewards($score)
    {
        // Membaca dari reward_items (sistem utama)
        $stmt = $this->prepare("
            SELECT 
                id,
                nama_reward as reward_name,
                poin_dibutuhkan as points_required,
                stok as stock,
                CASE WHEN status='active' THEN 1 ELSE 0 END as is_active,
                deskripsi as description,
                gambar as image
            FROM reward_items 
            WHERE status = 'active' 
            AND stok > 0
            ORDER BY poin_dibutuhkan ASC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Redeem reward
     */
    public function redeemReward($pelangganId, $rewardId)
    {
        // Dapatkan info reward dari reward_items (sistem utama)
        $stmt = $this->prepare("
            SELECT 
                id,
                nama_reward as reward_name,
                poin_dibutuhkan as points_required,
                stok as stock,
                CASE WHEN status='active' THEN 1 ELSE 0 END as is_active,
                deskripsi as description,
                gambar as image
            FROM reward_items 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();
        $reward = $stmt->get_result()->fetch_assoc();

        if (!$reward) {
            return ['status' => 'failed', 'msg' => 'Reward tidak ditemukan atau tidak aktif'];
        }

        // Cek poin pelanggan
        $customerPoints = $this->getCustomerPoints($pelangganId);
        if ($customerPoints < $reward['points_required']) {
            return ['status' => 'failed', 'msg' => 'Poin tidak mencukupi'];
        }

        // Cek skor profile matching pelanggan
        $currentMonth = date('n');
        $currentYear = date('Y');
        $matching = $this->calculateProfileMatching($pelangganId, $currentMonth, $currentYear);

        // Note: reward_items tidak memiliki min_score, semua reward tersedia untuk pelanggan eligible

        // Cek stok
        if ($reward['stock'] != -1 && $reward['stock'] <= 0) {
            return ['status' => 'failed', 'msg' => 'Stok reward habis'];
        }

        // Generate voucher code
        $voucherCode = 'RWD-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $this->conn->begin_transaction();

        // Kurangi poin
        $redeemResult = $this->redeemPoints(
            $pelangganId,
            $reward['points_required'],
            "Redeem reward: " . $reward['reward_name']
        );

        if ($redeemResult['status'] !== 'success') {
            $this->conn->rollback();
            return $redeemResult;
        }

        // Hitung expired date (30 hari)
        $expiredAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Simpan redemption
        $stmt = $this->prepare("
            INSERT INTO reward_redemption 
            (pelanggan_id, reward_id, points_used, profile_score, voucher_code, expired_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiidss",
            $pelangganId,
            $rewardId,
            $reward['points_required'],
            $matching['final_score'],
            $voucherCode,
            $expiredAt
        );

        if ($stmt->execute()) {
            // Kurangi stok reward_items
            $stockStmt = $this->prepare("UPDATE reward_items SET stok = stok - 1 WHERE id = ? AND stok > 0");
            $stockStmt->bind_param("i", $rewardId);
            $stockStmt->execute();
            $stockStmt->close();
            $this->conn->commit();

            return [
                'status' => 'success',
                'msg' => 'Reward berhasil ditukar!',
                'voucher_code' => $voucherCode,
                'reward_name' => $reward['reward_name'],
                'expired_at' => $expiredAt
            ];
        }

        $this->conn->rollback();
        return ['status' => 'failed', 'msg' => 'Gagal menyimpan penukaran'];
    }

    /**
     * Dapatkan riwayat redemption pelanggan
     */
    public function getRedemptionHistory($pelangganId)
    {
        $stmt = $this->prepare("
            SELECT rr.*, ri.nama_reward as reward_name
            FROM reward_redemption rr
            JOIN reward_items ri ON ri.id = rr.reward_id
            WHERE rr.pelanggan_id = ?
            ORDER BY rr.created_at DESC
        ");
        $stmt->bind_param("i", $pelangganId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /* =====================================================
       AJAX HANDLERS
    ===================================================== */

    /**
     * Handle AJAX requests
     */
    public function handleRequest()
    {
        $action = request_action('');

        switch ($action) {
            case 'get_customer_points':
                return $this->ajaxGetCustomerPoints();

            case 'get_point_history':
                return $this->ajaxGetPointHistory();

            case 'calculate_matching':
                return $this->ajaxCalculateMatching();

            case 'get_matching_results':
                return $this->ajaxGetMatchingResults();

            case 'get_reward_catalog':
                return $this->ajaxGetRewardCatalog();

            case 'redeem_reward':
                return $this->ajaxRedeemReward();

            case 'get_redemption_history':
                return $this->ajaxGetRedemptionHistory();

            case 'save_criteria':
                return $this->ajaxSaveCriteria();

            case 'save_reward':
                return $this->ajaxSaveReward();

            case 'get_settings':
                return $this->ajaxGetSettings();

            case 'save_settings':
                return $this->ajaxSaveSettings();

            case 'get_dashboard_stats':
                return $this->ajaxGetDashboardStats();

            default:
                return json_encode(['status' => 'error', 'msg' => 'Unknown action']);
        }
    }

    private function ajaxGetCustomerPoints()
    {
        $pelangganId = intval($_GET['pelanggan_id'] ?? $_POST['pelanggan_id'] ?? 0);
        if ($pelangganId <= 0) {
            return json_encode(['status' => 'error', 'msg' => 'ID pelanggan tidak valid']);
        }

        $points = $this->getCustomerPoints($pelangganId);
        return json_encode(['status' => 'success', 'points' => $points]);
    }

    private function ajaxGetPointHistory()
    {
        $pelangganId = intval($_GET['pelanggan_id'] ?? $_POST['pelanggan_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? 50);

        $history = $this->getPointHistory($pelangganId, $limit);
        return json_encode(['status' => 'success', 'data' => $history]);
    }

    private function ajaxCalculateMatching()
    {
        $month = intval($_POST['month'] ?? date('n'));
        $year = intval($_POST['year'] ?? date('Y'));

        $results = $this->calculateAllCustomersMatching($month, $year);
        $this->saveMatchingResults($results);

        return json_encode([
            'status' => 'success',
            'data' => $results,
            'total' => count($results)
        ]);
    }

    private function ajaxGetMatchingResults()
    {
        $month = intval($_GET['month'] ?? date('n'));
        $year = intval($_GET['year'] ?? date('Y'));

        $stmt = $this->prepare("
            SELECT pmr.*, p.kode, p.nama
            FROM profile_matching_results pmr
            JOIN pelanggan p ON p.id = pmr.pelanggan_id
            WHERE pmr.period_month = ? AND pmr.period_year = ?
            ORDER BY pmr.ranking ASC
        ");
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return json_encode(['status' => 'success', 'data' => $results]);
    }

    private function ajaxGetRewardCatalog()
    {
        $includeInactive = ($_GET['include_inactive'] ?? $_POST['include_inactive'] ?? '0') === '1';
        $rewards = $this->getRewardCatalog(!$includeInactive);
        return json_encode(['status' => 'success', 'data' => $rewards]);
    }

    private function ajaxRedeemReward()
    {
        $pelangganId = intval($_POST['pelanggan_id'] ?? 0);
        $rewardId = intval($_POST['reward_id'] ?? 0);

        try {
            $result = $this->redeemReward($pelangganId, $rewardId);
            return json_encode($result);
        } catch (Throwable $e) {
            if ($this->conn) {
                @$this->conn->rollback();
            }
            error_log('Redeem Reward Error: ' . $e->getMessage());
            return json_encode([
                'status' => 'failed',
                'msg' => 'Gagal menukar reward: ' . $e->getMessage()
            ]);
        }
    }

    private function ajaxGetRedemptionHistory()
    {
        $pelangganId = intval($_GET['pelanggan_id'] ?? 0);
        $history = $this->getRedemptionHistory($pelangganId);
        return json_encode(['status' => 'success', 'data' => $history]);
    }

    private function ajaxSaveCriteria()
    {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['criteria_name'] ?? '');
        $type = $_POST['criteria_type'] ?? 'SECONDARY';
        $weight = floatval($_POST['weight'] ?? 1);
        $minValue = floatval($_POST['min_value'] ?? 0);
        $maxValue = floatval($_POST['max_value'] ?? 100);
        $idealValue = floatval($_POST['ideal_value'] ?? 50);
        $isActive = intval($_POST['is_active'] ?? 1);
        $description = trim($_POST['description'] ?? '');

        if ($id > 0) {
            $stmt = $this->prepare("
                UPDATE profile_matching_criteria 
                SET criteria_name=?, criteria_type=?, weight=?, min_value=?, max_value=?, 
                    ideal_value=?, is_active=?, description=?
                WHERE id=?
            ");
            $stmt->bind_param("ssddddisi", $name, $type, $weight, $minValue, $maxValue, $idealValue, $isActive, $description, $id);
        } else {
            $stmt = $this->prepare("
                INSERT INTO profile_matching_criteria 
                (criteria_name, criteria_type, weight, min_value, max_value, ideal_value, is_active, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssddddis", $name, $type, $weight, $minValue, $maxValue, $idealValue, $isActive, $description);
        }

        if ($stmt->execute()) {
            return json_encode(['status' => 'success', 'msg' => 'Kriteria berhasil disimpan']);
        }
        return json_encode(['status' => 'failed', 'msg' => 'Gagal menyimpan kriteria']);
    }

    private function ajaxSaveReward()
    {
        // NOTE: Sistem reward sekarang menggunakan reward_items dari admin panel Pengaturan
        // Method ini ditinggalkan untuk backward compatibility tapi tidak lagi digunakan
        
        // Jika ada yang perlu disimpan, redirect ke reward_items system
        return json_encode([
            'status' => 'info', 
            'msg' => 'Sistem telah diubah untuk menggunakan Reward Items. Silakan tambah reward melalui: Admin > Pengaturan > Daftar Reward Items'
        ]);
    }

    private function ajaxGetSettings()
    {
        return json_encode(['status' => 'success', 'data' => $this->settings]);
    }

    private function ajaxSaveSettings()
    {
        foreach ($_POST as $key => $value) {
            if ($key === 'action') continue;

            $stmt = $this->prepare("
                INSERT INTO reward_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }

        return json_encode(['status' => 'success', 'msg' => 'Pengaturan berhasil disimpan']);
    }

    private function ajaxGetDashboardStats()
    {
        $stats = [];

        // Total poin aktif
        $result = $this->query("
            SELECT COALESCE(SUM(points), 0) AS total 
            FROM customer_points 
            WHERE (expired_at IS NULL OR expired_at > NOW())
        ");
        $stats['total_active_points'] = $result ? $result->fetch_assoc()['total'] : 0;

        // Total pelanggan terdaftar
        $result = $this->query("
            SELECT COUNT(*) AS total 
            FROM pelanggan
            WHERE status = 1 AND type_customer IN ('PELANGGAN', 'MEMBER')
        ");
        $stats['customers_with_points'] = $result ? $result->fetch_assoc()['total'] : 0;

        // Total reward redeemed bulan ini
        $result = $this->query("
            SELECT COUNT(*) AS total, COALESCE(SUM(points_used), 0) AS points
            FROM reward_redemption 
            WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ");
        $row = $result ? $result->fetch_assoc() : ['total' => 0, 'points' => 0];
        $stats['redemptions_this_month'] = $row['total'];
        $stats['points_redeemed_this_month'] = $row['points'];

        // Top 5 pelanggan dengan transaksi terbanyak bulan ini
        $result = $this->query("
            SELECT p.id, p.kode, p.nama, 
                   COALESCE(SUM(s.sale_total), 0) AS total_points
            FROM pelanggan p
            LEFT JOIN sale s ON s.cust_id = p.id 
                AND MONTH(s.sale_date) = MONTH(NOW()) 
                AND YEAR(s.sale_date) = YEAR(NOW())
            WHERE p.status = 1 AND p.type_customer IN ('PELANGGAN', 'MEMBER')
            GROUP BY p.id
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT 5
        ");
        $stats['top_customers'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return json_encode(['status' => 'success', 'data' => $stats]);
    }
}

// Handle direct AJAX calls - only when calling RewardSystem.php directly
// (not when included from another file like Penjualan.php)
if ((isset($_GET['f']) || isset($_POST['f']) || isset($_POST['action'])) &&
    basename($_SERVER['SCRIPT_FILENAME']) === 'RewardSystem.php'
) {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    $reward = new RewardSystem();
    echo $reward->handleRequest();
    exit;
}
