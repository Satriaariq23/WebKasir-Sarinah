<?php

/**
 * ==========================================================
 *  FILE: admin/system_info/save_system_info.php
 *  DESCRIPTION: Endpoint untuk save system info
 * ==========================================================
 */

require_once('../../config.php');

// Check if user is logged in
if (!isset($_SESSION['userdata'])) {
    echo json_encode(['status' => 'failed', 'msg' => 'Unauthorized']);
    exit;
}

// Call update_settings_info method
if (isset($_settings)) {
    $result = $_settings->update_settings_info();
    echo $result;
} else {
    echo json_encode(['status' => 'failed', 'msg' => 'Settings object not found']);
}
