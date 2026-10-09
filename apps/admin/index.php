<?php
/**
 * ==========================================================
 *  FILE: index.php (template utama admin)
 *  PURPOSE: Bootstrap halaman admin
 *  AUTHOR: GPT-5 🔨🤖
 * ==========================================================
 */

// Pastikan config diload
$basePath = dirname(__DIR__, 1) . '/config.php';
if (file_exists($basePath)) {
    require_once($basePath);
} else {
    die("<h3 style='color:red'>Config file tidak ditemukan: $basePath</h3>");
}

// Pastikan koneksi database aktif
try {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $db = new DBConnection();
        $conn = $db->conn;
    }
} catch (Exception $e) {
    die('<div class="alert alert-danger m-3"><strong>Gagal koneksi database:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// Pastikan SystemSettings aktif
if (!isset($_settings) || !is_object($_settings)) {
    $_settings = new SystemSettings();
    $_settings->load_system_info();
}

if (!isset($_GET['page'])) {
    $defaultPage = default_admin_page_for_role();
    if ($defaultPage !== 'home') {
        header('Location: ' . base_url . 'admin/?page=' . $defaultPage);
        exit;
    }
}

$requestedPage = normalize_admin_page($_GET['page'] ?? 'home');
$legacyPageRedirects = [
    'laporan' => 'laporan/sales',
    'penjualan/kasir_form' => 'penjualan/kasir',
    'reward' => 'pengaturan/index&subpage=reward_items',
    'reward/catalog' => 'pengaturan/index&subpage=reward_items',
    'reward/settings' => 'pengaturan/index&subpage=reward_items',
    'reward/criteria' => 'pengaturan/index&subpage=point_settings',
    'reward/redemption' => 'laporan/tukar_poin',
];

if (isset($legacyPageRedirects[$requestedPage])) {
    header('Location: ' . base_url . 'admin/?page=' . $legacyPageRedirects[$requestedPage]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" style="height:auto;">
<?php require_once('inc/header.php'); ?>
<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">

<script>start_loader()</script>

<div class="d-flex flex-column flex-root">
  <div class="page d-flex flex-row flex-column-fluid">

    <?php //require_once('inc/navigation.php'); ?> 

    <?php if ($_settings->chk_flashdata('success')): ?>
      <script>
        alert_toast("<?= addslashes($_settings->flashdata('success')) ?>",'success');
      </script>
    <?php endif; ?> 

    <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">

      <?php require_once('inc/topbarnav.php'); ?>

      <?php 
        // Cek dan tentukan halaman aktif
        $page = normalize_admin_page($_GET['page'] ?? 'home');
        if (!can_access_admin_page($page)) {
            echo '<div class="container-fluid py-10 px-5">
                    <div class="alert alert-danger">
                      <h4 class="mb-2">Akses ditolak</h4>
                      <div>Akun Anda tidak memiliki akses ke halaman ini.</div>
                      <a class="btn btn-sm btn-light mt-3" href="' . base_url . 'admin/?page=' . default_admin_page_for_role() . '">Kembali ke halaman utama</a>
                    </div>
                  </div>';
            require_once('inc/footer.php');
            echo '<script>$(document).ready(function(){ end_loader(); });</script></body></html>';
            exit;
        }
        $path = __DIR__ . '/' . $page;
        $filePath = $path . '.php';
        $indexPath = $path . '/index.php';

        if (is_dir($path) && file_exists($indexPath)) {
            include $indexPath;
        } elseif (file_exists($filePath)) {
            include $filePath;
        } else {
            include '404.html';
        }
      ?>

    </div><!-- end wrapper -->

  </div><!-- end page -->
</div><!-- end flex root -->

<!-- ================== GLOBAL MODALS ================== -->
<div class="modal fade" id="confirm_modal" role="dialog">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title">Konfirmasi</h1>
        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
          <span class="svg-icon svg-icon-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
              <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
              <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
            </svg>
          </span>
        </div>
      </div>
      <div class="modal-body">
        <div id="delete_content"></div>
      </div>
      <div class="modal-footer d-flex flex-row float-sm-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa fa-chevron-left"></i> Kembali</button>
        <button type="button" class="btn btn-primary" id="confirm">Ya <i class="fa fa-chevron-right"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- <div class="modal fade" id="uni_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered mw-650px">
    <div class="modal-content">
      <form class="form" action="#">
        <div class="modal-header">
          <h1 class="modal-title"></h1>
          <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
            <span class="svg-icon svg-icon-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
              </svg>
            </span>
          </div>
        </div>
        <div class="modal-body ui-front"></div>
        <div class="modal-footer flex-center">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa fa-chevron-left"></i> Kembali</button>
          <button type="button" id="submit" class="btn btn-primary" onclick="$('#uni_modal form').submit()">
            <span class="indicator-label">Simpan <i class="fa fa-chevron-right"></i></span>
            <span class="indicator-progress">Please wait...
              <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div> -->

<div class="modal fade" id="viewer_modal" role="dialog">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal"><span class="fa fa-times"></span></button>
      <img src="" alt="">
    </div>
  </div>
</div>

<?php require_once('inc/footer.php'); ?>

<script>
  $(document).ready(function() {
    end_loader();
  });
</script>

</body>
</html>
