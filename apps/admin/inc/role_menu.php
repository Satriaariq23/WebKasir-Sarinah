<?php

/**
 * ==========================================================
 *  FILE: inc/role_menu.php
 *  DESCRIPTION: Navigation menu items dengan role-based filtering
 *  AUTHOR: Role-Based Access Control System
 * ==========================================================
 */

/**
 * 🎯 Menu Items Mapping per Role
 * Role IDs:
 * 1 = Admin (Full Access)
 * 2 = Kasir
 * 3 = Salesman
 * 4 = Supervisor
 */

// Define menu structure
$menu_items = [
    // Master Data Section
    'barang' => ['roles' => [1, 2, 3], 'title' => 'Data Barang', 'icon' => 'database'],
    'pelanggan' => ['roles' => [1, 2, 3], 'title' => 'Pelanggan & Member', 'icon' => 'users'],
    'supplier' => ['roles' => [1, 2, 3], 'title' => 'Supplier', 'icon' => 'truck'],
    'salesman' => ['roles' => [1, 2, 3], 'title' => 'Salesman', 'icon' => 'user-tie'],

    // Transaction Section
    'pembelian' => ['roles' => [1, 2, 3], 'title' => 'Pembelian', 'icon' => 'shopping-cart'],
    'penjualan' => ['roles' => [1, 2, 3, 4], 'title' => 'Penjualan', 'icon' => 'receipt'],
    'penjualan/kasir' => ['roles' => [1, 2], 'title' => 'Kasir', 'icon' => 'cash-register'],
    'retur-pembelian' => ['roles' => [1, 2, 3], 'title' => 'Retur Pembelian', 'icon' => 'undo'],
    'retur-penjualan' => ['roles' => [1, 2, 3], 'title' => 'Retur Penjualan', 'icon' => 'undo'],
    'riwayat-transaksi' => ['roles' => [1], 'title' => 'Riwayat Transaksi', 'icon' => 'history'],

    // Finance Section
    'hutang' => ['roles' => [1, 2, 3], 'title' => 'Hutang', 'icon' => 'coins'],
    'piutang' => ['roles' => [1, 2, 3], 'title' => 'Piutang', 'icon' => 'money-bill'],

    // Stock Section
    'stok' => ['roles' => [1, 2, 3], 'title' => 'Stok', 'icon' => 'boxes'],
    'stok-opname' => ['roles' => [1, 2, 3], 'title' => 'Stok Opname', 'icon' => 'clipboard-check'],
    'kartu-stok' => ['roles' => [1, 2, 3], 'title' => 'Kartu Stok', 'icon' => 'id-card'],

    // Reports Section
    'laporan' => ['roles' => [1, 2, 3], 'title' => 'Laporan', 'icon' => 'chart-bar'],
    'laporan/sales' => ['roles' => [1, 2, 3], 'title' => 'Laporan Sales', 'icon' => 'chart-line'],
    'laporan/ppn' => ['roles' => [1, 4], 'title' => 'Laporan PPN & Non-PPN', 'icon' => 'receipt'],

    // Loyalty Section
    'reward' => ['roles' => [1, 2, 3], 'title' => 'Reward/Loyalty', 'icon' => 'gift'],

    // System Section
    'karyawan' => ['roles' => [1, 2], 'title' => 'Karyawan', 'icon' => 'briefcase'],
    'user' => ['roles' => [1, 2], 'title' => 'User Management', 'icon' => 'shield'],
    'system_info' => ['roles' => [1, 2], 'title' => 'System Settings', 'icon' => 'cog'],
    'log-aktivitas' => ['roles' => [1, 2], 'title' => 'Log Aktivitas', 'icon' => 'history'],
    'point_settings' => ['roles' => [1, 2], 'title' => 'Pengaturan Point', 'icon' => 'star'],
    'reward_items' => ['roles' => [1, 2], 'title' => 'Pengaturan Reward', 'icon' => 'gift'],
];

/**
 * 🔍 Check jika menu item boleh ditampilkan untuk user ini
 * @param string $menuKey - Key dari menu item
 * @return bool - true jika boleh ditampilkan
 */
function can_show_menu($menuKey)
{
    global $menu_items;

    if (function_exists('current_user_level') && current_user_level() === 4) {
        return true;
    }

    // Default allow jika menu tidak ada di config
    if (!isset($menu_items[$menuKey])) {
        return true;
    }

    $item = $menu_items[$menuKey];
    return check_role_access($item['roles']);
}

/**
 * 🎯 Get title menu dari key
 * @param string $menuKey - Key dari menu item
 * @return string - Title menu
 */
function get_menu_title($menuKey)
{
    global $menu_items;
    return $menu_items[$menuKey]['title'] ?? ucfirst(str_replace('_', ' ', $menuKey));
}

// Jangan close PHP di sini - biarkan file yang include ini yang manage
