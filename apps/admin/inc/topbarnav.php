<?php
$levelId = (int)($_settings->userdata('level_id') ?? 0);
$isAdmin = $levelId === 1;
$isKasir = $levelId === 2;
$isSales = $levelId === 3;
$isSupervisor = $levelId === 4;

$menuGroups = [];

if ($isAdmin || $isSupervisor) {
    $menuGroups = [
        'Master Data' => [
            ['Data Barang', 'barang'],
            ['Pelanggan & Member', 'pelanggan'],
            ['Supplier', 'supplier'],
            ['Salesman', 'salesman'],
            ['Karyawan', 'karyawan'],
        ],
        'Transaksi' => [
            ['Penjualan Sales', 'penjualan'],
            ['Penjualan Kasir', 'penjualan/kasir'],
            ['Pembelian', 'pembelian'],
            ['Retur Pembelian', 'retur-pembelian'],
            ['Retur Penjualan Sales', 'retur-penjualan'],
            ['Riwayat Transaksi', 'riwayat-transaksi'],
        ],
        'Manajemen Stok' => [
            ['Stok Barang', 'stok'],
            ['Kartu Stok', 'kartu-stok'],
            ['Stok Opname', 'stok-opname'],
        ],
        'Manajemen Hutang' => [
            ['Hutang & Pembayaran', 'hutang'],
        ],
        'Manajemen Piutang' => [
            ['Piutang & Penerimaan', 'piutang'],
        ],
        'Laporan' => [
            ['Laporan Sales', 'laporan/sales'],
            ['Laporan Omset', 'laporan/omset'],
            ['Laporan PPN & Non-PPN', 'laporan/ppn'],
            ['Laba Kotor', 'laporan/laba_kotor'],
            ['Produk Terlaris', 'laporan/produk_terlaris'],
            ['Setoran Kasir', 'laporan/setoran_kasir'],
            ['Kartu Poin', 'laporan/kartu_poin'],
            ['Rekap Poin', 'laporan/rekap_poin'],
            ['Tukar Poin', 'laporan/tukar_poin'],
            ['Profit Penjualan', 'laporan/profit_penjualan'],
        ],
        'Pengaturan' => [
            ['Manajemen Users', 'user'],
            ['Log Aktivitas', 'log-aktivitas'],
            ['System Info', 'system_info'],
            ['Pengaturan Point', 'pengaturan/index&subpage=point_settings'],
            ['Pengaturan Reward', 'pengaturan/index&subpage=reward_items'],
        ],
    ];
} elseif ($isKasir) {
    $menuGroups = [
        'Transaksi Kasir' => [
            ['Penjualan Kasir', 'penjualan/kasir'],
        ],
    ];
} elseif ($isSales) {
    $menuGroups = [
        'Transaksi Sales' => [
            ['Penjualan Sales', 'penjualan'],
        ],
    ];
}

function nav_page_url($page)
{
    return base_url . 'admin/?page=' . $page;
}
?>

<div id="kt_header" class="header align-items-stretch bg-dark">
  <div class="container-fluid d-flex align-items-stretch justify-content-between">
    <a class="navbar-brand d-flex flex-column align-items-start justify-content-center" href="<?php echo base_url ?>admin/">
      <div class="text-success fw-bolder fs-2 lh-1"><?php echo $_settings->info('name') ?></div>
      <div class="badge badge-warning text-dark fw-semibold fs-8 mt-1 text-uppercase">
        <?php echo $_settings->userdata('firstname') ?>
      </div>
    </a>

    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
      <div class="d-flex align-items-stretch" id="kt_header_nav">
        <div class="header-menu align-items-stretch">
          <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" data-kt-menu="true">
            <?php foreach ($menuGroups as $groupTitle => $items): ?>
              <div data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start" class="menu-item menu-lg-down-accordion me-lg-1">
                <span class="menu-link py-3">
                  <span class="menu-title text-gray-400"><?php echo htmlspecialchars($groupTitle) ?></span>
                  <span class="menu-arrow d-lg-none"></span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown menu-rounded-0 py-lg-4 w-lg-225px">
                  <?php foreach ($items as [$label, $page]): ?>
                    <?php if (can_access_admin_page(strtok($page, '&')) || $isAdmin): ?>
                      <div class="menu-item">
                        <a class="menu-link" href="<?php echo nav_page_url($page) ?>">
                          <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                          <span class="menu-title"><?php echo htmlspecialchars($label) ?></span>
                        </a>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-stretch flex-shrink-0">
        <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
          <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
            <img src="<?php echo get_user_avatar($_settings->userdata('avatar')) ?>" alt="user" />
          </div>
          <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
            <div class="menu-item px-3">
              <div class="menu-content d-flex align-items-center px-3">
                <div class="symbol symbol-50px me-5">
                  <img alt="Logo" src="<?php echo get_user_avatar($_settings->userdata('avatar')) ?>" />
                </div>
                <div class="d-flex flex-column">
                  <div class="fw-bolder d-flex align-items-center fs-5"><?php echo $_settings->userdata('firstname') ?></div>
                  <span class="fw-bolder text-gray-600 fs-6"><?php echo $_settings->userdata('level_nama') ?: get_role_name($levelId) ?></span>
                </div>
              </div>
            </div>
            <div class="separator my-2"></div>
            <div class="menu-item px-5">
              <form method="post" action="<?php echo base_url . 'classes/Login.php?f=logout' ?>" class="m-0">
                <?php echo csrf_input() ?>
                <button type="submit" class="menu-link px-5 w-100 border-0 bg-transparent text-start">Sign Out</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
