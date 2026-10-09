<?php

/**
 * ==========================================================
 *  FILE: admin/laporan/ranking_point.php
 *  DESCRIPTION: Ranking Point Pelanggan
 * ==========================================================
 */
?>

<div class="content-wrapper py-3 px-3">
    <div class="card">
        <div class="card-header">
            <h3>Rekap Poin</h3>
        </div>
        <div class="card-body">
            <!-- Navigation Tabs -->
            <div class="mb-3">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url ?>admin/?page=laporan/rekap_harian">Rekap Harian</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url ?>admin/?page=laporan/ranking_point">Ranking Point</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url ?>admin/?page=laporan/rekap_point_member">Rekap Point Member</a>
                    </li>
                </ul>
            </div>
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="search-customer" placeholder="Cari nama atau kode pelanggan...">
                        <select class="form-select" id="filter-eligibility" style="max-width: 250px;">
                            <option value="">Semua Pelanggan</option>
                            <option value="ELIGIBLE">Memenuhi Syarat ✓</option>
                            <option value="NOT_ELIGIBLE">Belum Memenuhi</option>
                        </select>
                        <button class="btn btn-outline-secondary" id="btn-clear-filter" type="button">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-success" id="btn-export-excel" title="Download ranking sebagai file Excel">
                            <i class="bi bi-file-earmark-excel"></i> Download Excel
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-reset-all-points" title="Reset semua poin pelanggan (Admin only)">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Semua Poin
                        </button>
                    </div>
                </div>
            </div>
            <table id="table-ranking" class="table table-hover table-bordered align-middle" style="width:100%">
                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                    <tr>
                        <th class="text-center" style="width: 5%;">Ranking</th>
                        <th style="width: 8%;">Kode</th>
                        <th>Nama Member</th>
                        <th class="text-center" style="width: 12%;">Total Point</th>
                        <th class="text-center" style="width: 15%;">Status</th>
                        <th class="text-center" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted" id="info-loaded">Menampilkan data...</small>
                <small class="text-muted" id="info-total">Total: -</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Pelanggan dengan Profile Matching -->
<div class="modal fade" id="modal-detail-pelanggan" tabindex="-1" role="dialog" aria-labelledby="modal-label-detail" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modal-label-detail">
                    <i class="bi bi-person-circle"></i> Detail Pelanggan & Analisa Profile Matching
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modal-detail-content">
                <div class="text-center">
                    <i class="bi bi-hourglass-split"></i> Memuat data...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btn-refresh-detail" onclick="reloadDetailModal()">
                    <i class="bi bi-arrow-repeat"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset Confirmation -->
<div class="modal fade" id="modal-reset-points" tabindex="-1" role="dialog" aria-labelledby="modal-label-reset" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="modal-label-reset">
                    <i class="bi bi-exclamation-triangle"></i> Konfirmasi Reset Semua Poin
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <strong>⚠️ Perhatian!</strong> Tindakan ini akan mereset semua poin pelanggan ke 0. Proses ini tidak dapat dibatalkan dan akan dicatat dalam log sistem.
                </div>
                <div class="mb-3">
                    <label class="form-label">Alasan Reset:</label>
                    <textarea class="form-control" id="reset-reason" rows="3" placeholder="Masukkan alasan reset (opsional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-reset" onclick="confirmResetAllPoints()">
                    <i class="bi bi-arrow-counterclockwise"></i> Ya, Reset Semua Poin
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const baseUrl = "<?php echo base_url; ?>classes/Master.php";
    let allRankingData = [];
    let pointSettings = {};
    let customerScoreMap = {};

    /**
     * Load point settings dan ranking data
     */
    function initializeData() {
        // Load point settings
        $.ajax({
            url: baseUrl + "?f=get_point_settings_data",
            type: "GET",
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success') {
                    pointSettings = resp.data;
                }
            }
        });

        // Load ranking data
        loadRankingData();
    }

    /**
     * Load ranking point data dengan limit 20
     */
    function loadRankingData() {
        $.ajax({
            url: baseUrl + "?f=get_customer_ranking",
            type: "POST",
            data: {
                month: new Date().getMonth() + 1,
                year: new Date().getFullYear(),
                limit: 100
            },
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success' && resp.ranking && resp.ranking.length > 0) {
                    allRankingData = resp.ranking;
                    renderRankingTable();
                } else {
                    $('#table-ranking tbody').html('<tr><td colspan="6" class="text-center text-danger py-5"><i class="bi bi-inbox"></i> Belum ada data ranking point. Transaksi belanja pertama kali diperlukan untuk mengumpulkan poin.</td></tr>');
                    $('#info-total').text('Total: 0');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error, xhr);
                $('#table-ranking tbody').html('<tr><td colspan="6" class="text-center text-danger py-5">❌ Gagal memuat data ranking. Error: ' + error + '</td></tr>');
            }
        });
    }

    /**
     * Render tabel ranking dengan filter
     */
    function renderRankingTable() {
        const searchTerm = $('#search-customer').val().toLowerCase().trim();
        const eligibilityFilter = $('#filter-eligibility').val();

        let filteredData = allRankingData.filter(item => {
            const matchesSearch = searchTerm === '' ||
                (item.nama && item.nama.toLowerCase().includes(searchTerm)) ||
                (item.kode && item.kode.toLowerCase().includes(searchTerm));

            // ✅ FIXED: Gunakan eligibility_status dari API
            const itemEligibility = item.eligibility_status || 'NOT_ELIGIBLE';
            const matchesEligibility = eligibilityFilter === '' ||
                itemEligibility === eligibilityFilter;

            return matchesSearch && matchesEligibility;
        });

        // Limit to 20 untuk initial view
        const displayData = filteredData.slice(0, 20);

        let html = '';
        let ranking = 1;

        displayData.forEach((item) => {
            const totalPoints = parseInt(item.total_points) || 0;
            // ✅ FIXED: Gunakan eligibility_status dari API, bukan dari total points
            const eligibilityStatus = item.eligibility_status || 'NOT_ELIGIBLE';
            let badgeClass = eligibilityStatus === 'ELIGIBLE' ? 'bg-success' : 'bg-secondary';
            let statusText = eligibilityStatus === 'ELIGIBLE' ? 'MEMENUHI SYARAT ✓' : 'BELUM MEMENUHI';

            html += `<tr>
              <td class="text-center">
                <span class="badge bg-primary" style="font-size: 13px; padding: 8px 12px;">
                  <i class="bi bi-trophy"></i> #${ranking}
                </span>
              </td>
              <td><strong>${item.kode || '-'}</strong></td>
              <td><strong>${item.nama || '-'}</strong></td>
              <td class="text-center">
                <span class="badge bg-info text-white" style="font-size: 12px; padding: 6px 10px;">
                  ${totalPoints} Poin
                </span>
              </td>
              <td class="text-center">
                <span class="badge ${badgeClass} text-white" style="font-size: 11px; padding: 6px 8px;">
                  ${statusText}
                </span>
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-info" onclick="showDetailModal(${item.pelanggan_id}, '${item.nama}', '${item.kode}')" title="Lihat detail analisa">
                  <i class="bi bi-eye"></i> Detail
                </button>
              </td>
            </tr>`;
            ranking++;
        });

        if (html === '') {
            html = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-search"></i> Tidak ada data yang sesuai filter</td></tr>';
        }

        $('#table-ranking tbody').html(html);

        const totalAvailable = allRankingData.length;
        const totalFiltered = filteredData.length;
        $('#info-total').text(`Total: ${totalFiltered}/${totalAvailable} (Menampilkan ${displayData.length})`);
    }

    /**
     * ⚠️ DEPRECATED: Function checkEligibility() tidak lagi digunakan
     * Eligibility kini ditentukan oleh backend API menggunakan GAP Analysis (threshold 3.5/5)
     * Lihat: Master.php::get_customer_detail_scoring() & RewardProfileMatching.php
     */

    /**
     * Tampilkan modal detail pelanggan dengan scoring analysis
     */
    function showDetailModal(pelangganId, pelangganNama, pelangganKode) {
        $('#modal-label-detail').text('Detail Pelanggan & Analisa Profile Matching - ' + pelangganNama + ' (' + pelangganKode + ')');
        $('#modal-detail-content').html('<div class="text-center"><i class="bi bi-hourglass-split"></i> Memuat data...</div>');

        const detailModal = new bootstrap.Modal(document.getElementById('modal-detail-pelanggan'));
        detailModal.show();

        loadDetailData(pelangganId);
    }

    /**
     * Load detail data pelanggan dengan scoring
     */
    function loadDetailData(pelangganId) {
        $.ajax({
            url: baseUrl + "?f=get_customer_detail_scoring",
            type: "POST",
            dataType: "json",
            data: {
                pelanggan_id: pelangganId,
                month: new Date().getMonth() + 1,
                year: new Date().getFullYear()
            },
            success: function(resp) {
                console.log('Full Response:', resp);

                if (resp.status === 'success') {
                    renderDetailContent(resp);
                } else {
                    let errorMsg = resp.message || 'Gagal memuat data';
                    console.error('API Error:', errorMsg);
                    $('#modal-detail-content').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> ' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);

                let errorMsg = 'Error: ' + error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }

                // Tampilkan raw response jika ada
                if (xhr.responseText) {
                    try {
                        let parsed = JSON.parse(xhr.responseText);
                        errorMsg = parsed.message || errorMsg;
                    } catch (e) {
                        errorMsg += '<br><small>Raw: ' + xhr.responseText.substring(0, 200) + '</small>';
                    }
                }

                $('#modal-detail-content').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> ' + errorMsg + '</div>');
            }
        });
    }

    /**
     * Render detail content ke modal
     * ⚠️ IMPORTANT: Scores are now on 1-5 scale (GAP Analysis), not 0-100!
     */
    function renderDetailContent(data) {
        const minBelanja = data.min_belanja_point || 0;
        const pointPerBelanja = data.point_per_belanja || 10000;
        const totalBelanja = data.total_belanja || 0;
        const jumlahTransaksi = data.jumlah_transaksi || 0;
        const totalPoin = data.total_poin || 0;

        // ✅ FIXED: Scores are 1-5 scale (GAP Analysis)
        const nominalScore = parseFloat(data.nominal_score) || 0; // 1-5 scale
        const frequencyScore = parseFloat(data.frequency_score) || 0; // 1-5 scale
        const finalScore = parseFloat(data.final_score) || 0; // 1-5 scale
        const eligibilityStatus = data.eligibility_status || 'NOT_ELIGIBLE';
        const periodYear = data.period_year || new Date().getFullYear();

        // ✅ FIXED: Convert 1-5 scale to 0-100 percentage for progress bar display
        const nominalScorePercent = (nominalScore / 5) * 100;
        const frequencyScorePercent = (frequencyScore / 5) * 100;
        const finalScorePercent = (finalScore / 5) * 100;

        // Validasi jika semua data kosong - tapi tetap render dengan info kosong
        const hasTransactionData = totalBelanja > 0 || jumlahTransaksi > 0;

        let htmlDetail = `
        <div class="alert alert-info mb-3">
            <i class="bi bi-calendar3"></i> <strong>Periode Analisa:</strong> Tahun ${periodYear} (01 Januari - 31 Desember)
        </div>

        ${!hasTransactionData ? `
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-circle"></i> <strong>Informasi:</strong> Pelanggan ini belum memiliki transaksi pada periode tahun ${periodYear}. Poin yang ditampilkan berasal dari transaksi tahun sebelumnya atau manual entry.
        </div>
        ` : ''}

        <div class="row">
            <div class="col-md-6">
                <div class="card bg-light mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informasi Pelanggan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><strong>Total Belanja:</strong></label>
                            <p class="text-success fw-bold" style="font-size: 18px;">Rp ${new Intl.NumberFormat('id-ID').format(Math.round(totalBelanja))}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Jumlah Transaksi:</strong></label>
                            <p class="text-info fw-bold" style="font-size: 18px;">${jumlahTransaksi} Kali</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Total Poin:</strong></label>
                            <p class="text-warning fw-bold" style="font-size: 18px;">${totalPoin} Poin</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card bg-light mb-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-sliders"></i> Pengaturan Minimum Pembelanjaan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><strong>Min Belanja untuk Poin:</strong></label>
                            <p class="fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(minBelanja)}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Poin per Rp:</strong></label>
                            <p class="fw-bold">1 Poin / Rp ${new Intl.NumberFormat('id-ID').format(pointPerBelanja)}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Berbelanja Minimum:</strong></label>
                            <p class="fw-bold">${totalBelanja >= minBelanja ? '<span class="badge bg-success">✓ Terpenuhi</span>' : '<span class="badge bg-warning">Belum Terpenuhi</span>'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-gradient mb-3">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Analisa Profile Matching</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3 border-end">
                            <h6 class="text-muted">Score Nominal Belanja</h6>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: ${nominalScorePercent.toFixed(0)}%" aria-valuenow="${nominalScore}" aria-valuemin="1" aria-valuemax="5">
                                    ${nominalScore.toFixed(2)}/5
                                </div>
                            </div>
                            <small class="text-muted">Bobot: 60% (Core Factor)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 border-end">
                            <h6 class="text-muted">Score Frekuensi Belanja</h6>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: ${frequencyScorePercent.toFixed(0)}%" aria-valuenow="${frequencyScore}" aria-valuemin="1" aria-valuemax="5">
                                    ${frequencyScore.toFixed(2)}/5
                                </div>
                            </div>
                            <small class="text-muted">Bobot: 40% (Secondary Factor)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6 class="text-muted">Final Score Profile Matching</h6>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar ${finalScore >= 3.5 ? 'bg-success' : 'bg-secondary'}" role="progressbar" style="width: ${finalScorePercent.toFixed(0)}%" aria-valuenow="${finalScore}" aria-valuemin="1" aria-valuemax="5">
                                    ${finalScore.toFixed(2)}/5
                                </div>
                            </div>
                            <small class="text-muted">Formula: (Nominal×60%) + (Frekuensi×40%)</small>
                        </div>
                    </div>
                </div>

                <div class="alert ${eligibilityStatus === 'ELIGIBLE' ? 'alert-success' : 'alert-warning'} mt-3 mb-0">
                    <div class="d-flex align-items-center">
                        <i class="bi ${eligibilityStatus === 'ELIGIBLE' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'}" style="font-size: 24px; margin-right: 10px;"></i>
                        <div>
                            <h6 class="mb-1">Status Eligibility: <strong>${eligibilityStatus}</strong></h6>
                            <small>
                                ${eligibilityStatus === 'ELIGIBLE' 
                                    ? `Pelanggan memenuhi syarat profile matching (Score: ${finalScore.toFixed(2)}/5 ≥ 3.5). Berhak mendapatkan reward.` 
                                    : `Pelanggan belum memenuhi syarat profile matching (Score: ${finalScore.toFixed(2)}/5 < 3.5). Diperlukan lebih banyak transaksi atau nominal belanja lebih tinggi.`
                                }
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Penjelasan Perhitungan GAP Analysis</h6>
                    </div>
                    <div class="card-body small">
                        <ul>
                            <li><strong>Ideal Nominal Belanja:</strong> Rp 10.000.000 (target belanja tahunan)</li>
                            <li><strong>Ideal Frekuensi Belanja:</strong> 10 transaksi (target frekuensi tahunan)</li>
                            <li><strong>Score Nominal Belanja:</strong> Dihitung berdasarkan GAP antara nominal aktual vs ideal (Bobot 60%)</li>
                            <li><strong>Score Frekuensi Belanja:</strong> Dihitung berdasarkan GAP antara frekuensi aktual vs ideal (Bobot 40%)</li>
                            <li><strong>Final Score:</strong> Kombinasi kedua score dengan rumus: (Nominal Score × 60%) + (Frequency Score × 40%)</li>
                            <li><strong>Skala Score:</strong> 1-5 (semakin tinggi semakin baik, semakin dekat dengan target)</li>
                            <li><strong>Threshold ELIGIBLE:</strong> Pelanggan ELIGIBLE jika final score ≥ 3.5 dari skala 1-5</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        `;

        $('#modal-detail-content').html(htmlDetail);
    }

    /**
     * Reload detail data saat refresh button diklik
     */
    function reloadDetailModal() {
        const modalElem = document.getElementById('modal-detail-pelanggan');
        const modalTitle = document.querySelector('#modal-label-detail');
        const pelangganInfo = modalTitle.textContent.split(' - ');

        if (pelangganInfo.length >= 2) {
            // Extract pelanggan_id dari data yang ada (kita bisa track di dataset juga)
            // Untuk sekarang kita reload dari label
            loadRankingData();
            showDetailModal(1, pelangganInfo[1], ''); // Need to get actual ID
        }
    }

    /**
     * Export ranking data ke Excel
     */
    function exportRankingToExcel() {
        const rows = [];

        rows.push(['Ranking', 'Kode', 'Nama Member', 'Total Point', 'Status']);

        $('#table-ranking tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length >= 5) {
                const ranking = $(cells[0]).find('span.badge').text().trim();
                const kode = $(cells[1]).text().trim();
                const nama = $(cells[2]).text().trim();
                const points = $(cells[3]).find('span.badge').text().trim();
                const status = $(cells[4]).find('span.badge').text().trim();

                rows.push([ranking, kode, nama, points, status]);
            }
        });

        if (rows.length <= 1) {
            Swal.fire('Info', 'Tidak ada data untuk diexport', 'info');
            return;
        }

        let csvContent = rows.map(row =>
            row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')
        ).join('\n');

        const blob = new Blob(['\uFEFF' + csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'ranking_point_' + new Date().getTime() + '.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        Swal.fire('Berhasil', 'Data ranking berhasil didownload', 'success');
    }

    /**
     * Show reset confirmation modal
     */
    function showResetModal() {
        $('#reset-reason').val('');
        const modal = new bootstrap.Modal(document.getElementById('modal-reset-points'));
        modal.show();
    }

    /**
     * Confirm dan execute reset untuk semua points
     */
    function confirmResetAllPoints() {
        const reason = $('#reset-reason').val().trim() || 'Diberikan reward oleh pemilik';

        Swal.fire({
            title: 'Konfirmasi Reset Semua Poin',
            html: `Anda akan mereset semua poin pelanggan yang ELIGIBLE. Tindakan ini tidak dapat dibatalkan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Reset Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Reset Poin...',
                    html: 'Mohon tunggu, sedang mereset semua poin pelanggan...',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                $.ajax({
                    url: baseUrl + "?f=reset_all_customer_points",
                    type: "POST",
                    dataType: "json",
                    data: {
                        reason: reason
                    },
                    success: function(resp) {
                        Swal.hideLoading();
                        Swal.close();

                        if (resp.status === 'success') {
                            Swal.fire(
                                'Berhasil!',
                                `Reset poin berhasil untuk ${resp.reset_count} pelanggan${resp.failed_count > 0 ? ` (Gagal: ${resp.failed_count})` : ''}`,
                                'success'
                            ).then(() => {
                                bootstrap.Modal.getInstance(document.getElementById('modal-reset-points')).hide();
                                loadRankingData();
                            });
                        } else {
                            Swal.fire('Gagal', resp.message || 'Terjadi kesalahan saat mereset poin', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.hideLoading();
                        Swal.close();
                        Swal.fire('Error', 'Gagal menghubungi server: ' + error, 'error');
                        console.error('AJAX Error:', error, xhr);
                    }
                });
            }
        });
    }

    $(function() {
        // Initialize
        initializeData();

        // Event listeners
        $(document).on('click', '#btn-reset-all-points', function() {
            showResetModal();
        });

        $(document).on('click', '#btn-export-excel', function() {
            exportRankingToExcel();
        });

        // Search dan filter event listeners
        $(document).on('keyup', '#search-customer', function() {
            renderRankingTable();
        });

        $(document).on('change', '#filter-eligibility', function() {
            renderRankingTable();
        });

        $(document).on('click', '#btn-clear-filter', function() {
            $('#search-customer').val('');
            $('#filter-eligibility').val('');
            renderRankingTable();
        });

        // Auto reload ranking setiap 30 detik
        setInterval(function() {
            loadRankingData();
        }, 30000);
    });
</script>