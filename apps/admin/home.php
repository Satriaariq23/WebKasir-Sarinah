<?php
$dashboardLevelId = current_user_level();
$primaryDashboardAction = null;

if ($dashboardLevelId === 2) {
    $primaryDashboardAction = [
        'label' => 'Buka Penjualan Kasir',
        'text' => 'Akses kasir aktif untuk membuat transaksi penjualan.',
        'page' => 'penjualan/kasir',
        'class' => 'btn-primary',
    ];
} elseif ($dashboardLevelId === 3) {
    $primaryDashboardAction = [
        'label' => 'Buka Penjualan Sales',
        'text' => 'Akses sales aktif untuk membuat penjualan baru.',
        'page' => 'penjualan',
        'class' => 'btn-success',
    ];
}
?>
<!--begin::Container-->
<div id="kt_content_container" class="d-flex flex-column-fluid align-items-start container-xxl">
    <!--begin::Post-->
    <div class="content flex-row-fluid" id="kt_content">
        <!--begin::Page Title-->
        <div class="page-title d-print-none mb-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-2">
                <div class="d-flex flex-column">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                        Dashboard
                    </h1>
                    <span class="text-muted fs-7 fw-semibold mt-1">Ringkasan Penjualan dan Manajemen Stok</span>
                </div>
                <div class="fs-7 fw-semibold">
                    <span class="badge badge-light">Diperbarui: <span id="last_update">--:--</span></span>
                </div>
            </div>
        </div>
        <!--end::Page Title-->

        <?php if ($primaryDashboardAction && can_access_admin_page($primaryDashboardAction['page'])): ?>
            <div class="alert alert-dismissible bg-light-primary border border-primary d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-4 p-5 mb-5">
                <div>
                    <div class="fw-bold text-dark fs-5"><?php echo get_role_name($dashboardLevelId) ?></div>
                    <div class="text-gray-700 fs-7"><?php echo $primaryDashboardAction['text'] ?></div>
                </div>
                <a href="<?php echo base_url ?>admin/?page=<?php echo $primaryDashboardAction['page'] ?>" class="btn <?php echo $primaryDashboardAction['class'] ?> btn-sm">
                    <?php echo $primaryDashboardAction['label'] ?>
                </a>
            </div>
        <?php endif; ?>

        <!--begin::Row 1 - Main Stats-->
        <div class="row gy-5 g-xl-8 mb-5">
            <!--Today's Sales Card-->
            <div class="col-xl-3 col-lg-6">
                <div class="card card-flush h-xl-100">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2 fw-bolder text-dark">Penjualan Hari Ini</span>
                            <span class="text-muted fs-7 fw-semibold mt-1" id="today_date"><?php echo date("d M Y"); ?></span>
                        </div>
                        <div class="card-toolbar">
                            <?php if (can_access_admin_page('penjualan')): ?>
                            <a href="<?php echo base_url ?>admin/?page=penjualan" class="btn btn-icon btn-color-gray-400 btn-active-color-primary justify-content-end" title="Kelola Penjualan">
                                <i class="ki-duotone ki-arrow-right fs-1"><span class="path1"></span><span class="path2"></span></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body d-flex align-items-end pt-0">
                        <div class="d-flex align-items-center flex-column mt-3 w-100">
                            <div class="d-flex justify-content-between w-100 mb-2">
                                <span class="fw-bolder text-gray-600 fs-7">Total</span>
                                <span class="fw-bolder text-dark fs-7" id="today_sales_value">Rp. 0</span>
                            </div>
                            <div class="d-flex justify-content-between w-100 mb-2">
                                <span class="fw-semibold text-gray-600 fs-7">Transaksi</span>
                                <span class="fw-semibold text-dark fs-8" id="today_count">0</span>
                            </div>
                            <div id="today_chart" style="height: 80px; min-height: 80px; margin: 10px 0;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!--Monthly Sales Card-->
            <div class="col-xl-3 col-lg-6">
                <div class="card card-flush h-xl-100">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2 fw-bolder text-dark">Penjualan Bulan Ini</span>
                            <span class="text-muted fs-7 fw-semibold mt-1"><?php echo date("M Y"); ?></span>
                        </div>
                    </div>
                    <div class="card-body d-flex align-items-end pt-0">
                        <div class="d-flex align-items-center flex-column mt-3 w-100">
                            <div class="d-flex justify-content-between w-100 mb-2">
                                <span class="fw-bolder text-gray-600 fs-7">Total</span>
                                <span class="fw-bolder text-dark fs-7" id="month_sales_value">Rp. 0</span>
                            </div>
                            <div class="d-flex justify-content-between w-100 mb-2">
                                <span class="fw-semibold text-gray-600 fs-7">Transaksi</span>
                                <span class="fw-semibold text-dark fs-8" id="month_count">0</span>
                            </div>
                            <div id="month_chart" style="height: 80px; min-height: 80px; margin: 10px 0;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!--Products Card-->
            <div class="col-xl-3 col-lg-6">
                <div class="card card-flush bg-light-success h-xl-100">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2 fw-bolder text-dark">Total Produk</span>
                            <span class="text-muted fs-7 fw-semibold mt-1">Stok Tersedia</span>
                        </div>
                        <div class="card-toolbar">
                            <?php if (can_access_admin_page('barang')): ?>
                            <a href="<?php echo base_url ?>admin/?page=barang" class="btn btn-icon btn-color-gray-400 btn-active-color-primary justify-content-end" title="Kelola Produk">
                                <i class="ki-duotone ki-arrow-right fs-1"><span class="path1"></span><span class="path2"></span></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body d-flex align-items-end pt-0">
                        <div class="fs-2 fw-bolder text-success" id="total_products">0</div>
                        <span class="text-muted fs-7 fw-semibold ms-2">Total Item</span>
                    </div>
                </div>
            </div>

            <!--Low Stock Card-->
            <div class="col-xl-3 col-lg-6">
                <div class="card card-flush bg-light-danger h-xl-100">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2 fw-bolder text-dark">Stok Rendah</span>
                            <span class="text-muted fs-7 fw-semibold mt-1">&lt; 10 Unit</span>
                        </div>
                        <div class="card-toolbar">
                            <?php if (can_access_admin_page('barang')): ?>
                            <a href="<?php echo base_url ?>admin/?page=barang" class="btn btn-icon btn-color-gray-400 btn-active-color-primary justify-content-end" title="Perbarui Stok">
                                <i class="ki-duotone ki-arrow-right fs-1"><span class="path1"></span><span class="path2"></span></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body d-flex align-items-end pt-0">
                        <div class="fs-2 fw-bolder text-danger" id="low_stock">0</div>
                        <span class="text-muted fs-7 fw-semibold ms-2">Produk</span>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row 1-->

        <!--begin::Row 2 - Secondary Stats & Best Sellers-->
        <div class="row gy-5 g-xl-8">
            <!--Best Sellers Card-->
            <div class="col-xl-6">
                <div class="card card-flush h-xl-100">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Produk Terlaris</span>
                            <span class="text-muted mt-1 fw-semibold fs-7"><?php echo date('M Y') ?></span>
                        </h3>
                        <div class="card-toolbar">
                            <?php if (can_access_admin_page('laporan/produk_terlaris')): ?>
                            <a href="<?php echo base_url . 'admin?page=laporan/produk_terlaris' ?>" class="btn btn-sm btn-light">
                                Lihat Semua
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body pt-5" id="best_sellers_container">
                        <div class="text-center py-10">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!--Customer & Member Stats-->
            <div class="col-xl-6">
                <div class="row gy-5 g-xl-8">
                    <!--Total Customers Card-->
                    <div class="col-lg-6">
                        <div class="card card-flush bg-light-info h-100">
                            <div class="card-header border-0 pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2 fw-bolder text-dark">Total Pelanggan</span>
                                    <span class="text-muted fs-7 fw-semibold mt-1">Aktif</span>
                                </div>
                                <div class="card-toolbar">
                                    <?php if (can_access_admin_page('pelanggan')): ?>
                                    <a href="<?php echo base_url ?>admin/?page=pelanggan" class="btn btn-icon btn-color-gray-400 btn-active-color-primary justify-content-end" title="Kelola Pelanggan">
                                        <i class="ki-duotone ki-arrow-right fs-1"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <div class="fs-2 fw-bolder text-info" id="total_customers">0</div>
                                <span class="text-muted fs-7 fw-semibold ms-2">Orang</span>
                            </div>
                        </div>
                    </div>

                    <!--Total Members Card-->
                    <div class="col-lg-6">
                        <div class="card card-flush bg-light-warning h-100">
                            <div class="card-header border-0 pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2 fw-bolder text-dark">Member Aktif</span>
                                    <span class="text-muted fs-7 fw-semibold mt-1">Loyalty</span>
                                </div>
                                <div class="card-toolbar">
                                    <?php if (can_access_admin_page('pelanggan/member')): ?>
                                    <a href="<?php echo base_url ?>admin/?page=pelanggan/member" class="btn btn-icon btn-color-gray-400 btn-active-color-primary justify-content-end" title="Kelola Member">
                                        <i class="ki-duotone ki-arrow-right fs-1"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <div class="fs-2 fw-bolder text-warning" id="total_members">0</div>
                                <span class="text-muted fs-7 fw-semibold ms-2">Orang</span>
                            </div>
                        </div>
                    </div>

                    <!--Daily Sales Trend-->
                    <div class="col-12">
                        <div class="card card-flush">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bolder text-dark">Tren Penjualan (7 Hari Terakhir)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-3">
                                <div id="daily_trend_chart" style="height: 250px; min-height: 250px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row 2-->

        <!--begin::Row 3 - Customer Loyalty Points-->
        <div class="row gy-5 g-xl-8 mt-5">
            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-header border-0 pt-5">
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bolder text-dark">Top Member Loyalty Points</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Poin pelanggan aktif bulanan</span>
                            </h3>
                            <div class="card-toolbar">
                                <?php if (can_access_admin_page('pelanggan/member')): ?>
                                <a href="<?php echo base_url ?>admin/?page=pelanggan/member" class="btn btn-sm btn-light">
                                    Kelola Member
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="customer_points_table">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-50px">#</th>
                                        <th class="min-w-150px">Nama Member</th>
                                        <th class="min-w-100px">Kode</th>
                                        <th class="text-end min-w-100px">Total Points</th>
                                    </tr>
                                </thead>
                                <tbody id="customer_points_tbody">
                                    <tr>
                                        <td colspan="4" class="text-center py-10">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row 3-->

        <script>
            $(document).ready(function() {
                // Load dashboard data
                load_dashboard_data();

                // Refresh dashboard every 30 seconds
                setInterval(load_dashboard_data, 30000);
            });

            function load_dashboard_data() {
                $.ajax({
                    url: "<?php echo base_url ?>classes/Master.php?f=get_dashboard_stats",
                    type: 'POST',
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            // Update Today's Sales
                            $('#today_sales_value').text('Rp. ' + format_currency(resp.today_sales.nilai));
                            $('#today_count').text(parseInt(resp.today_sales.jumlah_transaksi) + ' Transaksi');

                            // Update Monthly Sales
                            $('#month_sales_value').text('Rp. ' + format_currency(resp.month_sales.nilai));
                            $('#month_count').text(parseInt(resp.month_sales.jumlah_transaksi) + ' Transaksi');

                            // Update Stats
                            $('#total_products').text(format_number(resp.total_products));
                            $('#low_stock').text(format_number(resp.low_stock));
                            $('#total_customers').text(format_number(resp.total_customers));
                            $('#total_members').text(format_number(resp.total_members));

                            // Update timestamp
                            const now = new Date();
                            $('#last_update').text(now.toLocaleTimeString('id-ID'));

                            // Update Best Sellers
                            update_best_sellers(resp.best_sellers);

                            // Update Customer Points Table
                            update_customer_points_table(resp.customer_points);

                            // Load charts
                            load_dashboard_charts(resp.daily_sales);
                        } else {
                            console.error('Error loading dashboard:', resp.msg);
                        }
                    },
                    error: function() {
                        console.log('Error loading dashboard data');
                    }
                });
            }

            function update_best_sellers(best_sellers) {
                let html = '';

                if (best_sellers && best_sellers.length > 0) {
                    best_sellers.forEach(function(item, index) {
                        const badgeClass = index === 0 ? 'bg-light-success' : (index === 1 ? 'bg-light-warning' : 'bg-light-info');
                        const badgeText = index === 0 ? '🥇' : (index === 1 ? '🥈' : '🥉');

                        html += `
                <div class="d-flex align-items-center gap-3 mb-5">
                    <div class="symbol symbol-50px ${badgeClass}">
                        <div class="symbol-label fs-5">${badgeText}</div>
                    </div>
                    <div class="flex-grow-1">
                        <a href="javascript:void(0)" class="text-dark fw-bolder text-hover-primary d-block fs-6">${item.nama_produk}</a>
                        <div class="text-muted fs-8">
                            <span>${format_number(item.total_qty)}</span> unit • 
                            <span class="fw-semibold text-success">Rp. ${format_currency(item.total_harga)}</span>
                        </div>
                    </div>
                </div>
            `;
                    });
                } else {
                    html = '<div class="text-center text-muted py-10"><i class="ki-duotone ki-information fs-2x mb-3"><span class="path1"></span><span class="path2"></span></i><div>Belum ada data penjualan</div></div>';
                }

                $('#best_sellers_container').html(html);
            }

            function update_customer_points_table(customer_points) {
                let html = '';

                if (customer_points && customer_points.length > 0) {
                    customer_points.forEach(function(item, index) {
                        const badgeClass = index === 0 ? 'badge-light-success' : (index === 1 ? 'badge-light-warning' : 'badge-light-info');
                        const medalIcon = index === 0 ? '⭐' : (index === 1 ? '✨' : '💎');

                        html += `
                <tr>
                    <td>
                        <span class="fw-bold">${index + 1}</span>
                    </td>
                    <td>
                        <span class="fw-bold text-dark">${medalIcon} ${item.nama}</span>
                    </td>
                    <td>
                        <span class="badge badge-light">${item.kode}</span>
                    </td>
                    <td class="text-end">
                        <span class="badge ${badgeClass} fs-7 fw-bolder">${format_number(item.total_points)} pts</span>
                    </td>
                </tr>
            `;
                    });
                } else {
                    html = `
            <tr>
                <td colspan="4" class="text-center text-muted py-10">
                    <i class="ki-duotone ki-information fs-2x mb-3"><span class="path1"></span><span class="path2"></span></i>
                    <div>Belum ada member dengan poin</div>
                </td>
            </tr>
            `;
                }

                $('#customer_points_tbody').html(html);
            }

            function load_dashboard_charts(daily_sales) {
                if (typeof ApexCharts !== 'undefined') {
                    // Daily Trend Chart
                    create_daily_trend_chart(daily_sales);
                }
            }

            function create_daily_trend_chart(daily_sales) {
                const dates = [];
                const values = [];

                if (daily_sales && daily_sales.length > 0) {
                    daily_sales.forEach(function(item) {
                        dates.push(item.date);
                        values.push(item.nilai);
                    });
                }

                const options = {
                    series: [{
                        name: "Penjualan (Rp)",
                        data: values
                    }],
                    chart: {
                        type: "area",
                        height: 250,
                        toolbar: {
                            show: false
                        },
                        sparkline: {
                            enabled: false
                        }
                    },
                    colors: ["#3B82F6"],
                    fill: {
                        type: "gradient",
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [20, 100, 100, 100]
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        width: 2,
                        curve: "smooth"
                    },
                    xaxis: {
                        type: "category",
                        categories: dates,
                        axisBorder: {
                            show: true,
                            color: '#e5e7eb'
                        },
                        axisTicks: {
                            show: false
                        },
                        labels: {
                            show: true,
                            style: {
                                colors: '#9CA3AF',
                                fontSize: '12px',
                                fontFamily: 'inherit'
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Nominal (Rp)'
                        },
                        labels: {
                            style: {
                                colors: '#9CA3AF',
                                fontSize: '12px',
                                fontFamily: 'inherit'
                            },
                            formatter: function(value) {
                                return 'Rp ' + (value / 1000000).toFixed(0) + 'M';
                            }
                        }
                    },
                    grid: {
                        show: true,
                        borderColor: '#e5e7eb',
                        padding: {
                            left: 0,
                            right: 0,
                            top: 0,
                            bottom: 0
                        }
                    }
                };

                if (window['chart_daily'] !== undefined) {
                    window['chart_daily'].destroy();
                }

                window['chart_daily'] = new ApexCharts(document.querySelector("#daily_trend_chart"), options);
                window['chart_daily'].render();
            }

            function format_number(num) {
                if (!num) return '0';
                return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            function format_currency(num) {
                if (!num) return '0';
                const numStr = parseInt(num).toString();
                return numStr.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
        </script>
