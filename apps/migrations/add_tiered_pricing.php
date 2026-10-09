<?php

/**
 * Migration: Add Tiered Pricing Columns (Multi-Tier Support)
 * This adds minimum quantity support for tiered pricing (P2, P3, P4)
 */

require_once('../config.php');

header('Content-Type: application/json');

if (php_sapi_name() !== 'cli') {
    require_admin_auth(1, true);
    require_csrf_for_unsafe_request(true);
}

$response = ['status' => 'failed', 'msg' => ''];

try {
    // Column counter
    $addedColumns = 0;

    // Check if columns exist by attempting to show columns
    $showColumns = $db->query("SHOW COLUMNS FROM produk");
    $columnNames = array();
    if ($showColumns) {
        while ($col = $showColumns->fetch_assoc()) {
            $columnNames[] = $col['Field'];
        }
    }

    // OPTION 1: If P2, P3, P4 already exist, add mqty columns
    $hasP2 = in_array('P2', $columnNames);
    $hasP3 = in_array('P3', $columnNames);
    $hasP4 = in_array('P4', $columnNames);

    if ($hasP2) {
        // Add mqty2 if not exists (minimum qty for P2 tier)
        if (!in_array('mqty2', $columnNames)) {
            $sql1 = "ALTER TABLE produk ADD COLUMN mqty2 INT DEFAULT 0 AFTER P2";
            if ($db->query($sql1)) {
                $addedColumns++;
            } else {
                // Non-fatal error - continue
                error_log("Warning: Failed to add mqty2: " . $db->error);
            }
        }
    }

    if ($hasP3) {
        // Add mqty3 if not exists (minimum qty for P3 tier)
        if (!in_array('mqty3', $columnNames)) {
            $sql2 = "ALTER TABLE produk ADD COLUMN mqty3 INT DEFAULT 0 AFTER P3";
            if ($db->query($sql2)) {
                $addedColumns++;
            } else {
                error_log("Warning: Failed to add mqty3: " . $db->error);
            }
        }
    }

    if ($hasP4) {
        // Add mqty4 if not exists (minimum qty for P4 tier)
        if (!in_array('mqty4', $columnNames)) {
            $sql3 = "ALTER TABLE produk ADD COLUMN mqty4 INT DEFAULT 0 AFTER P4";
            if ($db->query($sql3)) {
                $addedColumns++;
            } else {
                error_log("Warning: Failed to add mqty4: " . $db->error);
            }
        }
    }

    // OPTION 2: If P2, P3, P4 don't exist, create them with mqty columns
    if (!$hasP2 && !$hasP3 && !$hasP4) {
        // Add P2, mqty2, P3, mqty3, P4, mqty4 if none exist
        if (!in_array('P2', $columnNames)) {
            $sql_p2 = "ALTER TABLE produk ADD COLUMN P2 DECIMAL(16,2) DEFAULT 0 AFTER harga_kasir";
            if ($db->query($sql_p2)) {
                $addedColumns++;
            }
        }
        if (!in_array('mqty2', $columnNames)) {
            $sql_m2 = "ALTER TABLE produk ADD COLUMN mqty2 INT DEFAULT 0 AFTER P2";
            if ($db->query($sql_m2)) {
                $addedColumns++;
            }
        }
        if (!in_array('P3', $columnNames)) {
            $sql_p3 = "ALTER TABLE produk ADD COLUMN P3 DECIMAL(16,2) DEFAULT 0 AFTER mqty2";
            if ($db->query($sql_p3)) {
                $addedColumns++;
            }
        }
        if (!in_array('mqty3', $columnNames)) {
            $sql_m3 = "ALTER TABLE produk ADD COLUMN mqty3 INT DEFAULT 0 AFTER P3";
            if ($db->query($sql_m3)) {
                $addedColumns++;
            }
        }
        if (!in_array('P4', $columnNames)) {
            $sql_p4 = "ALTER TABLE produk ADD COLUMN P4 DECIMAL(16,2) DEFAULT 0 AFTER mqty3";
            if ($db->query($sql_p4)) {
                $addedColumns++;
            }
        }
        if (!in_array('mqty4', $columnNames)) {
            $sql_m4 = "ALTER TABLE produk ADD COLUMN mqty4 INT DEFAULT 0 AFTER P4";
            if ($db->query($sql_m4)) {
                $addedColumns++;
            }
        }
    }

    $response['status'] = 'success';
    $response['msg'] = $addedColumns > 0 ? "Migration completed. Columns added/checked: " . $addedColumns : "All required columns already exist";
    $response['columns_added'] = $addedColumns;
    $response['has_p2'] = $hasP2;
    $response['has_p3'] = $hasP3;
    $response['has_p4'] = $hasP4;
} catch (Exception $e) {
    $response['msg'] = $e->getMessage();
}

echo json_encode($response);
