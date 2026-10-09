<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Ranking Point Pelanggan</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Daftar ranking pelanggan berdasarkan point yang dikumpulkan dari transaksi belanja</span>
                    </h3>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-success me-2" id="btn-export-excel" title="Download ranking sebagai file Excel">
                            <i class="bi bi-file-earmark-excel"></i> Download Excel
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-reset-all-points" title="Reset semua poin pelanggan (Admin only)">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Semua Poin
                        </button>
                    </div>
                </div>
                <div class="separator"></div>
                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <div class="d-flex overflow-auto h-40px">
                            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bolder">
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6" href="<?php echo base_url ?>admin/?page=pelanggan">Pelanggan</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6" href="<?php echo base_url ?>admin/?page=pelanggan/member">Member</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 active" href="<?php echo base_url ?>admin/?page=pelanggan/ranking-point">Ranking Point</a>
                                </li>
                            </ul>
                        </div>
                        <div class="table-responsive">
                            <table id="table-ranking" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">Ranking</th>
                                        <th style="width: 8%;">Kode</th>
                                        <th>Nama Member</th>
                                        <th>Alamat</th>
                                        <th>Kota</th>
                                        <th>Telepon</th>
                                        <th class="text-center" style="width: 12%;">Total Point</th>
                                        <th class="text-center" style="width: 15%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 fw-semibold"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
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

    $(document).ready(function() {
        loadRankingData();

        // Event listener untuk reset button
        $('#btn-reset-all-points').on('click', function() {
            showResetModal();
        });
        
        // Event listener untuk export excel button
        $('#btn-export-excel').on('click', function() {
            exportRankingToExcel();
        });
    });

    /**
     * Load ranking point data
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
                    let html = '';
                    let ranking = 1;

                    resp.ranking.forEach((item) => {
                        const totalPoints = parseInt(item.total_points) || 0;
                        const eligibilityStatus = item.eligibility_status || 'NOT_ELIGIBLE';
                        let badgeClass = 'bg-secondary';
                        let statusText = 'BELUM MEMENUHI';

                        // DEBUG - log setiap item untuk verifikasi
                        console.log('Item:', item);
                        console.log('Eligibility Status:', eligibilityStatus);

                        // Check eligibility status dari score matching (bukan total points)
                        if (eligibilityStatus === 'ELIGIBLE') {
                            badgeClass = 'bg-success';
                            statusText = 'MEMENUHI SYARAT';
                        }

                        console.log('Final Status Text:', statusText);

                        html += `<tr>
                            <td class="text-center">
                                <span class="badge bg-primary" style="font-size: 13px; padding: 8px 12px;">
                                    <i class="bi bi-trophy"></i> #${ranking}
                                </span>
                            </td>
                            <td><strong>${item.kode || '-'}</strong></td>
                            <td>
                                <a href="javascript:void(0)" class="text-decoration-none fw-bold text-primary" onclick="showCustomerDetail(${item.id})">
                                    ${item.nama || '-'}
                                </a>
                            </td>
                            <td>${item.alamat || '-'}</td>
                            <td>${item.kota || '-'}</td>
                            <td>${item.nohp1 || item.telpon || '-'}</td>
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
                        </tr>`;
                        ranking++;
                    });

                    $('#table-ranking tbody').html(html);
                } else {
                    $('#table-ranking tbody').html('<tr><td colspan="8" class="text-center text-danger py-5"><i class="bi bi-inbox"></i> Belum ada data ranking point. Transaksi belanja pertama kali diperlukan untuk mengumpulkan poin.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error, xhr);
                $('#table-ranking tbody').html('<tr><td colspan="8" class="text-center text-danger py-5">❌ Gagal memuat data ranking. Error: ' + error + '</td></tr>');
            }
        });
    }

    // Reload setiap 30 detik untuk update ranking real-time
    setInterval(loadRankingData, 30000);

    /**
     * Export ranking data ke Excel
     */
    function exportRankingToExcel() {
        // Get data dari table
        const rows = [];
        
        // Header
        rows.push(['Ranking', 'Kode', 'Nama Member', 'Alamat', 'Kota', 'Telepon', 'Total Point', 'Status']);
        
        // Data
        $('#table-ranking tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length > 1 && $(cells[0]).find('span.badge').length > 0) {
                const ranking = $(cells[0]).find('span.badge').text().trim();
                const kode = $(cells[1]).text().trim();
                const nama = $(cells[2]).text().trim();
                const alamat = $(cells[3]).text().trim();
                const kota = $(cells[4]).text().trim();
                const telepon = $(cells[5]).text().trim();
                const points = $(cells[6]).find('span.badge').text().trim();
                const status = $(cells[7]).find('span.badge').text().trim();
                
                rows.push([ranking, kode, nama, alamat, kota, telepon, points, status]);
            }
        });
        
        if (rows.length <= 1) {
            Swal.fire('Info', 'Tidak ada data untuk diexport', 'info');
            return;
        }
        
        // Create CSV content
        let csvContent = rows.map(row => 
            row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')
        ).join('\n');
        
        // Create blob dan download
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
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
        // Clear previous reason
        $('#reset-reason').val('');

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('modal-reset-points'));
        modal.show();
    }

    /**
     * Confirm dan execute reset untuk semua points
     */
    function confirmResetAllPoints() {
        const reason = $('#reset-reason').val().trim() || 'Diberikan reward oleh pemilik';

        // Konfirmasi terakhir
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
                // Show loading state
                Swal.fire({
                    title: 'Memproses Reset Poin...',
                    html: 'Mohon tunggu, sedang mereset semua poin pelanggan...',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                // Execute reset semua pelanggan
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
                                // Close modal
                                bootstrap.Modal.getInstance(document.getElementById('modal-reset-points')).hide();
                                // Reload ranking data
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
</script>

<!-- Include Modal Detail Pelanggan -->
<?php include('modal_customer_detail.php'); ?>