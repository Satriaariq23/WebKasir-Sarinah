<div class="content-wrapper py-3 px-3">
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Poin Aktif</h6>
                    <h3 id="total_active_points">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Pelanggan Ber-Poin</h6>
                    <h3 id="customers_with_points">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Penukaran Bulan Ini</h6>
                    <h3 id="redemptions_this_month">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Poin Ditukar Bulan Ini</h6>
                    <h3 id="points_redeemed_this_month">0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Matching Section -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Profile Matching - Pelanggan Berhak Reward</h5>
                    <div class="d-flex gap-2">
                        <select id="filter_month" class="form-select form-select-sm" style="width:120px">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                                    <?= DateTime::createFromFormat('!m', $m)->format('F') ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select id="filter_year" class="form-select form-select-sm" style="width:100px">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button class="btn btn-primary btn-sm" id="btn_calculate">
                            <i class="bi bi-calculator me-1"></i>Hitung Ulang
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table_matching" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Rank</th>
                                    <th>Kode</th>
                                    <th>Nama Pelanggan</th>
                                    <th>Total Transaksi</th>
                                    <th>Frekuensi</th>
                                    <th>NCF</th>
                                    <th>NSF</th>
                                    <th>Skor Akhir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Poin Tertinggi</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="top_customers">
                        <li class="list-group-item text-center text-muted">Loading...</li>
                    </ul>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Menu Reward</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=reward/catalog" class="btn btn-outline-primary">
                            <i class="bi bi-gift me-2"></i>Katalog Reward
                        </a>
                        <a href="?page=reward/criteria" class="btn btn-outline-info">
                            <i class="bi bi-sliders me-2"></i>Pengaturan Kriteria
                        </a>
                        <a href="?page=reward/redemption" class="btn btn-outline-success">
                            <i class="bi bi-receipt me-2"></i>Riwayat Penukaran
                        </a>
                        <a href="?page=reward/settings" class="btn btn-outline-secondary">
                            <i class="bi bi-gear-fill me-2"></i>Pengaturan Sistem
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Pelanggan -->
<div class="modal fade" id="modal_detail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Profile Matching</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informasi Pelanggan</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Kode</td>
                                <td><strong id="detail_kode"></strong></td>
                            </tr>
                            <tr>
                                <td>Nama</td>
                                <td><strong id="detail_nama"></strong></td>
                            </tr>
                            <tr>
                                <td>Total Poin</td>
                                <td><strong id="detail_points" class="text-primary"></strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Hasil Profile Matching</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Total Transaksi</td>
                                <td id="detail_transaksi"></td>
                            </tr>
                            <tr>
                                <td>Frekuensi Belanja</td>
                                <td id="detail_frekuensi"></td>
                            </tr>
                            <tr>
                                <td>GAP Transaksi</td>
                                <td id="detail_gap_transaksi"></td>
                            </tr>
                            <tr>
                                <td>GAP Frekuensi</td>
                                <td id="detail_gap_frekuensi"></td>
                            </tr>
                            <tr>
                                <td>NCF (Core Factor)</td>
                                <td id="detail_ncf"></td>
                            </tr>
                            <tr>
                                <td>NSF (Secondary Factor)</td>
                                <td id="detail_nsf"></td>
                            </tr>
                            <tr>
                                <td>Skor Akhir</td>
                                <td><strong id="detail_score" class="text-success"></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <hr>
                <h6>Reward yang Berhak Didapatkan</h6>
                <div id="eligible_rewards" class="row"></div>
            </div>
        </div>
    </div>
</div>

<script>
    const rewardApi = '../classes/RewardSystem.php';

    // Load dashboard stats
    function loadDashboardStats() {
        $.get(rewardApi + '?f=get_dashboard_stats', function(response) {
            if (response.status === 'success') {
                const data = response.data;
                $('#total_active_points').text(Number(data.total_active_points).toLocaleString('id-ID'));
                $('#customers_with_points').text(data.customers_with_points);
                $('#redemptions_this_month').text(data.redemptions_this_month);
                $('#points_redeemed_this_month').text(Number(data.points_redeemed_this_month).toLocaleString('id-ID'));

                // Top customers
                let topHtml = '';
                if (data.top_customers.length > 0) {
                    data.top_customers.forEach((c, i) => {
                        const badges = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                        topHtml += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${badges[i]} ${c.nama}</span>
                            <span class="badge bg-primary">${Number(c.total_points).toLocaleString('id-ID')} poin</span>
                        </li>
                    `;
                    });
                } else {
                    topHtml = '<li class="list-group-item text-center text-muted">Belum ada data</li>';
                }
                $('#top_customers').html(topHtml);
            }
        }, 'json');
    }

    // Load matching results
    function loadMatchingResults() {
        const month = $('#filter_month').val();
        const year = $('#filter_year').val();

        $.get(rewardApi + '?f=get_matching_results&month=' + month + '&year=' + year, function(response) {
            if (response.status === 'success') {
                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(r => {
                        const scoreClass = r.final_score >= 4 ? 'text-success' : r.final_score >= 3 ? 'text-warning' : 'text-danger';
                        html += `
                        <tr>
                            <td><span class="badge bg-secondary">#${r.ranking}</span></td>
                            <td>${r.kode}</td>
                            <td>${r.nama}</td>
                            <td>Rp ${Number(r.total_transaksi).toLocaleString('id-ID')}</td>
                            <td>${r.frekuensi_belanja}x</td>
                            <td>${Number(r.ncf).toFixed(2)}</td>
                            <td>${Number(r.nsf).toFixed(2)}</td>
                            <td><strong class="${scoreClass}">${Number(r.final_score).toFixed(2)}</strong></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="showDetail(${JSON.stringify(r).replace(/"/g, '&quot;')})">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    });
                } else {
                    html = '<tr><td colspan="9" class="text-center text-muted">Belum ada data untuk periode ini. Klik "Hitung Ulang" untuk menghitung.</td></tr>';
                }
                $('#table_matching tbody').html(html);
            }
        }, 'json');
    }

    // Calculate matching
    $('#btn_calculate').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Menghitung...');

        $.post(rewardApi, {
            action: 'calculate_matching',
            month: $('#filter_month').val(),
            year: $('#filter_year').val()
        }, function(response) {
            btn.prop('disabled', false).html('<i class="bi bi-calculator me-1"></i>Hitung Ulang');
            if (response.status === 'success') {
                Swal.fire('Berhasil', `Profile matching untuk ${response.total} pelanggan berhasil dihitung!`, 'success');
                loadMatchingResults();
            } else {
                Swal.fire('Error', response.msg || 'Terjadi kesalahan', 'error');
            }
        }, 'json');
    });

    // Show detail
    function showDetail(data) {
        $('#detail_kode').text(data.kode);
        $('#detail_nama').text(data.nama);
        $('#detail_transaksi').text('Rp ' + Number(data.total_transaksi).toLocaleString('id-ID'));
        $('#detail_frekuensi').text(data.frekuensi_belanja + 'x');
        $('#detail_gap_transaksi').text(data.gap_transaksi);
        $('#detail_gap_frekuensi').text(data.gap_frekuensi);
        $('#detail_ncf').text(Number(data.ncf).toFixed(2));
        $('#detail_nsf').text(Number(data.nsf).toFixed(2));
        $('#detail_score').text(Number(data.final_score).toFixed(2));

        // Get points
        $.get(rewardApi + '?f=get_customer_points&pelanggan_id=' + data.pelanggan_id, function(res) {
            $('#detail_points').text(Number(res.points || 0).toLocaleString('id-ID') + ' poin');
        }, 'json');

        // Get eligible rewards
        $.get(rewardApi + '?f=get_reward_catalog', function(res) {
            let rewardHtml = '';
            if (res.data && res.data.length > 0) {
                res.data.forEach(r => {
                    if (r.min_score <= data.final_score) {
                        rewardHtml += `
                        <div class="col-md-6 mb-2">
                            <div class="card border-success">
                                <div class="card-body p-2">
                                    <h6 class="mb-1">${r.reward_name}</h6>
                                    <small class="text-muted">${r.description || ''}</small>
                                    <div class="mt-1">
                                        <span class="badge bg-primary">${r.points_required} poin</span>
                                        <span class="badge bg-info">Min skor: ${r.min_score}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    }
                });
            }
            if (!rewardHtml) {
                rewardHtml = '<div class="col-12 text-muted">Tidak ada reward yang berhak didapat dengan skor ini</div>';
            }
            $('#eligible_rewards').html(rewardHtml);
        }, 'json');

        $('#modal_detail').modal('show');
    }

    // Filter change
    $('#filter_month, #filter_year').on('change', loadMatchingResults);

    // Init
    $(document).ready(function() {
        loadDashboardStats();
        loadMatchingResults();
    });
</script>