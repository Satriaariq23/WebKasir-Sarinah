<?php

/**
 * ==========================================================
 *  FILE: classes/RewardProfileMatching.php
 *  DESCRIPTION: Reward System dengan Algoritma GAP Analysis
 *  
 *  ALGORITMA GAP ANALYSIS (1-5 SCALE):
 *  1. Hitung GAP Nominal Belanja (Core Factor = 60%)
 *  2. Hitung GAP Frekuensi Belanja (Secondary Factor = 40%)
 *  3. Standarisasi score ke skala 1-5 berdasarkan GAP
 *  4. Hitung Final Score = (Nominal Score × 60%) + (Frequency Score × 40%)
 *  5. Tentukan Eligibility berdasarkan threshold minimum (3.5)
 * ==========================================================
 */

require_once __DIR__ . '/../config.php';

class RewardProfileMatching extends DBConnection
{
    // Bobot Profile Matching
    const CORE_FACTOR_WEIGHT = 0.60;           // Bobot Score Nominal Belanja
    const SECONDARY_FACTOR_WEIGHT = 0.40;      // Bobot Score Frekuensi Belanja

    // Threshold Eligibility (1-5 scale)
    const MINIMUM_ELIGIBILITY_SCORE = 3.5;     // Score minimum untuk eligible (skala 1-5)

    // Ideal Values untuk GAP Analysis
    const IDEAL_NOMINAL_AMOUNT = 10000000;     // Rp 10 juta (ideal nominal)
    const IDEAL_FREQUENCY_TRANSACTION = 10;    // 10 transaksi (ideal frequency)

    // Performance threshold
    const MIN_TRANSACTION_COUNT = 1;            // Minimum transaksi untuk mulai dihitung
    const MIN_TOTAL_AMOUNT = 100000;            // Minimum total nominal untuk valid

    public function __construct()
    {
        parent::__construct();
        $this->initializeTables();
    }

    /**
     * =====================================================
     * Inisialisasi Tabel Database
     * =====================================================
     */
    private function initializeTables()
    {
        // Tabel untuk menyimpan poin dari transaksi
        $sql1 = "CREATE TABLE IF NOT EXISTS customer_points (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pelanggan_id INT NOT NULL,
            sale_id INT,
            point_type ENUM('EARN', 'REDEEM', 'ADMIN') DEFAULT 'EARN',
            points INT NOT NULL DEFAULT 0,
            transaction_amount DECIMAL(12, 2),
            description TEXT,
            expired_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
            INDEX idx_pelanggan (pelanggan_id),
            INDEX idx_created (created_at)
        )";

        // Tabel untuk menyimpan hasil perhitungan Profile Matching
        $sql2 = "CREATE TABLE IF NOT EXISTS customer_reward_scoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pelanggan_id INT NOT NULL UNIQUE,
            scoring_month DATE,
            total_transaction_amount DECIMAL(12, 2) DEFAULT 0,
            transaction_count INT DEFAULT 0,
            nominal_score DECIMAL(5, 2) DEFAULT 0,
            frequency_score DECIMAL(5, 2) DEFAULT 0,
            final_score DECIMAL(5, 2) DEFAULT 0,
            eligibility_status ENUM('ELIGIBLE', 'NOT_ELIGIBLE') DEFAULT 'NOT_ELIGIBLE',
            last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
            INDEX idx_pelanggan (pelanggan_id),
            INDEX idx_status (eligibility_status),
            INDEX idx_month (scoring_month)
        )";

        // Tabel untuk audit log reset poin
        $sql3 = "CREATE TABLE IF NOT EXISTS reward_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pelanggan_id INT NOT NULL,
            action_type ENUM('RESET', 'EARN', 'REDEEM', 'ADMIN') DEFAULT 'ADMIN',
            points_before INT,
            points_after INT,
            description TEXT,
            admin_id INT,
            action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_pelanggan (pelanggan_id),
            INDEX idx_date (action_date)
        )";

        // Tabel untuk konfigurasi reward
        $sql4 = "CREATE TABLE IF NOT EXISTS reward_config (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value VARCHAR(255),
            setting_type ENUM('INT', 'FLOAT', 'STRING', 'BOOLEAN') DEFAULT 'STRING',
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        $this->query($sql1);
        $this->query($sql2);
        $this->query($sql3);
        $this->query($sql4);

        // Insert default configuration jika belum ada
        $this->insertDefaultConfig();
    }

    /**
     * =====================================================
     * Insert Default Configuration
     * =====================================================
     */
    private function insertDefaultConfig()
    {
        $configs = [
            ['reward_active', '1', 'BOOLEAN', 'Aktifkan sistem reward'],
            ['min_transaction_point', '100000', 'INT', 'Minimum nominal transaksi untuk mendapat poin'],
            ['point_per_rupiah', '10000', 'INT', 'Jumlah point per X rupiah'],
            ['point_expiry_months', '12', 'INT', 'Masa berlaku poin dalam bulan'],
            ['core_factor_weight', '60', 'INT', 'Bobot Score Nominal Belanja (%)'],
            ['secondary_factor_weight', '40', 'INT', 'Bobot Score Frekuensi Belanja (%)'],
            ['minimum_eligibility_score', '65', 'INT', 'Score minimum untuk ELIGIBLE (0-100)'],
            ['max_nominal_benchmark', '5000000', 'INT', 'Benchmark nominal maksimal untuk score 100'],
            ['max_frequency_benchmark', '5', 'INT', 'Benchmark frekuensi maksimal untuk score 100'],
        ];

        foreach ($configs as $config) {
            // Check apakah sudah ada
            $check = $this->query("SELECT setting_key FROM reward_config WHERE setting_key = '{$config[0]}'");
            if ($check->num_rows == 0) {
                $stmt = $this->prepare("
                    INSERT INTO reward_config 
                    (setting_key, setting_value, setting_type, description) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("ssss", $config[0], $config[1], $config[2], $config[3]);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /**
     * =====================================================
     * GAP ANALYSIS SCORE CALCULATION (1-5 SCALE)
     * =====================================================
     */

    /**
     * Hitung Score Nominal Belanja menggunakan GAP Analysis
     * GAP = ABS(Total - Ideal) / Ideal
     * Score = 5 - (GAP × 5), min 1, max 5
     */
    public function calculateNominalScore($totalAmount)
    {
        $totalAmount = floatval($totalAmount);
        $idealAmount = floatval(self::IDEAL_NOMINAL_AMOUNT);

        if ($idealAmount <= 0) {
            return 1;
        }

        $gap = abs($totalAmount - $idealAmount) / $idealAmount;
        $score = 5 - ($gap * 5);

        // Cap score ke range 1-5
        return max(1, min(5, round($score, 2)));
    }

    /**
     * Hitung Score Frekuensi Belanja menggunakan GAP Analysis
     * GAP = ABS(Count - Ideal) / Ideal
     * Score = 5 - (GAP × 5), min 1, max 5
     */
    public function calculateFrequencyScore($transactionCount)
    {
        $transactionCount = intval($transactionCount);
        $idealFrequency = intval(self::IDEAL_FREQUENCY_TRANSACTION);

        if ($idealFrequency <= 0) {
            return 1;
        }

        $gap = abs($transactionCount - $idealFrequency) / $idealFrequency;
        $score = 5 - ($gap * 5);

        // Cap score ke range 1-5
        return max(1, min(5, round($score, 2)));
    }

    /**
     * =====================================================
     * PROFILE MATCHING CALCULATION (1-5 SCALE)
     * =====================================================
     */

    /**
     * Hitung Profile Matching Score untuk satu pelanggan
     * Final Score = (Nominal Score × 60%) + (Frequency Score × 40%)
     * Range: 1-5 scale
     */
    public function calculateProfileMatchingScore($nominalScore, $frequencyScore)
    {
        $nominalScore = floatval($nominalScore);
        $frequencyScore = floatval($frequencyScore);

        // Profile Matching Formula (1-5 scale)
        $finalScore = ($nominalScore * self::CORE_FACTOR_WEIGHT) +
            ($frequencyScore * self::SECONDARY_FACTOR_WEIGHT);

        return round($finalScore, 2);
    }

    /**
     * Tentukan Status Eligibility berdasarkan Final Score (1-5 scale)
     * ELIGIBLE jika score >= 3.5
     */
    public function determineEligibility($finalScore)
    {
        $finalScore = floatval($finalScore);
        return $finalScore >= self::MINIMUM_ELIGIBILITY_SCORE ? 'ELIGIBLE' : 'NOT_ELIGIBLE';
    }

    /**
     * =====================================================
     * MAIN CALCULATION METHOD
     * =====================================================
     */

    /**
     * Hitung dan update scoring untuk satu pelanggan pada periode tertentu
     */
    public function calculateAndUpdateScore($pelangganId, $month = null, $year = null)
    {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        $pelangganId = intval($pelangganId);
        $scoringDate = "{$year}-{$month}-01";

        // Get transaksi pelanggan dalam bulan ini
        $transactionData = $this->getCustomerTransactionData($pelangganId, $month, $year);

        if (empty($transactionData)) {
            return [
                'status' => 'no_transaction',
                'message' => 'Pelanggan tidak memiliki transaksi pada periode ini'
            ];
        }

        // Hitung score untuk setiap kategori
        $totalAmount = floatval($transactionData['total_amount']);
        $transactionCount = intval($transactionData['transaction_count']);

        // Standarisasi score ke skala 0-100
        $nominalScore = $this->calculateNominalScore($totalAmount);
        $frequencyScore = $this->calculateFrequencyScore($transactionCount);

        // Profile Matching
        $finalScore = $this->calculateProfileMatchingScore($nominalScore, $frequencyScore);

        // Tentukan eligibility
        $eligibilityStatus = $this->determineEligibility($finalScore);

        // Update atau insert ke database
        $stmt = $this->prepare("
            INSERT INTO customer_reward_scoring 
            (pelanggan_id, scoring_month, total_transaction_amount, transaction_count, 
             nominal_score, frequency_score, final_score, eligibility_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_transaction_amount = VALUES(total_transaction_amount),
            transaction_count = VALUES(transaction_count),
            nominal_score = VALUES(nominal_score),
            frequency_score = VALUES(frequency_score),
            final_score = VALUES(final_score),
            eligibility_status = VALUES(eligibility_status),
            last_calculated = CURRENT_TIMESTAMP
        ");

        $stmt->bind_param(
            "isidddds",
            $pelangganId,
            $scoringDate,
            $totalAmount,
            $transactionCount,
            $nominalScore,
            $frequencyScore,
            $finalScore,
            $eligibilityStatus
        );

        if ($stmt->execute()) {
            $stmt->close();
            return [
                'status' => 'success',
                'pelanggan_id' => $pelangganId,
                'total_amount' => $totalAmount,
                'transaction_count' => $transactionCount,
                'nominal_score' => round($nominalScore, 2),
                'frequency_score' => round($frequencyScore, 2),
                'final_score' => $finalScore,
                'eligibility_status' => $eligibilityStatus,
                'message' => "Score berhasil dihitung untuk pelanggan #$pelangganId"
            ];
        } else {
            $stmt->close();
            return [
                'status' => 'failed',
                'message' => 'Gagal menyimpan score ke database'
            ];
        }
    }

    /**
     * =====================================================
     * DATA RETRIEVAL
     * =====================================================
     */

    /**
     * Ambil data transaksi pelanggan untuk periode tertentu
     */
    private function getCustomerTransactionData($pelangganId, $month, $year)
    {
        $pelangganId = intval($pelangganId);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $year = intval($year);

        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $sql = "
            SELECT 
                COUNT(*) as transaction_count,
                SUM(sale_total) as total_amount
            FROM sale
            WHERE cust_id = ?
            AND DATE(sale_date) BETWEEN ? AND ?
            AND sale_status NOT IN ('DELETED', 'CANCELLED')
        ";

        $stmt = $this->prepare($sql);
        $stmt->bind_param("iss", $pelangganId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && $row['transaction_count'] > 0) {
            return [
                'transaction_count' => intval($row['transaction_count']),
                'total_amount' => floatval($row['total_amount'] ?? 0)
            ];
        }

        return null;
    }

    /**
     * Ambil score pelanggan
     */
    public function getCustomerScore($pelangganId, $month = null, $year = null)
    {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        $pelangganId = intval($pelangganId);
        $scoringDate = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%";

        $stmt = $this->prepare("
            SELECT * FROM customer_reward_scoring 
            WHERE pelanggan_id = ? AND scoring_month LIKE ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $pelangganId, $scoringDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $score = $result->fetch_assoc();
        $stmt->close();

        return $score;
    }

    /**
     * Ambil poin pelanggan (total poin yang belum di-redeem)
     */
    public function getCustomerPoints($pelangganId)
    {
        $pelangganId = intval($pelangganId);

        $stmt = $this->prepare("
            SELECT COALESCE(SUM(points), 0) as total_points
            FROM customer_points
            WHERE pelanggan_id = ?
            AND (expired_at IS NULL OR expired_at > NOW())
        ");
        $stmt->bind_param("i", $pelangganId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return intval($row['total_points'] ?? 0);
    }

    /**
     * Ambil riwayat poin pelanggan
     */
    public function getCustomerPointHistory($pelangganId, $limit = 10)
    {
        $pelangganId = intval($pelangganId);
        $limit = intval($limit);

        $stmt = $this->prepare("
            SELECT * FROM customer_points
            WHERE pelanggan_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $pelangganId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];

        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        $stmt->close();
        return $history;
    }

    /**
     * =====================================================
     * POINT MANAGEMENT
     * =====================================================
     */

    /**
     * Tambah poin dari transaksi
     */
    public function earnPointsFromTransaction($pelangganId, $saleId, $transactionAmount)
    {
        require_once(__DIR__ . '/RewardSystem.php');
        $rewardSystem = new RewardSystem($this->conn, false);
        return $rewardSystem->syncEarnPointsForTransaction(
            (int)$pelangganId,
            (int)$saleId,
            (float)$transactionAmount
        );
    }

    /**
     * Kurangi poin (redeem)
     */
    public function redeemPoints($pelangganId, $points, $description = '')
    {
        $pelangganId = intval($pelangganId);
        $points = intval($points);

        $currentPoints = $this->getCustomerPoints($pelangganId);

        if ($currentPoints < $points) {
            return [
                'status' => 'failed',
                'message' => "Poin tidak mencukupi. Tersedia: $currentPoints, Diminta: $points"
            ];
        }

        $stmt = $this->prepare("
            INSERT INTO customer_points
            (pelanggan_id, point_type, points, description)
            VALUES (?, 'REDEEM', ?, ?)
        ");

        $negativePoints = -$points;
        $stmt->bind_param("iis", $pelangganId, $negativePoints, $description);

        if ($stmt->execute()) {
            $stmt->close();
            return [
                'status' => 'success',
                'message' => "Berhasil menggunakan $points poin"
            ];
        } else {
            $stmt->close();
            return [
                'status' => 'failed',
                'message' => 'Gagal mengurangi poin'
            ];
        }
    }

    /**
     * Reset poin pelanggan (untuk reward diberikan)
     */
    public function resetCustomerPoints($pelangganId, $adminId = 0, $reason = '')
    {
        $pelangganId = intval($pelangganId);
        $adminId = intval($adminId);

        $currentPoints = $this->getCustomerPoints($pelangganId);

        // Log reset
        $stmt = $this->prepare("
            INSERT INTO reward_history
            (pelanggan_id, action_type, points_before, points_after, description, admin_id)
            VALUES (?, 'RESET', ?, 0, ?, ?)
        ");

        $stmt->bind_param(
            "iisis",
            $pelangganId,
            $currentPoints,
            $reason,
            $adminId
        );

        if ($stmt->execute()) {
            // Delete semua poin yang sudah di-reset
            $stmt2 = $this->prepare("
                DELETE FROM customer_points
                WHERE pelanggan_id = ?
            ");
            $stmt2->bind_param("i", $pelangganId);
            if ($stmt2->execute()) {
                $stmt->close();
                $stmt2->close();
                return [
                    'status' => 'success',
                    'message' => "Berhasil reset $currentPoints poin pelanggan",
                    'points_before' => $currentPoints
                ];
            }
            $stmt2->close();
        }

        $stmt->close();
        return [
            'status' => 'failed',
            'message' => 'Gagal reset poin'
        ];
    }

    /**
     * =====================================================
     * BATCH OPERATIONS
     * =====================================================
     */

    /**
     * Hitung scoring untuk semua pelanggan dalam periode tertentu
     */
    public function calculateAllCustomerScores($month = null, $year = null)
    {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        // Get semua pelanggan yang memiliki transaksi
        $sql = "
            SELECT DISTINCT cust_id
            FROM sale
            WHERE YEAR(sale_date) = ?
            AND MONTH(sale_date) = ?
            AND sale_status NOT IN ('DELETED', 'CANCELLED')
        ";

        $stmt = $this->prepare($sql);
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $customerId = intval($row['cust_id']);
            if ($customerId > 0) {
                $calcResult = $this->calculateAndUpdateScore($customerId, $month, $year);
                $results[] = $calcResult;
            }
        }

        $stmt->close();

        return [
            'status' => 'success',
            'total_processed' => count($results),
            'results' => $results,
            'message' => "Berhasil memproses " . count($results) . " pelanggan"
        ];
    }

    /**
     * Get daftar pelanggan yang ELIGIBLE untuk reward
     */
    public function getEligibleCustomers($month = null, $year = null, $limit = 0)
    {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        $scoringDate = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%";

        $sql = "
            SELECT 
                crs.*,
                p.kode,
                p.nama,
                p.alamat,
                p.kota,
                p.no_telp,
                (SELECT COALESCE(SUM(points), 0) FROM customer_points WHERE pelanggan_id = crs.pelanggan_id) as total_points
            FROM customer_reward_scoring crs
            JOIN pelanggan p ON crs.pelanggan_id = p.id
            WHERE crs.scoring_month LIKE ?
            AND crs.eligibility_status = 'ELIGIBLE'
            ORDER BY crs.final_score DESC
        ";

        if ($limit > 0) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->prepare($sql);
        $stmt->bind_param("s", $scoringDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }

        $stmt->close();
        return $customers;
    }

    /**
     * Get ranking pelanggan berdasarkan score
     */
    public function getCustomerRanking($month = null, $year = null, $limit = 0)
    {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        // ✅ UPDATED: Query menggunakan customer_reward_scoring (GAP Analysis) sebagai source utama
        // Ini menampilkan semua pelanggan dengan transaksi, bukan hanya yang punya poin
        $sql = "
            SELECT 
                p.id as pelanggan_id,
                p.kode,
                p.nama,
                p.alamat,
                p.kota,
                p.telpon as nohp1,
                COALESCE(crs.total_transaction_amount, 0) as total_nominal,
                COALESCE(crs.transaction_count, 0) as transaction_count,
                COALESCE(crs.nominal_score, 0) as nominal_score,
                COALESCE(crs.frequency_score, 0) as frequency_score,
                COALESCE(crs.final_score, 0) as final_score,
                COALESCE(crs.eligibility_status, 'NOT_ELIGIBLE') as eligibility_status,
                COALESCE(cp_total.total_points, 0) as total_points
            FROM pelanggan p
            LEFT JOIN customer_reward_scoring crs ON p.id = crs.pelanggan_id
            LEFT JOIN (
                SELECT pelanggan_id, SUM(points) as total_points 
                FROM customer_points 
                GROUP BY pelanggan_id
            ) cp_total ON p.id = cp_total.pelanggan_id
            WHERE p.status = 1 AND (crs.transaction_count > 0 OR cp_total.total_points > 0)
            ORDER BY CASE WHEN crs.eligibility_status = 'ELIGIBLE' THEN 0 ELSE 1 END, cp_total.total_points DESC, crs.final_score DESC
        ";

        if ($limit > 0) {
            $sql .= " LIMIT " . intval($limit);
        }

        $result = $this->query($sql);

        $ranking = [];
        while ($row = $result->fetch_assoc()) {
            $ranking[] = $row;
        }

        return $ranking;
    }
}
