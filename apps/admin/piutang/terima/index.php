<?php
/**
 * Komponen penerimaan dan riwayat piutang pelanggan.
 * Dipakai oleh apps/admin/piutang/index.php.
 */
if (($_GET['page'] ?? '') === 'piutang/terima') {
    $redirectUrl = base_url . 'admin/?page=piutang';
    if (!empty($_GET['piutang_id'])) {
        $redirectUrl .= '&piutang_id=' . rawurlencode((string)$_GET['piutang_id']);
    }
    echo '<script>window.location.replace(' . json_encode($redirectUrl) . ');</script>';
    return;
}
?>

<style>
    #modal_piutang_settlement .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 16px;
        box-shadow: 0 22px 60px rgba(24, 28, 50, .22);
    }

    #modal_piutang_settlement .modal-header {
        border: 0;
        background: #181c32;
        color: #fff;
    }

    #modal_piutang_settlement .modal-title,
    #modal_piutang_settlement .modal-subtitle {
        color: #fff !important;
    }

    #modal_piutang_settlement .modal-subtitle {
        margin-top: 3px;
        color: rgba(255, 255, 255, .7) !important;
        font-size: .78rem;
    }

    #modal_piutang_settlement .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .piutang-settlement-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 18px;
    }

    .piutang-settlement-stat {
        min-width: 0;
        padding: 14px;
        border: 1px solid #e4e6ef;
        border-radius: 12px;
        background: #f8fafc;
    }

    .piutang-settlement-stat small {
        display: block;
        margin-bottom: 5px;
        color: #7e8299;
        font-weight: 600;
    }

    .piutang-settlement-stat strong {
        display: block;
        overflow: hidden;
        color: #181c32;
        font-size: 1rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .piutang-settlement-form {
        margin-bottom: 18px;
        padding: 16px;
        border: 1px solid #d8ebfb;
        border-radius: 12px;
        background: #f3f9fe;
    }

    .piutang-settlement-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 13px;
        color: #181c32;
        font-size: .95rem;
        font-weight: 700;
    }

    .piutang-settlement-history {
        overflow: hidden;
        border: 1px solid #e4e6ef;
        border-radius: 12px;
    }

    .piutang-settlement-history .table {
        min-width: 760px;
        margin: 0;
    }

    .piutang-settlement-history thead th {
        border: 0;
        background: #181c32;
        color: #fff;
        font-size: .76rem;
        white-space: nowrap;
    }

    .piutang-settlement-history tbody td {
        color: #3f4254;
        font-size: .8rem;
        vertical-align: middle;
    }

    @media (max-width: 767.98px) {
        .piutang-settlement-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #modal_piutang_settlement .modal-body { padding: 14px; }
        .piutang-settlement-form { padding: 13px; }
    }
</style>

<div class="modal fade" id="modal_piutang_settlement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header px-4 py-3">
                <div>
                    <h5 class="modal-title">Penerimaan Piutang Pelanggan</h5>
                    <div class="modal-subtitle" id="piutang_settlement_identity">Memuat data piutang...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div id="piutang_settlement_msg"></div>

                <div class="piutang-settlement-summary">
                    <div class="piutang-settlement-stat">
                        <small>Total Piutang</small>
                        <strong id="piutang_settlement_total">Rp 0</strong>
                    </div>
                    <div class="piutang-settlement-stat">
                        <small>Sudah Diterima</small>
                        <strong id="piutang_settlement_received">Rp 0</strong>
                    </div>
                    <div class="piutang-settlement-stat">
                        <small>Sisa Piutang</small>
                        <strong id="piutang_settlement_remaining">Rp 0</strong>
                    </div>
                    <div class="piutang-settlement-stat">
                        <small>Status</small>
                        <strong id="piutang_settlement_status">-</strong>
                    </div>
                </div>

                <div class="alert alert-success d-none" id="piutang_settlement_paid_notice">
                    <i class="bi bi-check-circle me-2"></i>Piutang sudah lunas. Form penerimaan ditutup, sedangkan riwayat tetap dapat dilihat.
                </div>

                <section class="piutang-settlement-form" id="piutang_settlement_form_section">
                    <h6 class="piutang-settlement-section-title"><i class="bi bi-cash-coin"></i> Input Penerimaan</h6>
                    <form id="form_piutang_settlement">
                        <input type="hidden" name="piutang_id" id="piutang_settlement_id">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tanggal Terima</label>
                                <input type="date" name="tanggal_terima" id="piutang_settlement_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Jumlah Terima</label>
                                <div class="input-group">
                                    <input type="text" id="piutang_settlement_amount" class="form-control text-end" inputmode="numeric" autocomplete="off" placeholder="0" required>
                                    <input type="hidden" name="jumlah_terima" id="piutang_settlement_amount_raw" value="">
                                    <button type="button" class="btn btn-light-primary" id="btn_piutang_receive_remaining" title="Isi seluruh sisa piutang">Terima Sisa</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Metode</label>
                                <select name="metode_penerimaan" class="form-select" required>
                                    <option value="tunai">Tunai</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="giro">Giro</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">No. Bukti</label>
                                <input type="text" name="no_bukti" class="form-control" placeholder="Opsional">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label fw-bold">Keterangan</label>
                                <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100" id="btn_save_piutang_settlement">
                                    <i class="bi bi-check2-circle"></i> Simpan Penerimaan
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <section>
                    <h6 class="piutang-settlement-section-title"><i class="bi bi-clock-history"></i> Riwayat Penerimaan</h6>
                    <div class="table-responsive piutang-settlement-history">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>No. Penerimaan</th>
                                    <th>Tanggal</th>
                                    <th>Metode</th>
                                    <th>No. Bukti</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody id="piutang_settlement_history_rows">
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat penerimaan</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    const piutangSettlementApiUrl = "<?= base_url ?>classes/PiutangPelanggan.php";
    let activePiutangSettlementId = 0;
    let activePiutangRemaining = 0;

    $(function() {
        $('#form_piutang_settlement').on('submit', function(event) {
            event.preventDefault();
            savePiutangSettlement();
        });

        $('#btn_piutang_receive_remaining').on('click', function() {
            setPiutangSettlementAmount(activePiutangRemaining);
            $('#piutang_settlement_amount').trigger('focus').select();
        });

        $('#piutang_settlement_amount').on('input', function() {
            setPiutangSettlementAmount($(this).val());
        });

        $('#modal_piutang_settlement').on('hidden.bs.modal', function() {
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('piutang_id');
                window.history.replaceState({}, '', url.toString());
            }
        });

        const requestedId = Number(new URLSearchParams(window.location.search).get('piutang_id') || 0);
        if (requestedId > 0) {
            openPiutangSettlement(requestedId);
        }
    });

    function openPiutangSettlement(id) {
        const piutangId = Number(id || 0);
        if (piutangId <= 0) return;

        activePiutangSettlementId = piutangId;
        activePiutangRemaining = 0;
        $('#form_piutang_settlement')[0].reset();
        $('#piutang_settlement_id').val(piutangId);
        $('#piutang_settlement_date').val(piutangSettlementToday());
        $('#piutang_settlement_identity').text('Memuat data piutang...');
        $('#piutang_settlement_msg').empty();
        $('#piutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat riwayat...</td></tr>');
        $('#modal_piutang_settlement').modal('show');

        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('piutang_id', piutangId);
            window.history.replaceState({}, '', url.toString());
        }

        loadPiutangSettlement();
    }

    function loadPiutangSettlement() {
        if (activePiutangSettlementId <= 0) return;

        $.getJSON(piutangSettlementApiUrl + '?f=get_piutang_detail', { id: activePiutangSettlementId })
            .done(function(detail) {
                if (!detail || detail.status === 'failed') {
                    showPiutangSettlementMessage(detail?.msg || 'Data piutang tidak ditemukan', 'danger');
                    return;
                }
                renderPiutangSettlementDetail(detail);
            })
            .fail(function(xhr) {
                showPiutangSettlementMessage(xhr.responseJSON?.msg || 'Gagal memuat detail piutang', 'danger');
            });

        $.getJSON(piutangSettlementApiUrl + '?f=get_penerimaan_history', { piutang_id: activePiutangSettlementId })
            .done(renderPiutangSettlementHistory)
            .fail(function(xhr) {
                $('#piutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-danger py-4">' + escapePiutangSettlement(xhr.responseJSON?.msg || 'Gagal memuat riwayat penerimaan') + '</td></tr>');
            });
    }

    function renderPiutangSettlementDetail(detail) {
        const total = Number(detail.total_piutang || 0);
        const received = Number(detail.total_diterima || 0);
        const remaining = Math.max(Number(detail.sisa_piutang || 0), 0);
        const status = String(detail.status || 'BELUM').toUpperCase();
        const isPaid = status === 'LUNAS' || remaining <= 0;

        activePiutangRemaining = remaining;
        $('#piutang_settlement_identity').text((detail.no_piutang || '-') + ' · ' + (detail.nama_pelanggan || '-'));
        $('#piutang_settlement_total').text(formatPiutangSettlementCurrency(total));
        $('#piutang_settlement_received').text(formatPiutangSettlementCurrency(received));
        $('#piutang_settlement_remaining').text(formatPiutangSettlementCurrency(remaining));
        $('#piutang_settlement_status').html(piutangSettlementStatusBadge(status, isPaid));
        $('#piutang_settlement_amount').attr('data-max', remaining);
        $('#piutang_settlement_form_section').toggleClass('d-none', isPaid);
        $('#piutang_settlement_paid_notice').toggleClass('d-none', !isPaid);
    }

    function renderPiutangSettlementHistory(history) {
        if (!Array.isArray(history) || history.length === 0) {
            $('#piutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat penerimaan</td></tr>');
            return;
        }

        const rows = history.map(function(item, index) {
            return '<tr>' +
                '<td class="text-center">' + (index + 1) + '</td>' +
                '<td class="fw-semibold">' + escapePiutangSettlement(item.no_penerimaan || '-') + '</td>' +
                '<td>' + escapePiutangSettlement(item.tanggal_penerimaan || '-') + '</td>' +
                '<td>' + escapePiutangSettlement(capitalizePiutangSettlement(item.metode_penerimaan || 'tunai')) + '</td>' +
                '<td>' + escapePiutangSettlement(item.no_bukti || '-') + '</td>' +
                '<td>' + escapePiutangSettlement(item.keterangan || '-') + '</td>' +
                '<td class="text-end fw-bold">Rp ' + escapePiutangSettlement(item.jumlah_penerimaan || '0') + '</td>' +
                '</tr>';
        }).join('');
        $('#piutang_settlement_history_rows').html(rows);
    }

    function savePiutangSettlement() {
        const amount = Number($('#piutang_settlement_amount_raw').val() || 0);
        if (amount <= 0) {
            showPiutangSettlementMessage('Jumlah penerimaan harus lebih dari 0', 'warning');
            return;
        }
        if (amount > activePiutangRemaining) {
            showPiutangSettlementMessage('Jumlah penerimaan tidak boleh melebihi sisa piutang', 'warning');
            return;
        }

        const $button = $('#btn_save_piutang_settlement');
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan');

        $.ajax({
            url: piutangSettlementApiUrl + '?f=save_penerimaan',
            method: 'POST',
            dataType: 'json',
            data: $('#form_piutang_settlement').serialize()
        }).done(function(response) {
            if (!response || response.status !== 'success') {
                showPiutangSettlementMessage(response?.msg || 'Gagal menyimpan penerimaan', 'danger');
                return;
            }

            showPiutangSettlementMessage(response.msg || 'Penerimaan berhasil disimpan', 'success');
            setPiutangSettlementAmount('');
            $('#form_piutang_settlement [name="no_bukti"], #form_piutang_settlement [name="keterangan"]').val('');
            loadPiutangSettlement();
            if (typeof reloadPiutangTable === 'function') {
                reloadPiutangTable();
            }
        }).fail(function(xhr) {
            showPiutangSettlementMessage(xhr.responseJSON?.msg || 'Gagal menyimpan penerimaan', 'danger');
        }).always(function() {
            $button.prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Simpan Penerimaan');
        });
    }

    function showPiutangSettlementMessage(message, type) {
        $('#piutang_settlement_msg').html('<div class="alert alert-' + (type || 'primary') + ' alert-dismissible fade show" role="alert">' +
            escapePiutangSettlement(message) +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>');
    }

    function piutangSettlementStatusBadge(status, isPaid) {
        if (isPaid) return '<span class="badge bg-success">Lunas</span>';
        if (status === 'OVERDUE') return '<span class="badge bg-danger">Terlambat / Belum Lunas</span>';
        return '<span class="badge bg-warning text-dark">Belum Lunas</span>';
    }

    function formatPiutangSettlementCurrency(value) {
        return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    }

    function setPiutangSettlementAmount(value) {
        const digits = String(value ?? '').replace(/\D/g, '');
        const amount = digits === '' ? 0 : Number.parseInt(digits, 10) || 0;
        $('#piutang_settlement_amount').val(digits === '' ? '' : amount.toLocaleString('id-ID'));
        $('#piutang_settlement_amount_raw').val(digits === '' ? '' : String(amount));
    }

    function piutangSettlementToday() {
        const now = new Date();
        return new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 10);
    }

    function capitalizePiutangSettlement(value) {
        const text = String(value || '');
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function escapePiutangSettlement(value) {
        return $('<div>').text(value ?? '').html();
    }
</script>
