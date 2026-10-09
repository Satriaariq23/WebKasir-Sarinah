<?php

/**
 * Pengaturan Settings - Point & Reward Router
 * Included from admin/index.php
 */

// Ensure all dependencies are loaded
if (!isset($_settings) || !isset($conn)) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/sarinah-apps/apps/config.php');
}

$subpage = $_GET['subpage'] ?? 'point_settings';

if ($subpage === 'reward_items') {
    include 'reward_items_content.php';
} else {
    include 'point_settings_content.php';
}
