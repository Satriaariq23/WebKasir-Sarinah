<?php

require_once(__DIR__ . '/../config.php');

/**
 * CLASS: PointRewardSystem
 * Mengelola reward items, point settings, dan redemption
 */

class PointRewardSystem extends DBConnection
{
    public function __construct()
    {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->ensure_reward_items_table();
        $this->ensure_redemption_table();
    }

    private function ensure_reward_items_table()
    {
        $this->query("ALTER TABLE reward_items
            MODIFY status ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active'");
        $this->query("UPDATE reward_items SET status = 'deleted' WHERE status = ''");
    }

    private function ensure_redemption_table()
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

        $this->conn->query($sql);
    }

    // ============================================
    // REWARD ITEMS MANAGEMENT
    // ============================================

    public function get_reward_items()
    {
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $where = "WHERE status != 'deleted'";
        $params = [];

        if ($status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= " AND nama_reward LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $sql = "SELECT * FROM reward_items $where ORDER BY poin_dibutuhkan ASC";
        $data = $this->fetchAll($sql, $params);

        return $this->json_success('reward_items', $data);
    }

    public function add_reward_item()
    {
        $nama_reward = trim($_POST['nama_reward'] ?? $_POST['nama_hadiah'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $poin_dibutuhkan = (int)($_POST['poin_dibutuhkan'] ?? $_POST['poin_diperlukan'] ?? 0);
        $stok = (int)($_POST['stok'] ?? $_POST['qty'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (empty($nama_reward) || $poin_dibutuhkan <= 0) {
            return $this->json_error('Nama reward dan poin harus diisi dengan benar');
        }

        $stmt = $this->execute(
            "INSERT INTO reward_items (nama_reward, deskripsi, poin_dibutuhkan, stok, status)
             VALUES (?, ?, ?, ?, ?)",
            [$nama_reward, $deskripsi, $poin_dibutuhkan, $stok, $status]
        );
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        return $this->json_success('reward_item_added', ['id' => $this->conn->insert_id]);
    }

    public function update_reward_item()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_reward = trim($_POST['nama_reward'] ?? $_POST['nama_hadiah'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $poin_dibutuhkan = (int)($_POST['poin_dibutuhkan'] ?? $_POST['poin_diperlukan'] ?? 0);
        $stok = (int)($_POST['stok'] ?? $_POST['qty'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if ($id <= 0 || empty($nama_reward) || $poin_dibutuhkan <= 0) {
            return $this->json_error('Data tidak valid');
        }

        $stmt = $this->execute(
            "UPDATE reward_items
             SET nama_reward = ?, deskripsi = ?, poin_dibutuhkan = ?, stok = ?, status = ?
             WHERE id = ?",
            [$nama_reward, $deskripsi, $poin_dibutuhkan, $stok, $status, $id]
        );
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        return $this->json_success('reward_item_updated');
    }

    public function delete_reward_item()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            return $this->json_error('ID tidak valid');
        }

        $stmt = $this->execute("UPDATE reward_items SET status = 'deleted' WHERE id = ?", [$id]);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        return $this->json_success('reward_item_deleted');
    }

    // ============================================
    // POINT SETTINGS MANAGEMENT
    // ============================================

    public function get_point_settings()
    {
        $sql = "SELECT * FROM point_settings ORDER BY setting_key ASC";
        $result = $this->conn->query($sql);

        if (!$result) {
            return $this->json_error('Query error: ' . $this->conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['setting_key']] = $row;
        }

        return $this->json_success('point_settings', $data);
    }

    public function update_point_setting()
    {
        $setting_key = trim($_POST['setting_key'] ?? $_POST['key'] ?? '');
        $setting_value = trim($_POST['setting_value'] ?? $_POST['value'] ?? '');

        if (empty($setting_key)) {
            return $this->json_error('Setting key harus diisi');
        }

        $mapping = [
            'min_belanja_point' => 'min_transaction_point',
            'point_nilai_tukar' => 'point_per_rupiah',
            'max_poin_per_transaksi' => 'max_points_per_transaction',
            'status_poin_system' => 'reward_active',
        ];

        if (in_array($setting_key, ['min_belanja_point', 'point_nilai_tukar'], true) && (float)$setting_value <= 0) {
            return $this->json_error('Nilai pengaturan poin harus lebih dari nol');
        }
        if ($setting_key === 'max_poin_per_transaksi' && (int)$setting_value < 0) {
            return $this->json_error('Maksimum poin tidak boleh minus');
        }
        if ($setting_key === 'status_poin_system') {
            $setting_value = strtolower($setting_value) === 'active' ? 'active' : 'inactive';
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->execute(
                "UPDATE point_settings SET setting_value = ? WHERE setting_key = ?",
                [$setting_value, $setting_key]
            );
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }

            if (isset($mapping[$setting_key])) {
                $rewardValue = $setting_key === 'status_poin_system'
                    ? ($setting_value === 'active' ? '1' : '0')
                    : $setting_value;
                $rewardKey = $mapping[$setting_key];
                $stmt = $this->execute(
                    "INSERT INTO reward_settings (setting_key, setting_value, description)
                     VALUES (?, ?, 'Disinkronkan dari pengaturan poin')
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
                    [$rewardKey, $rewardValue]
                );
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            return $this->json_error('Gagal menyimpan pengaturan poin: ' . $e->getMessage());
        }

        return $this->json_success('point_setting_updated');
    }

    public function get_setting_value($key)
    {
        $row = $this->fetchOne(
            "SELECT setting_value FROM point_settings WHERE setting_key = ? LIMIT 1",
            [$key]
        );

        if ($row) {
            return $row['setting_value'];
        }

        return null;
    }

    // ============================================
    // POINT CALCULATION
    // ============================================

    public function calculate_points($total_belanja)
    {
        $min_belanja = (float)$this->get_setting_value('min_belanja_point');

        // Jika belanja kurang dari minimum (Rp 50.000), tidak dapat point
        if ($total_belanja < $min_belanja) {
            return 0;
        }

        $nilaiTukar = max(1, (float)$this->get_setting_value('point_nilai_tukar'));
        $poin = floor($total_belanja / $nilaiTukar);
        $maxPoin = (int)$this->get_setting_value('max_poin_per_transaksi');
        if ($maxPoin > 0) {
            $poin = min($poin, $maxPoin);
        }

        return (int)$poin;
    }

    // ============================================
    // REWARD REDEMPTION
    // ============================================

    public function redeem_reward($pelanggan_id, $reward_id, $poin_digunakan)
    {
        $pelanggan_id = (int)$pelanggan_id;
        $reward_id = (int)$reward_id;
        $poin_digunakan = (int)$poin_digunakan;

        if ($pelanggan_id <= 0 || $reward_id <= 0 || $poin_digunakan <= 0) {
            return $this->json_error('Data penukaran poin tidak valid');
        }

        $this->conn->begin_transaction();

        // Check reward exists and has stock
        $reward = $this->fetchOne(
            "SELECT * FROM reward_items WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE",
            [$reward_id]
        );

        if (!$reward) {
            $this->conn->rollback();
            return $this->json_error('Reward tidak ditemukan atau tidak aktif');
        }

        if ($reward['stok'] <= 0) {
            $this->conn->rollback();
            return $this->json_error('Reward stok habis');
        }

        // Check customer poin
        $pointRow = $this->fetchOne(
            "SELECT GREATEST(COALESCE(SUM(points), 0), 0) as total_poin
             FROM customer_points
             WHERE pelanggan_id = ?
               AND (expired_at IS NULL OR expired_at > NOW() OR points < 0)",
            [$pelanggan_id]
        );
        $customer_poin = (int)($pointRow['total_poin'] ?? 0);

        if ($customer_poin < $poin_digunakan) {
            $this->conn->rollback();
            return $this->json_error('Poin tidak cukup. Poin anda: ' . $customer_poin);
        }

        // Create redemption record using existing schema (points_used instead of poin_digunakan)
        $stmt = $this->execute(
            "INSERT INTO reward_redemption (pelanggan_id, reward_id, points_used, status)
             VALUES (?, ?, ?, 'PENDING')",
            [$pelanggan_id, $reward_id, $poin_digunakan]
        );
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        $redemption_id = $this->conn->insert_id;

        $negativePoints = -abs($poin_digunakan);
        $description = 'Penukaran reward #' . $redemption_id . ' - ' . $reward['nama_reward'];
        $stmt = $this->execute(
            "INSERT INTO customer_points (pelanggan_id, reward_redemption_id, point_type, points, description)
             VALUES (?, ?, 'REDEEM', ?, ?)",
            [$pelanggan_id, $redemption_id, $negativePoints, $description]
        );
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        // Update reward stock
        $stmt = $this->execute("UPDATE reward_items SET stok = stok - 1 WHERE id = ? AND stok > 0", [$reward_id]);
        if ($stmt instanceof mysqli_stmt) {
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                $this->conn->rollback();
                return $this->json_error('Stok reward berubah, silakan coba kembali');
            }
            $stmt->close();
        }

        $this->conn->commit();

        return $this->json_success('reward_redeemed', [
            'redemption_id' => $redemption_id,
            'reward_name' => $reward['nama_reward'],
            'poin_used' => $poin_digunakan
        ]);
    }

    public function get_redemption_history($pelanggan_id = null)
    {
        $where = "WHERE rr.status IN ('PENDING', 'APPROVED', 'USED')";
        $params = [];
        if ($pelanggan_id) {
            $pelanggan_id = (int)$pelanggan_id;
            $where .= " AND rr.pelanggan_id = ?";
            $params[] = $pelanggan_id;
        }

        $sql = "SELECT rr.*, ri.nama_reward, ri.poin_dibutuhkan, 
                       p.nama as nama_pelanggan, p.kode
                FROM reward_redemption rr
                JOIN reward_items ri ON ri.id = rr.reward_id
                JOIN pelanggan p ON p.id = rr.pelanggan_id
                $where
                ORDER BY rr.created_at DESC
                LIMIT 100";

        $data = $this->fetchAll($sql, $params);

        return $this->json_success('redemption_history', $data);
    }

    public function update_redemption_status()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $status = trim($_POST['status'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($id <= 0 || empty($status)) {
            return $this->json_error('Data tidak valid');
        }

        // Validate status (only valid enum values)
        $valid_statuses = ['PENDING', 'APPROVED', 'USED', 'EXPIRED', 'REJECTED'];
        if (!in_array($status, $valid_statuses)) {
            return $this->json_error('Status tidak valid');
        }

        $this->conn->begin_transaction();
        $redemption = $this->fetchOne(
            "SELECT id, pelanggan_id, reward_id, points_used, status FROM reward_redemption WHERE id = ? LIMIT 1 FOR UPDATE",
            [$id]
        );
        if (!$redemption) {
            $this->conn->rollback();
            return $this->json_error('Data penukaran tidak ditemukan');
        }

        $closedStatuses = ['REJECTED', 'EXPIRED'];
        if (in_array($redemption['status'], $closedStatuses, true) && $status !== $redemption['status']) {
            $this->conn->rollback();
            return $this->json_error('Penukaran yang sudah ditolak/kedaluwarsa tidak dapat dibuka kembali');
        }

        if (in_array($status, $closedStatuses, true) && !in_array($redemption['status'], $closedStatuses, true)) {
            $existingAdjust = $this->fetchOne(
                "SELECT id FROM customer_points WHERE reward_redemption_id = ? AND point_type = 'ADJUST' LIMIT 1",
                [$id]
            );
            if (!$existingAdjust) {
                $restorePoints = abs((int)$redemption['points_used']);
                $description = 'Pengembalian poin penukaran #' . $id . ' (' . $status . ')';
                $stmt = $this->execute(
                    "INSERT INTO customer_points (pelanggan_id, reward_redemption_id, point_type, points, description)
                     VALUES (?, ?, 'ADJUST', ?, ?)",
                    [(int)$redemption['pelanggan_id'], $id, $restorePoints, $description]
                );
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
                $stmt = $this->execute("UPDATE reward_items SET stok = stok + 1 WHERE id = ?", [(int)$redemption['reward_id']]);
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
            }
        }

        if ($status === 'APPROVED') {
            $stmt = $this->execute(
                "UPDATE reward_redemption SET status = ?, notes = ?, approved_at = NOW() WHERE id = ?",
                [$status, $catatan, $id]
            );
        } else {
            $stmt = $this->execute(
                "UPDATE reward_redemption SET status = ?, notes = ? WHERE id = ?",
                [$status, $catatan, $id]
            );
        }
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        $this->conn->commit();

        return $this->json_success('redemption_updated');
    }

    // ============================================
    // JSON HELPERS
    // ============================================

    private function json_success($key, $data = null)
    {
        echo json_encode([
            'status' => 'success',
            'message' => ucfirst(str_replace('_', ' ', $key)),
            'data' => $data
        ]);
        exit;
    }

    private function json_error($message)
    {
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }
}

// ============================================
// HANDLE REQUESTS
// ============================================

if (is_direct_script(__FILE__)) {
    require_admin_auth(1, true);
    require_csrf_for_unsafe_request(true);

    $action = request_action('');
    $reward = new PointRewardSystem();

    switch ($action) {
        case 'get_reward_items':
            $reward->get_reward_items();
            break;
        case 'add_reward_item':
            $reward->add_reward_item();
            break;
        case 'update_reward_item':
            $reward->update_reward_item();
            break;
        case 'delete_reward_item':
            $reward->delete_reward_item();
            break;
        case 'get_point_settings':
            $reward->get_point_settings();
            break;
        case 'update_point_setting':
            $reward->update_point_setting();
            break;
        case 'redeem_reward':
            $reward->redeem_reward($_POST['pelanggan_id'], $_POST['reward_id'], $_POST['poin_digunakan']);
            break;
        case 'get_redemption_history':
            $pelanggan_id = isset($_POST['pelanggan_id']) ? $_POST['pelanggan_id'] : null;
            $reward->get_redemption_history($pelanggan_id);
            break;
        case 'update_redemption_status':
            $reward->update_redemption_status();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action not found']);
            break;
    }
}
