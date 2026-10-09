<?php
require_once('../../config.php');

require_admin_auth(1, true);
require_csrf_for_unsafe_request(true);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function ensure_reward_items_status_schema($db)
{
    $db->query("ALTER TABLE reward_items
                MODIFY status ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active'");
    $db->query("UPDATE reward_items SET status = 'deleted' WHERE status = ''");
}

ensure_reward_items_status_schema($db);

$action = request_action('none');
$response = ['status' => 'error', 'message' => 'Invalid action'];

try {
    switch ($action) {
        case 'get_reward_items':
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');

            $where = ["status != 'deleted'"];
            $params = [];

            if ($status !== '') {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            if ($search !== '') {
                $where[] = '(nama_reward LIKE ? OR deskripsi LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $sql = "SELECT id, nama_reward, deskripsi, poin_dibutuhkan, stok, status
                    FROM reward_items
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY poin_dibutuhkan ASC";
            $response = ['status' => 'success', 'data' => $db->fetchAll($sql, $params)];
            break;

        case 'add_reward_item':
        case 'update_reward_item':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['nama_hadiah'] ?? $_POST['nama_reward'] ?? '');
            $poin = (int)($_POST['poin_diperlukan'] ?? $_POST['poin_dibutuhkan'] ?? 0);
            $stok = (int)($_POST['qty'] ?? $_POST['stok'] ?? 0);
            $desc = trim($_POST['deskripsi'] ?? '');
            $status = trim($_POST['status'] ?? 'active');

            if (!in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }

            if ($name === '' || $poin <= 0 || $stok < 0) {
                json_response(['status' => 'error', 'message' => 'Data reward tidak valid'], 422);
            }

            if ($action === 'add_reward_item') {
                $stmt = $db->execute(
                    "INSERT INTO reward_items (nama_reward, poin_dibutuhkan, stok, deskripsi, status)
                     VALUES (?, ?, ?, ?, ?)",
                    [$name, $poin, $stok, $desc, $status]
                );
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
                $response = ['status' => 'success', 'message' => 'Added'];
            } else {
                if ($id <= 0) {
                    json_response(['status' => 'error', 'message' => 'ID reward tidak valid'], 422);
                }

                $stmt = $db->execute(
                    "UPDATE reward_items
                     SET nama_reward = ?, poin_dibutuhkan = ?, stok = ?, deskripsi = ?, status = ?
                     WHERE id = ?",
                    [$name, $poin, $stok, $desc, $status, $id]
                );
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
                $response = ['status' => 'success', 'message' => 'Updated'];
            }
            break;

        case 'delete_reward_item':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['status' => 'error', 'message' => 'ID reward tidak valid'], 422);
            }

            $stmt = $db->execute("UPDATE reward_items SET status = 'deleted' WHERE id = ?", [$id]);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            $response = ['status' => 'success', 'message' => 'Deleted'];
            break;

        case 'get_point_settings':
            $rows = $db->fetchAll("SELECT setting_key, setting_value, deskripsi FROM point_settings ORDER BY setting_key ASC");
            $data = [];
            foreach ($rows as $row) {
                $data[$row['setting_key']] = $row;
            }
            $response = ['status' => 'success', 'data' => $data];
            break;

        case 'update_point_setting':
            $key = trim($_POST['key'] ?? '');
            $value = trim($_POST['value'] ?? '');
            $allowedKeys = [
                'min_belanja_point',
                'frekuensi_belanja',
                'max_poin_per_transaksi',
                'point_nilai_tukar',
                'status_poin_system',
            ];

            if (!in_array($key, $allowedKeys, true) || $value === '') {
                json_response(['status' => 'error', 'message' => 'Setting tidak valid'], 422);
            }

            $mapping = [
                'min_belanja_point' => 'min_transaction_point',
                'point_nilai_tukar' => 'point_per_rupiah',
                'max_poin_per_transaksi' => 'max_points_per_transaction',
                'status_poin_system' => 'reward_active',
            ];
            if (in_array($key, ['min_belanja_point', 'point_nilai_tukar'], true) && (float)$value <= 0) {
                json_response(['status' => 'error', 'message' => 'Nilai pengaturan poin harus lebih dari nol'], 422);
            }
            if ($key === 'max_poin_per_transaksi' && (int)$value < 0) {
                json_response(['status' => 'error', 'message' => 'Maksimum poin tidak boleh minus'], 422);
            }
            if ($key === 'status_poin_system') {
                $value = strtolower($value) === 'active' ? 'active' : 'inactive';
            }

            $db->conn->begin_transaction();
            try {
                $stmt = $db->execute("UPDATE point_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
                if (isset($mapping[$key])) {
                    $rewardValue = $key === 'status_poin_system' ? ($value === 'active' ? '1' : '0') : $value;
                    $stmt = $db->execute(
                        "INSERT INTO reward_settings (setting_key, setting_value, description)
                         VALUES (?, ?, 'Disinkronkan dari pengaturan poin')
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
                        [$mapping[$key], $rewardValue]
                    );
                    if ($stmt instanceof mysqli_stmt) {
                        $stmt->close();
                    }
                }
                $db->conn->commit();
            } catch (Throwable $e) {
                $db->conn->rollback();
                throw $e;
            }
            $response = ['status' => 'success', 'message' => 'Updated'];
            break;

        case 'get_summary':
            $members = $db->fetchOne("
                SELECT COUNT(*) AS c
                FROM (
                    SELECT pelanggan_id
                    FROM customer_points
                    WHERE expired_at IS NULL OR expired_at > NOW() OR points < 0
                    GROUP BY pelanggan_id
                    HAVING SUM(points) > 0
                ) balances
            ");
            $points = $db->fetchOne("
                SELECT COALESCE(SUM(balance), 0) AS t
                FROM (
                    SELECT GREATEST(SUM(points), 0) AS balance
                    FROM customer_points
                    WHERE expired_at IS NULL OR expired_at > NOW() OR points < 0
                    GROUP BY pelanggan_id
                ) balances
            ");
            $rewards = $db->fetchOne("SELECT COUNT(*) AS c FROM reward_items WHERE status = 'active'");

            $response = ['status' => 'success', 'data' => [
                'total_members' => (int)($members['c'] ?? 0),
                'total_points' => (int)($points['t'] ?? 0),
                'active_rewards' => (int)($rewards['c'] ?? 0),
            ]];
            break;
    }
} catch (Exception $e) {
    error_log('[pengaturan/api] ' . $e->getMessage());
    json_response(['status' => 'error', 'message' => 'Terjadi kesalahan server'], 500);
}

echo json_encode($response);
