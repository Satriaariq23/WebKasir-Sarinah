<?php
/**
 * ============================================================
 * MODAL DETAIL PELANGGAN & ANALISIS PROFILE MATCHING
 * ============================================================
 * File ini berisi modal yang menampilkan detail pelanggan
 * dengan informasi transaksi, poin, dan score matching
 */
?>

<!-- Modal Detail Pelanggan & Analisis Profile Matching -->
<div class="modal fade" id="modal-customer-detail" tabindex="-1" role="dialog" aria-labelledby="modal-label-detail" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modal-label-detail">
                    <i class="bi bi-person-check"></i> Detail Pelanggan & Analisis Profile Matching
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <!-- Periode Analisa -->
                <div class="alert alert-light-info rounded border-info border-2 mb-3">
                    <div class="fw-bold text-info">
                        <i class="bi bi-calendar-event"></i>
                        Periode Analisa: <span id="detail-periode">Tahun 2021 (01 Januari - 31 Desember)</span>
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="alert alert-light-warning rounded border-warning border-2 mb-3" id="detail-info-alert">
                    <i class="bi bi-info-circle"></i>
                    <small id="detail-info-text">Pelanggan ini belum memiliki transaksi pada periode tahun 2021. Poin yang ditampilkan berasal dari transaksi tahun sebelumnya atau manual entry.</small>
                </div>

                <!-- Main Content Row -->
                <div class="row g-3 mb-4">
                    <!-- Informasi Pelanggan -->
                    <div class="col-md-6">
                        <div class="card info-card h-100">
                            <div class="card-header-custom bg-info">
                                <i class="bi bi-person-circle"></i>
                                <span class="ms-2">Informasi Pelanggan</span>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Kode Pelanggan</div>
                                    <div class="info-value fw-bold" id="detail-kode">-</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Nama Pelanggan</div>
                                    <div class="info-value fw-bold" id="detail-nama">-</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Alamat</div>
                                    <div class="info-value" id="detail-alamat">-</div>
                                </div>
                                <div class="row mt-3 pt-2 border-top">
                                    <div class="col-6">
                                        <div class="info-label small">Kota</div>
                                        <div class="info-value" id="detail-kota">-</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label small">Telepon</div>
                                        <div class="info-value" id="detail-telepon">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pengaruh Minimum Pembelanjaan -->
                    <div class="col-md-6">
                        <div class="card info-card h-100">
                            <div class="card-header-custom bg-purple">
                                <i class="bi bi-sliders"></i>
                                <span class="ms-2">Pengaruh Minimum Pembelanjaan</span>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Min Belanja untuk Poin</div>
                                    <div class="info-value fw-bold" id="detail-min-belanja">Rp 50.000</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Poin per Rp</div>
                                    <div class="info-value fw-bold" id="detail-poin-per-rp">1 Poin / Rp 10.000</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Berbelanja Minimum</div>
                                    <div class="info-value">
                                        <span class="badge badge-status" id="detail-belanja-status">Belum Terpenuhi</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Info Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card transaction-card border-light-info h-100">
                            <div class="card-body">
                                <div class="trans-label">Total Belanja</div>
                                <div class="trans-value text-info" id="detail-total-belanja">Rp 0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card transaction-card border-light-primary h-100">
                            <div class="card-body">
                                <div class="trans-label">Jumlah Transaksi</div>
                                <div class="trans-value text-primary" id="detail-jumlah-transaksi">0 Kali</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card transaction-card border-light-warning h-100">
                            <div class="card-body">
                                <div class="trans-label">Total Poin</div>
                                <div class="trans-value text-warning" id="detail-total-poin">0 Poin</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analisa Profile Matching -->
                <div class="card info-card">
                    <div class="card-header-custom bg-purple">
                        <i class="bi bi-graph-up"></i>
                        <span class="ms-2">Analisa Profile Matching</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <!-- Score Nominal Belanja -->
                            <div class="col-md-4">
                                <div class="score-box">
                                    <div class="score-label">Score Nominal Belanja</div>
                                    <div class="score-info">Bobot: 60% (Core Factor)</div>
                                    <div class="progress score-progress">
                                        <div class="progress-bar bg-info fw-bold" id="detail-score-nominal-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                    </div>
                                    <div class="score-value">
                                        <span class="fw-bold" id="detail-score-nominal">0.00</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Score Frekuensi Belanja -->
                            <div class="col-md-4">
                                <div class="score-box">
                                    <div class="score-label">Score Frekuensi Belanja</div>
                                    <div class="score-info">Bobot: 40% (Secondary Factor)</div>
                                    <div class="progress score-progress">
                                        <div class="progress-bar bg-warning fw-bold text-dark" id="detail-score-frekuensi-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                    </div>
                                    <div class="score-value">
                                        <span class="fw-bold" id="detail-score-frekuensi">0.00</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Score Profile Matching -->
                            <div class="col-md-4">
                                <div class="score-box">
                                    <div class="score-label">Final Score Profile Matching</div>
                                    <div class="score-info">Formula: (Nominal×60%) + (Frekuensi×40%)</div>
                                    <div class="progress score-progress">
                                        <div class="progress-bar bg-success fw-bold" id="detail-final-score-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                    </div>
                                    <div class="score-value">
                                        <span class="fw-bold" id="detail-final-score">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Eligibility Status -->
                        <div class="alert alert-light-warning rounded py-3 mb-0" role="alert">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi bi-info-circle-fill" style="font-size: 22px; color: #ff9800; flex-shrink: 0; margin-top: 2px;"></i>
                                <div style="flex: 1;">
                                    <div class="fw-bold text-dark mb-2" style="font-size: 13px;">
                                        Status Eligibility: <span id="detail-eligibility-status" class="badge bg-warning text-dark ms-2">Belum Terpenuhi</span>
                                    </div>
                                    <small id="detail-eligibility-message" class="text-dark d-block" style="font-size: 12px; line-height: 1.5;">
                                        Pelanggan perlu memenuhi minimum belanja Rp 50.000 untuk dapat mengumpulkan poin
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btn-refresh-detail" onclick="refreshCustomerDetail()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Tambahan untuk Modal -->
<style>
    .border-purple {
        border-color: #9366cc !important;
    }

    .bg-purple {
        background-color: #9366cc !important;
    }

    .border-light-info {
        border: 2px solid rgba(23, 162, 184, 0.2) !important;
    }

    .border-light-primary {
        border: 2px solid rgba(0, 123, 255, 0.2) !important;
    }

    .border-light-warning {
        border: 2px solid rgba(255, 193, 7, 0.2) !important;
    }

    /* Modal improvement */
    #modal-customer-detail .card {
        box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
    }

    #modal-customer-detail .card-header {
        padding: 1.25rem 1.5rem !important;
    }

    #modal-customer-detail .card-body {
        padding: 1.5rem !important;
    }

    /* Score boxes styling */

</style>

<!-- JavaScript untuk Modal -->
<script>
    let currentCustomerId = null;

    /**
     * Tampilkan modal dengan data pelanggan terbaru
     */
    function showCustomerDetail(customerId, periode = null) {
        currentCustomerId = customerId;

        // Tentukan periode (default tahun 2021)
        if (!periode) {
            periode = new Date().getFullYear();
        }

        // Load data dari server
        $.ajax({
            url: "<?php echo base_url; ?>classes/Master.php?f=get_customer_detail_matching",
            type: "POST",
            data: {
                customer_id: customerId,
                periode: periode
            },
            dataType: "json",
            beforeSend: function() {
                // Tampilkan loading state
                $('#modal-customer-detail').find('.modal-body').css('opacity', '0.5');
            },
            success: function(resp) {
                if (resp.status === 'success' && resp.data) {
                    updateModalContent(resp.data, periode);
                    const modal = new bootstrap.Modal(document.getElementById('modal-customer-detail'));
                    modal.show();
                } else {
                    Swal.fire('Gagal', 'Data pelanggan tidak ditemukan', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error, xhr);
                Swal.fire('Error', 'Gagal memuat data pelanggan: ' + error, 'error');
            },
            complete: function() {
                $('#modal-customer-detail').find('.modal-body').css('opacity', '1');
            }
        });
    }

    /**
     * Update konten modal dengan data terbaru
     */
    function updateModalContent(data, periode) {
        // Update periode
        const startDate = `01 Januari - 31 Desember`;
        $('#detail-periode').text(`Tahun ${periode} (${startDate})`);

        // Update informasi pelanggan
        $('#detail-kode').text(data.customer.kode || '-');
        $('#detail-nama').text(data.customer.nama || '-');
        $('#detail-alamat').text(data.customer.alamat || '-');
        $('#detail-kota').text(data.customer.kota || '-');
        $('#detail-telepon').text(data.customer.telpon || '-');

        // Update info alert
        const hasTransaction = data.transaction_count > 0;
        if (hasTransaction) {
            $('#detail-info-alert').removeClass('alert-light-warning').addClass('alert-light-info');
            $('#detail-info-text').html(
                '<strong>✓ Pelanggan memiliki transaksi</strong> pada periode tahun ' + periode + '. Data poin telah terakumulasi dari transaksi belanja.'
            );
        } else {
            $('#detail-info-alert').removeClass('alert-light-info').addClass('alert-light-warning');
            $('#detail-info-text').text(
                'Pelanggan ini belum memiliki transaksi pada periode tahun ' + periode + '. Poin yang ditampilkan berasal dari transaksi tahun sebelumnya atau manual entry.'
            );
        }

        // Update informasi pengaruh minimum
        $('#detail-min-belanja').text('Rp ' + formatNumber(data.reward_settings?.min_purchase || 50000));
        const pointPerRp = data.reward_settings?.point_per_rp || 10000;
        $('#detail-poin-per-rp').text('1 Poin / Rp ' + formatNumber(pointPerRp));

        // Update status belanja minimum
        const minBelanja = data.total_belanja >= (data.reward_settings?.min_purchase || 50000);
        $('#detail-belanja-status').text(minBelanja ? 'Terpenuhi' : 'Belum Terpenuhi')
            .removeClass('bg-warning bg-success')
            .addClass(minBelanja ? 'bg-success' : 'bg-warning')
            .toggleClass('text-dark', !minBelanja)
            .toggleClass('text-white', minBelanja);

        // Update transaction info
        $('#detail-total-belanja').text('Rp ' + formatNumber(data.total_belanja));
        $('#detail-jumlah-transaksi').text(data.transaction_count + ' Kali');
        $('#detail-total-poin').text(data.total_points + ' Poin');

        // Update scores
        updateScoreBars(data.matching_score);

        // Update eligibility status
        const eligible = data.matching_score.final_score >= (data.reward_settings?.min_score || 65);
        const statusBadge = $('#detail-eligibility-status')
            .text(eligible ? 'MEMENUHI SYARAT' : 'BELUM MEMENUHI')
            .removeClass('bg-warning bg-success')
            .addClass(eligible ? 'bg-success' : 'bg-warning')
            .removeClass('text-white')
            .addClass(eligible ? 'text-white' : 'text-dark');

        let message = 'Pelanggan ';
        if (!minBelanja) {
            message += 'perlu memenuhi minimum belanja Rp ' + formatNumber(data.reward_settings?.min_purchase || 50000) + ' ';
        }
        if (!eligible && minBelanja) {
            message += 'memiliki score matching ' + data.matching_score.final_score.toFixed(2) + ' (minimal ' + (data.reward_settings?.min_score || 65) + ')';
        } else if (eligible) {
            message += 'telah memenuhi semua syarat untuk mendapatkan reward';
        }
        $('#detail-eligibility-message').text(message);
    }

    /**
     * Update score bars dengan animasi
     */
    function updateScoreBars(matchingScore) {
        const nominalScore = matchingScore.nominal_score || 0;
        const frekuensiScore = matchingScore.frequency_score || 0;
        const finalScore = matchingScore.final_score || 0;

        // Update Nominal Score
        const nominalPercent = Math.min(nominalScore, 100);
        const nominalDisplay = nominalPercent >= 10 ? nominalPercent.toFixed(1) + '%' : nominalPercent.toFixed(1) + '%';
        $('#detail-score-nominal-bar')
            .css('width', nominalPercent + '%')
            .html(nominalDisplay);
        $('#detail-score-nominal').text(nominalScore.toFixed(2));

        // Update Frekuensi Score
        const frekuensiPercent = Math.min(frekuensiScore, 100);
        const frekuensiDisplay = frekuensiPercent >= 10 ? frekuensiPercent.toFixed(1) + '%' : frekuensiPercent.toFixed(1) + '%';
        $('#detail-score-frekuensi-bar')
            .css('width', frekuensiPercent + '%')
            .html(frekuensiDisplay);
        $('#detail-score-frekuensi').text(frekuensiScore.toFixed(2));

        // Update Final Score
        const finalPercent = Math.min(finalScore, 100);
        const finalDisplay = finalPercent >= 10 ? finalPercent.toFixed(1) + '%' : finalPercent.toFixed(1) + '%';
        $('#detail-final-score-bar')
            .css('width', finalPercent + '%')
            .html(finalDisplay);
        $('#detail-final-score').text(finalScore.toFixed(2));
    }

    /**
     * Refresh data pelanggan terbaru
     */
    function refreshCustomerDetail() {
        if (currentCustomerId) {
            showCustomerDetail(currentCustomerId);
        }
    }

    /**
     * Format number dengan separator ribu
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
</script>
