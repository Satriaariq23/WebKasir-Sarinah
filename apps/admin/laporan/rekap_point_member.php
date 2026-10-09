<?php

/**
 * ==========================================================
 *  FILE: admin/laporan/rekap_point_member.php
 *  DESCRIPTION: Rekap Point Member
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
                        <a class="nav-link" href="<?= base_url ?>admin/?page=laporan/ranking_point">Ranking Point</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url ?>admin/?page=laporan/rekap_point_member">Rekap Point Member</a>
                    </li>
                </ul>
            </div>
            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="form-label">Cari Member</label>
                    <input type="text" id="filter_member_point" class="form-control form-control-sm" placeholder="Nama atau Kode">
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="form-label">Minimum Poin</label>
                    <input type="number" id="filter_min_member_points" class="form-control form-control-sm" placeholder="0" value="0">
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="form-label">Urutkan</label>
                    <select id="sort_member_points" class="form-control form-control-sm">
                        <option value="points_desc" selected>Poin (Tertinggi)</option>
                        <option value="points_asc">Poin (Terendah)</option>
                        <option value="name_asc">Nama (A-Z)</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 d-flex align-items-end mb-2 gap-2">
                    <button class="btn btn-primary btn-sm flex-grow-1" id="btn-apply-member-filter">
                        <i class="fa fa-filter"></i> Terapkan
                    </button>
                    <button class="btn btn-success btn-sm" id="btn-export-member-excel" title="Export ke Excel">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                </div>
            </div>
            <!-- Summary Info -->
            <div id="member_points_info"></div>
            <!-- Responsive Table -->
            <div class="table-responsive">
                <table id="table_member_points" class="table table-hover table-bordered align-middle">
                    <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 8%;">Kode</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th class="text-center" style="width: 12%;">Poin Saat Ini</th>
                            <th class="text-center" style="width: 12%;">Poin Digunakan</th>
                            <th class="text-center" style="width: 12%;">Total Poin</th>
                            <th class="text-center" style="width: 8%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div id="pagination-info" class="text-muted">
                    <small>Menampilkan data 1-10 dari total</small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-2" id="btn-prev-page" disabled>
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                    <span id="pagination-page" class="badge bg-primary mx-2">1</span>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-next-page">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tukar Point -->
<div class="modal fade" id="modal-redeem-reward" tabindex="-1" role="dialog" aria-labelledby="modal-label-redeem" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modal-label-redeem">
                    <i class="bi bi-gift"></i> Tukar Poin dengan Reward
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Hidden Member ID -->
                <input type="hidden" id="redeem-member-id">

                <!-- Member Info -->
                <div class="alert alert-info mb-3">
                    <strong>Member:</strong> <span id="redeem-member-name">-</span> <br>
                    <strong>Poin Tersedia:</strong> <span id="redeem-member-points" class="badge bg-success">0</span>
                </div>

                <!-- Reward Selection -->
                <div class="mb-3">
                    <label class="form-label">Pilih Reward</label>
                    <select id="redeem-reward-select" class="form-select form-select-sm">
                        <option value="">- Pilih Reward -</option>
                    </select>
                </div>

                <!-- Reward Details -->
                <div id="redeem-reward-details" class="alert alert-secondary" style="display: none;">
                    <p><strong>Deskripsi:</strong> <span id="reward-detail-desc"></span></p>
                    <p><strong>Poin Dibutuhkan:</strong> <span id="reward-detail-poin"></span></p>
                    <p><strong>Stok Tersedia:</strong> <span id="reward-detail-stok"></span></p>
                </div>

                <!-- Alert Messages -->
                <div id="redeem-alert" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-confirm-redeem">
                    <i class="bi bi-check-circle"></i> Tukar Poin
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variables untuk pagination
    let allMemberPointsData = [];
    let currentPage = 1;
    const itemsPerPage = 10;

    /**
     * Load member points data - SEMUA MEMBER
     */
    function loadMemberPointsData() {
        const filterMember = $('#filter_member_point').val().trim();
        const filterMinPoints = $('#filter_min_member_points').val() || 0;
        const sortBy = $('#sort_member_points').val() || 'points_desc';

        $.ajax({
            url: "<?= base_url ?>classes/Laporan.php?f=get_rekap_point_member",
            type: "POST",
            data: {
                search: filterMember,
                min_points: filterMinPoints,
                sort_by: sortBy
            },
            dataType: "json",
            beforeSend: function() {
                $('#table_member_points tbody').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Loading...</td></tr>');
            },
            success: function(resp) {
                if (resp.status === 'success' && resp.data && resp.data.length > 0) {
                    allMemberPointsData = resp.data;
                    currentPage = 1;
                    displayMemberPointsPage();
                } else {
                    allMemberPointsData = [];
                    currentPage = 1;
                    $('#table_member_points tbody').html('<tr><td colspan="8" class="text-center text-danger py-4"><i class="bi bi-inbox"></i> Tidak ada data member poin</td></tr>');
                    updatePaginationControls();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading member points:', error);
                allMemberPointsData = [];
                currentPage = 1;
                $('#table_member_points tbody').html('<tr><td colspan="8" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle"></i> Gagal memuat data</td></tr>');
                updatePaginationControls();
            }
        });
    }

    /**
     * Display member points for current page
     */
    function displayMemberPointsPage() {
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageData = allMemberPointsData.slice(startIndex, endIndex);

        let html = '';
        pageData.forEach((item, index) => {
            const totalPoints = parseInt(item.poin_total) || 0;
            const pointsUsed = parseInt(item.poin_digunakan) || 0;
            const pointsCurrent = totalPoints - pointsUsed;
            const rowNumber = startIndex + index + 1;

            html += `<tr>
              <td class="text-center"><strong>${rowNumber}</strong></td>
              <td><strong>${item.kode || '-'}</strong></td>
              <td><strong>${item.nama || '-'}</strong></td>
              <td>${item.alamat || '-'}</td>
              <td class="text-center">
                <span class="badge bg-info text-white">${pointsCurrent}</span>
              </td>
              <td class="text-center">
                <span class="badge bg-warning text-dark">${pointsUsed}</span>
              </td>
              <td class="text-center">
                <span class="badge bg-success text-white">${totalPoints}</span>
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-info btn-view-history me-2" data-member-id="${item.pelanggan_id}" data-member-name="${item.nama}" title="Lihat Riwayat Poin">
                  <i class="bi bi-clock-history"></i>
                </button>
                <button class="btn btn-sm btn-success btn-redeem-reward" data-member-id="${item.pelanggan_id}" data-member-name="${item.nama}" data-points="${pointsCurrent}" title="Tukar Poin dengan Reward">
                  <i class="bi bi-gift"></i> Tukar Point
                </button>
              </td>
            </tr>`;
        });

        $('#table_member_points tbody').html(html);
        updatePaginationControls();
    }

    /**
     * Update pagination controls
     */
    function updatePaginationControls() {
        const totalItems = allMemberPointsData.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const startIndex = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(currentPage * itemsPerPage, totalItems);

        // Update info text
        $('#pagination-info').html(`<small>Menampilkan data ${startIndex}-${endIndex} dari total ${totalItems}</small>`);

        // Update page number
        $('#pagination-page').text(totalPages === 0 ? 0 : currentPage);

        // Update button disabled state
        $('#btn-prev-page').prop('disabled', currentPage <= 1);
        $('#btn-next-page').prop('disabled', totalPages === 0 || currentPage >= totalPages);
    }



    /**
     * Show member point history modal
     */
    function showMemberPointHistory(memberId, memberName) {
        $.ajax({
            url: "<?= base_url ?>classes/Laporan.php?f=get_member_point_history",
            type: "POST",
            data: {
                member_id: memberId
            },
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success' && resp.data && resp.data.length > 0) {
                    let html = '<table class="table table-sm table-hover">';
                    html += '<thead class="bg-light"><tr><th>Tanggal</th><th>Transaksi ID</th><th>Poin Diperoleh</th></tr></thead><tbody>';

                    resp.data.forEach((item) => {
                        html += `<tr>
              <td>${item.created_at || '-'}</td>
              <td><small>${item.transaksi_id || '-'}</small></td>
              <td><strong>${item.poin}</strong></td>
            </tr>`;
                    });

                    html += '</tbody></table>';

                    Swal.fire({
                        title: 'Riwayat Poin: ' + memberName,
                        html: html,
                        width: '600px',
                        confirmButtonText: 'Tutup'
                    });
                } else {
                    Swal.fire('Info', 'Tidak ada riwayat poin untuk member ini', 'info');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('Error', 'Gagal memuat riwayat poin: ' + error, 'error');
            }
        });
    }

    /**
     * Export member points data ke Excel
     */
    function exportMemberPointsToExcel() {
        const rows = [];
        rows.push(['No', 'Kode', 'Nama Member', 'Alamat', 'Poin Saat Ini', 'Poin Digunakan', 'Total Poin']);

        $('#table_member_points tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length > 1) {
                rows.push([
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim(),
                    $(cells[3]).text().trim(),
                    $(cells[4]).find('span').text().trim(),
                    $(cells[5]).find('span').text().trim(),
                    $(cells[6]).find('span').text().trim()
                ]);
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
        link.setAttribute('download', 'rekap_point_member_' + new Date().getTime() + '.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        Swal.fire('Berhasil', 'Data member poin berhasil didownload', 'success');
    }

    /**
     * Open redeem reward modal
     */
    function openRedeemModal(memberId, memberName, points) {
        $('#redeem-member-id').val(memberId);
        $('#redeem-member-name').text(memberName);
        $('#redeem-member-points').text(points);

        // Load rewards
        loadAvailableRewards(points);

        const modal = new bootstrap.Modal(document.getElementById('modal-redeem-reward'));
        modal.show();
    }

    /**
     * Load available rewards
     */
    function loadAvailableRewards(memberPoints) {
        console.log('Loading available rewards for member points:', memberPoints);
        
        $.ajax({
            url: "<?= base_url ?>classes/RewardSystem.php?f=get_reward_catalog",
            type: "GET",
            dataType: "json",
            success: function(resp) {
                console.log('Response from get_reward_catalog:', resp);
                
                if (resp.status === 'success' && resp.data) {
                    console.log('Number of rewards loaded:', resp.data.length);
                    
                    let options = '<option value="">- Pilih Reward -</option>';

                    resp.data.forEach(reward => {
                        console.log('Processing reward:', reward);
                        
                        const pointsRequired = parseInt(reward.points_required) || 0;
                        const stock = parseInt(reward.stock) || 0;
                        const canRedeem = memberPoints >= pointsRequired && (stock > 0 || stock === -1);
                        const disabled = !canRedeem ? 'disabled' : '';
                        const status = !canRedeem ? ' (Tidak bisa ditukar)' : '';

                        options += `<option value="${reward.id}" data-poin="${pointsRequired}" data-stok="${stock}" data-desc="${reward.description}" ${disabled}>
              ${reward.reward_name} - ${pointsRequired} poin ${status}
            </option>`;
                    });

                    $('#redeem-reward-select').html(options);
                    console.log('Dropdown populated with ' + (resp.data.length) + ' rewards');
                } else {
                    console.warn('No rewards available or error in response:', resp);
                    showRedeemAlert('danger', 'Tidak ada reward tersedia');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load rewards:', error, xhr);
                console.error('Response text:', xhr.responseText);
                showRedeemAlert('danger', 'Gagal memuat reward: ' + error);
            }
        });
    }

    /**
     * Show reward details
     */
    function showRewardDetails(selectedOption) {
        const poin = selectedOption.data('poin');
        const stok = selectedOption.data('stok');
        const desc = selectedOption.data('desc');

        $('#reward-detail-poin').text(poin);
        $('#reward-detail-stok').text(stok);
        $('#reward-detail-desc').text(desc || '-');

        $('#redeem-reward-details').show();
    }

    /**
     * Confirm redeem reward
     */
    function confirmRedeemReward() {
        const memberId = document.getElementById('redeem-member-id').value;
        const rewardId = $('#redeem-reward-select').val();
        const poinDigunakan = parseInt($('#redeem-reward-select').find('option:selected').data('poin'));

        if (!memberId || !rewardId || !poinDigunakan) {
            showRedeemAlert('warning', 'Data tidak lengkap');
            return;
        }

        $.ajax({
            url: "<?= base_url ?>classes/RewardSystem.php?f=redeem_reward",
            type: "POST",
            data: {
                pelanggan_id: memberId,
                reward_id: rewardId,
                csrf_token: window._csrf_token_ || ''
            },
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success') {
                    showRedeemAlert('success', 'Reward berhasil ditukar!');

                    setTimeout(function() {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modal-redeem-reward'));
                        modal.hide();
                        loadMemberPointsData(); // Reload tabel
                    }, 1500);
                } else {
                    showRedeemAlert('danger', resp.msg || resp.message || 'Gagal menukar reward');
                }
            },
            error: function(xhr, status, error) {
                console.error('Redeem error:', error, xhr);
                let message = 'Gagal menukar reward: ' + error;
                if (xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) {
                    message = xhr.responseJSON.msg || xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    message = xhr.responseText;
                }
                showRedeemAlert('danger', message);
            }
        });
    }

    /**
     * Show alert dalam modal redeem
     */
    function showRedeemAlert(type, message) {
        const alertDiv = $('#redeem-alert');
        alertDiv.removeClass('alert-success alert-warning alert-danger alert-info');
        alertDiv.addClass('alert-' + type);
        alertDiv.html(message);
        alertDiv.show();
    }

    $(function() {
        loadMemberPointsData();

        // Event listeners untuk member points
        $(document).on('click', '#btn-apply-member-filter', function() {
            currentPage = 1; // Reset ke page 1 saat apply filter
            loadMemberPointsData();
        });

        $(document).on('click', '#btn-export-member-excel', function() {
            exportMemberPointsToExcel();
        });

        $(document).on('click', '.btn-view-history', function() {
            const memberId = $(this).data('member-id');
            const memberName = $(this).data('member-name');
            showMemberPointHistory(memberId, memberName);
        });

        // Event listener untuk tombol tukar point
        $(document).on('click', '.btn-redeem-reward', function() {
            const memberId = $(this).data('member-id');
            const memberName = $(this).data('member-name');
            const points = $(this).data('points');

            openRedeemModal(memberId, memberName, points);
        });

        // Event listener untuk dropdown reward
        $(document).on('change', '#redeem-reward-select', function() {
            const selectedOption = $(this).find('option:selected');
            if (selectedOption.val()) {
                showRewardDetails(selectedOption);
            } else {
                $('#redeem-reward-details').hide();
            }
        });

        // Event listener untuk button confirm redeem
        $(document).on('click', '#btn-confirm-redeem', function() {
            const rewardId = $('#redeem-reward-select').val();
            if (!rewardId) {
                showRedeemAlert('warning', 'Pilih reward terlebih dahulu');
                return;
            }
            confirmRedeemReward();
        });

        // Event listener untuk pagination buttons
        $(document).on('click', '#btn-prev-page', function() {
            if (currentPage > 1) {
                currentPage--;
                displayMemberPointsPage();
                $('html, body').animate({
                    scrollTop: $('#table_member_points').offset().top - 100
                }, 300);
            }
        });

        $(document).on('click', '#btn-next-page', function() {
            const totalPages = Math.ceil(allMemberPointsData.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                displayMemberPointsPage();
                $('html, body').animate({
                    scrollTop: $('#table_member_points').offset().top - 100
                }, 300);
            }
        });
    });
</script>
