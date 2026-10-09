        <!--begin::Aside-->
        <div id="kt_aside" class="aside aside-light aside-hoverable bg-dark" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
          <!--begin::Brand-->
          <div class="aside-logo flex-column-auto" id="kt_aside_logo">
            <!--begin::Logo-->
            <?php require_once('role_menu.php'); // Load role-based menu configuration 
            ?>
            <a>
              <img alt="Logo" src="<?php echo validate_image($_settings->info('logo')) ?>" class="h-40px logo" />
            </a>
            <!--end::Logo-->
            <!--begin::Aside toggler-->
            <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
              <!--begin::Svg Icon | path: icons/duotune/arrows/arr079.svg-->
              <span class="svg-icon svg-icon-1 rotate-180">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black" />
                  <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black" />
                </svg>
              </span>
              <!--end::Svg Icon-->
            </div>
            <!--end::Aside toggler-->
          </div>
          <!--end::Brand-->
          <!--begin::Aside menu-->
          <div class="aside-menu flex-column-fluid">
            <!--begin::Aside Menu-->
            <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
              <!--begin::Menu-->
              <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="#kt_aside_menu" data-kt-menu="true">
                <div class="menu-item">
                  <div class="menu-content pb-2">
                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Home</span>
                  </div>
                </div>
                <!-- dashboard -->
                <div class="menu-item">
                  <a class="menu-link" href="./">
                    <span class="menu-icon">
                      <!--begin::Svg Icon | path: icons/duotune/general/gen025.svg-->
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-speedometer2" viewBox="0 0 16 16">
                          <path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4zM3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707zM2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10zm9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5zm.754-4.246a.389.389 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.389.389 0 0 0-.029-.518z" />
                          <path fill-rule="evenodd" d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 1.602-2.932 1.25C11.309 13.488 9.475 13 8 13c-1.474 0-3.31.488-4.615.911-1.087.352-2.49.003-2.932-1.25A7.988 7.988 0 0 1 0 10zm8-7a7 7 0 0 0-6.603 9.329c.203.575.923.876 1.68.63C4.397 12.533 6.358 12 8 12s3.604.532 4.923.96c.757.245 1.477-.056 1.68-.631A7 7 0 0 0 8 3z" />
                        </svg>
                      </span>
                      <!--end::Svg Icon-->
                    </span>
                    <span class="menu-title text-white">Dashboard</span>
                  </a>
                </div>
                <div class="menu-item">
                  <div class="menu-content pt-8 pb-2">
                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Apps</span>
                  </div>
                </div>
                <!-- MASTER DATA -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-database" viewBox="0 0 16 16">
                          <path d="M4.318 2.687C5.234 2.271 6.536 2 8 2s2.766.27 3.682.687C12.644 3.125 13 3.627 13 4c0 .374-.356.875-1.318 1.313C10.766 5.729 9.464 6 8 6s-2.766-.27-3.682-.687C3.356 4.875 3 4.373 3 4c0-.374.356-.875 1.318-1.313M13 5.698V7c0 .374-.356.875-1.318 1.313C10.766 8.729 9.464 9 8 9s-2.766-.27-3.682-.687C3.356 7.875 3 7.373 3 7V5.698c.271.202.58.378.904.525C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777A5 5 0 0 0 13 5.698M14 4c0-1.007-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1s-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4v9c0 1.007.875 1.755 1.904 2.223C4.978 15.71 6.427 16 8 16s3.022-.289 4.096-.777C13.125 14.755 14 14.007 14 13zm-1 4.698V10c0 .374-.356.875-1.318 1.313C10.766 11.729 9.464 12 8 12s-2.766-.27-3.682-.687C3.356 10.875 3 10.373 3 10V8.698c.271.202.58.378.904.525C4.978 9.71 6.427 10 8 10s3.022-.289 4.096-.777A5 5 0 0 0 13 8.698m0 3V13c0 .374-.356.875-1.318 1.313C10.766 14.729 9.464 15 8 15s-2.766-.27-3.682-.687C3.356 13.875 3 13.373 3 13v-1.302c.271.202.58.378.904.525C4.978 12.71 6.427 13 8 13s3.022-.289 4.096-.777c.324-.147.633-.323.904-.525" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Master Data</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <!-- Barang-->
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=barang">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Data Barang</span>
                      </a>
                    </div>
                    <!-- Member -->
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=pelanggan">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Pelanggan & Member</span>
                      </a>
                    </div>
                    <!-- Supplier-->
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=supplier">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Supplier</span>
                      </a>
                    </div>
                    <!-- Sales-->
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=salesman">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Salesman</span>
                      </a>
                    </div>
                    <!-- Karyawan-->
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=karyawan">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Karyawan</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- TRANSAKSI -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart3" viewBox="0 0 16 16">
                          <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l.84 4.479 9.144-.459L13.89 4zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Transaksi</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=penjualan">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Penjualan Sales</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=penjualan/kasir">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Penjualan Kasir</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=pembelian">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Pembelian</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=retur-pembelian">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Retur Pembelian</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=retur-penjualan">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Retur Penjualan Sales</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=riwayat-transaksi">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Riwayat Transaksi</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- STOK -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box" viewBox="0 0 16 16">
                          <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5 8 5.961 14.154 3.5zM15 4.239l-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Data Stok</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=stok">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Stok Barang</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=kartu-stok">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Kartu Stok</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=stok-opname">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Stok Opname</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- HUTANG SUPPLIER -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard2-pulse" viewBox="0 0 16 16">
                          <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5h3Z" />
                          <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-12Z" />
                          <path d="M9.979 5.356a.5.5 0 0 0-.968.04L7.92 10.49l-.94-3.135a.5.5 0 0 0-.926-.08L4.69 10H4.5a.5.5 0 0 0 0 1H5a.5.5 0 0 0 .447-.276l.936-1.873 1.138 3.793a.5.5 0 0 0 .968-.04L9.58 7.51l.94 3.135A.5.5 0 0 0 11 11h.5a.5.5 0 0 0 0-1h-.128L9.979 5.356Z" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Manajemen Hutang</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=hutang">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Hutang &amp; Pembayaran</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- PIUTANG PELANGGAN -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard2-pulse" viewBox="0 0 16 16">
                          <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5h3Z" />
                          <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-12Z" />
                          <path d="M9.979 5.356a.5.5 0 0 0-.968.04L7.92 10.49l-.94-3.135a.5.5 0 0 0-.926-.08L4.69 10H4.5a.5.5 0 0 0 0 1H5a.5.5 0 0 0 .447-.276l.936-1.873 1.138 3.793a.5.5 0 0 0 .968-.04L9.58 7.51l.94 3.135A.5.5 0 0 0 11 11h.5a.5.5 0 0 0 0-1h-.128L9.979 5.356Z" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Manajemen Piutang</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=piutang">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Piutang &amp; Penerimaan</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- LAPORAN -->
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                  <span class="menu-link">
                    <span class="menu-icon">
                      <span class="svg-icon svg-icon-2 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16">
                          <path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1H5z" />
                          <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z" />
                        </svg>
                      </span>
                    </span>
                    <span class="menu-title text-white">Laporan</span>
                    <span class="menu-arrow"></span>
                  </span>
                  <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/sales">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Laporan Sales</span>
                      </a>
                    </div>
                    <?php if (in_array(current_user_level(), [1, 4], true)): ?>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/ppn">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Laporan PPN &amp; Non-PPN</span>
                      </a>
                    </div>
                    <?php endif; ?>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/laba_kotor">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Laba Kotor</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/produk_terlaris">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Produk Terlaris</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/setoran_kasir">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Setoran Kasir</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/kartu_poin">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Kartu Poin</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=reward">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Rekap Poin</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=reward/redemption">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Tukar Poin</span>
                      </a>
                    </div>
                    <div class="menu-item">
                      <a class="menu-link" href="<?php echo base_url ?>admin/?page=laporan/profit_penjualan">
                        <span class="menu-bullet">
                          <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title text-white">Profit Penjualan</span>
                      </a>
                    </div>
                  </div>
                </div>
                <!-- ADMIN -->
                <?php if ($_settings->userdata('user_type') < 3): ?>
                  <div class="menu-item">
                    <div class="menu-content pt-8 pb-0">
                      <span class="menu-section text-muted text-uppercase fs-8 ls-1">Admin</span>
                    </div>
                  </div>
                  <div class="menu-item">
                    <a class="menu-link" href="<?php echo base_url ?>admin/?page=log-aktivitas">
                      <span class="menu-icon">
                        <span class="svg-icon svg-icon-2 text-white">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                            <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.018.042zM4.696 6.61A.5.5 0 0 0 4.5 7v4a.5.5 0 0 0 .191.656l3 1.5a.5.5 0 0 0 .618-.118l2-3a.5.5 0 0 0-.82-.596l-1.56 2.341V7a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v.191l-.856-.514a.5.5 0 0 0-.61.144zM8 13A5 5 0 1 1 8 3a5 5 0 0 1 0 10z" />
                          </svg>
                        </span>
                      </span>
                      <span class="menu-title text-white">Log Aktivitas</span>
                    </a>
                  </div>
                  <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                    <span class="menu-link">
                      <span class="menu-icon">
                        <span class="svg-icon svg-icon-2 text-white">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
                            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z" />
                            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z" />
                          </svg>
                        </span>
                      </span>
                      <span class="menu-title text-white">Apps Settings</span>
                      <span class="menu-arrow"></span>
                    </span>
                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                      <?php if ($_settings->userdata('user_type') == 1) { ?>
                        <div class="menu-item">
                          <a class="menu-link" href="<?php echo base_url ?>admin/?page=user">
                            <span class="menu-bullet">
                              <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title text-white">Users</span>
                          </a>
                        </div>
                      <?php } else { ?>
                        <div class="menu-item">
                          <a class="menu-link" href="<?php echo base_url ?>admin/?page=user/user_level_admin">
                            <span class="menu-bullet">
                              <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title text-white">Users</span>
                          </a>
                        </div>
                      <?php } ?>
                      <div class="menu-item">
                        <a class="menu-link" href="<?php echo base_url ?>admin/?page=system_info">
                          <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                          </span>
                          <span class="menu-title text-white">Settings</span>
                        </a>
                      </div>
                      <div class="menu-item">
                        <a class="menu-link" href="<?php echo base_url ?>admin/?page=pengaturan/index&subpage=point_settings">
                          <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                          </span>
                          <span class="menu-title text-white">Pengaturan Point</span>
                        </a>
                      </div>
                      <div class="menu-item">
                        <a class="menu-link" href="<?php echo base_url ?>admin/?page=pengaturan/index&subpage=reward_items">
                          <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                          </span>
                          <span class="menu-title text-white">Pengaturan Reward</span>
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="menu-item">
                  <div class="menu-content">
                    <div class="separator mx-1 my-4"></div>
                  </div>
                </div>
              </div>
              <!--end::Menu-->
            </div>
            <!--end::Aside Menu-->
          </div>
          <!--end::Aside menu-->

        </div>
        <!--end::Aside-->
        <script type="text/javascript">
          $(document).ready(function() {
            $('#create_new_target').click(function() {
              uni_modal("Target Baru", "apage/sales-target/sales_target_modal.php", "modal-lg")
            })
            $('#create_new_career').click(function() {
              uni_modal("Lowongan Kerja Baru", "apage/website-settings/career_modal.php", "modal-lg")
            })
          })
        </script>
