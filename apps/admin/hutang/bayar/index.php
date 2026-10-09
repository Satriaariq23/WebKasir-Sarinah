<?php
/**
 * Komponen pembayaran dan riwayat hutang supplier.
 * Dipakai oleh apps/admin/hutang/index.php.
 */
if (($_GET['page'] ?? '') === 'hutang/bayar') {
    $redirectUrl = base_url . 'admin/?page=hutang';
    if (!empty($_GET['hutang_id'])) {
        $redirectUrl .= '&hutang_id=' . rawurlencode((string)$_GET['hutang_id']);
    }
    echo '<script>window.location.replace(' . json_encode($redirectUrl) . ');</script>';
    return;
}
?>

<style>
    #modal_hutang_settlement .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 16px;
        box-shadow: 0 22px 60px rgba(24, 28, 50, .22);
    }

    #modal_hutang_settlement .modal-header {
        border: 0;
        background: #181c32;
        color: #fff;
    }

    #modal_hutang_settlement .modal-title,
    #modal_hutang_settlement .modal-subtitle {
        color: #fff !important;
    }

    #modal_hutang_settlement .modal-subtitle {
        margin-top: 3px;
        color: rgba(255, 255, 255, .7) !important;
        font-size: .78rem;
    }

    #modal_hutang_settlement .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .hutang-settlement-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 18px;
    }

    .hutang-settlement-stat {
        min-width: 0;
        padding: 14px;
        border: 1px solid #e4e6ef;
        border-radius: 12px;
        background: #f8fafc;
    }

    .hutang-settlement-stat small {
        display: block;
        margin-bottom: 5px;
        color: #7e8299;
        font-weight: 600;
    }

    .hutang-settlement-stat strong {
        display: block;
        overflow: hidden;
        color: #181c32;
        font-size: 1rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hutang-settlement-form {
        margin-bottom: 18px;
        padding: 16px;
        border: 1px solid #d9f3e5;
        border-radius: 12px;
        background: #f3fbf7;
    }

    .hutang-settlement-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 13px;
        color: #181c32;
        font-size: .95rem;
        font-weight: 700;
    }

    .hutang-settlement-history {
        overflow: hidden;
        border: 1px solid #e4e6ef;
        border-radius: 12px;
    }

    .hutang-settlement-history .table {
        min-width: 760px;
        margin: 0;
    }

    .hutang-settlement-history thead th {
        border: 0;
        background: #181c32;
        color: #fff;
        font-size: .76rem;
        white-space: nowrap;
    }

    .hutang-settlement-history tbody td {
        color: #3f4254;
        font-size: .8rem;
        vertical-align: middle;
    }

    @media (max-width: 767.98px) {
        .hutang-settlement-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #modal_hutang_settlement .modal-body { padding: 14px; }
        .hutang-settlement-form { padding: 13px; }
    }
</style>

<div class="modal fade" id="modal_hutang_settlement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header px-4 py-3">
                <div>
                    <h5 class="modal-title">Pembayaran Hutang Supplier</h5>
                    <div class="modal-subtitle" id="hutang_settlement_identity">Memuat data hutang...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div id="hutang_settlement_msg"></div>

                <div class="hutang-settlement-summary">
                    <div class="hutang-settlement-stat">
                        <small>Total Hutang</small>
                        <strong id="hutang_settlement_total">Rp 0</strong>
                    </div>
                    <div class="hutang-settlement-stat">
                        <small>Sudah Dibayar</small>
                        <strong id="hutang_settlement_paid">Rp 0</strong>
                    </div>
                    <div class="hutang-settlement-stat">
                        <small>Sisa Hutang</small>
                        <strong id="hutang_settlement_remaining">Rp 0</strong>
                    </div>
                    <div class="hutang-settlement-stat">
                        <small>Status</small>
                        <strong id="hutang_settlement_status">-</strong>
                    </div>
                </div>

                <div class="alert alert-success d-none" id="hutang_settlement_paid_notice">
                    <i class="bi bi-check-circle me-2"></i>Hutang sudah lunas. Form pembayaran ditutup, sedangkan riwayat tetap dapat dilihat.
                </div>

                <section class="hutang-settlement-form" id="hutang_settlement_form_section">
                    <h6 class="hutang-settlement-section-title"><i class="bi bi-cash-stack"></i> Input Pembayaran</h6>
                    <form id="form_hutang_settlement">
                        <input type="hidden" name="hutang_id" id="hutang_settlement_id">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tanggal Bayar</label>
                                <input type="date" name="tanggal_bayar" id="hutang_settlement_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Jumlah Bayar</label>
                                <div class="input-group">
                                    <input type="text" id="hutang_settlement_amount" class="form-control text-end" inputmode="numeric" autocomplete="off" placeholder="0" required>
                                    <input type="hidden" name="jumlah_bayar" id="hutang_settlement_amount_raw" value="">
                                    <button type="button" class="btn btn-light-primary" id="btn_hutang_pay_remaining" title="Isi seluruh sisa hutang">Bayar Sisa</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Metode</label>
                                <select name="metode_bayar" class="form-select" required>
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
                                <button type="submit" class="btn btn-success w-100" id="btn_save_hutang_settlement">
                                    <i class="bi bi-check2-circle"></i> Simpan Pembayaran
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <section>
                    <h6 class="hutang-settlement-section-title"><i class="bi bi-clock-history"></i> Riwayat Pembayaran</h6>
                    <div class="table-responsive hutang-settlement-history">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>No. Pembayaran</th>
                                    <th>Tanggal</th>
                                    <th>Metode</th>
                                    <th>No. Bukti</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody id="hutang_settlement_history_rows">
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat pembayaran</td></tr>
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
    const hutangSettlementApiUrl = "<?= base_url ?>classes/HutangSupplier.php";
    let activeHutangSettlementId = 0;
    let activeHutangRemaining = 0;

    $(function() {
        $('#form_hutang_settlement').on('submit', function(event) {
            event.preventDefault();
            saveHutangSettlement();
        });

        $('#btn_hutang_pay_remaining').on('click', function() {
            setHutangSettlementAmount(activeHutangRemaining);
            $('#hutang_settlement_amount').trigger('focus').select();
        });

        $('#hutang_settlement_amount').on('input', function() {
            setHutangSettlementAmount($(this).val());
        });

        $('#modal_hutang_settlement').on('hidden.bs.modal', function() {
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('hutang_id');
                window.history.replaceState({}, '', url.toString());
            }
        });

        const requestedId = Number(new URLSearchParams(window.location.search).get('hutang_id') || 0);
        if (requestedId > 0) {
            openHutangSettlement(requestedId);
        }
    });

    function openHutangSettlement(id) {
        const hutangId = Number(id || 0);
        if (hutangId <= 0) return;

        activeHutangSettlementId = hutangId;
        activeHutangRemaining = 0;
        $('#form_hutang_settlement')[0].reset();
        $('#hutang_settlement_id').val(hutangId);
        $('#hutang_settlement_date').val(hutangSettlementToday());
        $('#hutang_settlement_identity').text('Memuat data hutang...');
        $('#hutang_settlement_msg').empty();
        $('#hutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat riwayat...</td></tr>');
        $('#modal_hutang_settlement').modal('show');

        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('hutang_id', hutangId);
            window.history.replaceState({}, '', url.toString());
        }

        loadHutangSettlement();
    }

    function loadHutangSettlement() {
        if (activeHutangSettlementId <= 0) return;

        $.getJSON(hutangSettlementApiUrl + '?f=get_hutang_detail', { id: activeHutangSettlementId })
            .done(function(detail) {
                if (!detail || detail.status === 'failed') {
                    showHutangSettlementMessage(detail?.msg || 'Data hutang tidak ditemukan', 'danger');
                    return;
                }
                renderHutangSettlementDetail(detail);
            })
            .fail(function(xhr) {
                showHutangSettlementMessage(xhr.responseJSON?.msg || 'Gagal memuat detail hutang', 'danger');
            });

        $.getJSON(hutangSettlementApiUrl + '?f=get_pembayaran_history', { hutang_id: activeHutangSettlementId })
            .done(renderHutangSettlementHistory)
            .fail(function(xhr) {
                $('#hutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHutangSettlement(xhr.responseJSON?.msg || 'Gagal memuat riwayat pembayaran') + '</td></tr>');
            });
    }

    function renderHutangSettlementDetail(detail) {
        const total = Number(detail.total_hutang || 0);
        const paid = Number(detail.total_dibayar || 0);
        const remaining = Math.max(Number(detail.sisa_hutang || 0), 0);
        const status = String(detail.status || 'BELUM').toUpperCase();
        const isPaid = status === 'LUNAS' || remaining <= 0;

        activeHutangRemaining = remaining;
        $('#hutang_settlement_identity').text((detail.no_hutang || '-') + ' · ' + (detail.nama_supplier || '-'));
        $('#hutang_settlement_total').text(formatHutangSettlementCurrency(total));
        $('#hutang_settlement_paid').text(formatHutangSettlementCurrency(paid));
        $('#hutang_settlement_remaining').text(formatHutangSettlementCurrency(remaining));
        $('#hutang_settlement_status').html(hutangSettlementStatusBadge(status, isPaid));
        $('#hutang_settlement_amount').attr('data-max', remaining);
        $('#hutang_settlement_form_section').toggleClass('d-none', isPaid);
        $('#hutang_settlement_paid_notice').toggleClass('d-none', !isPaid);
    }

    function renderHutangSettlementHistory(history) {
        if (!Array.isArray(history) || history.length === 0) {
            $('#hutang_settlement_history_rows').html('<tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat pembayaran</td></tr>');
            return;
        }

        const rows = history.map(function(item, index) {
            return '<tr>' +
                '<td class="text-center">' + (index + 1) + '</td>' +
                '<td class="fw-semibold">' + escapeHutangSettlement(item.no_pembayaran || '-') + '</td>' +
                '<td>' + escapeHutangSettlement(item.tanggal_bayar || '-') + '</td>' +
                '<td>' + escapeHutangSettlement(capitalizeHutangSettlement(item.metode_bayar || 'tunai')) + '</td>' +
                '<td>' + escapeHutangSettlement(item.no_bukti || '-') + '</td>' +
                '<td>' + escapeHutangSettlement(item.keterangan || '-') + '</td>' +
                '<td class="text-end fw-bold">Rp ' + escapeHutangSettlement(item.jumlah_bayar || '0') + '</td>' +
                '</tr>';
        }).join('');
        $('#hutang_settlement_history_rows').html(rows);
    }

    function saveHutangSettlement() {
        const amount = Number($('#hutang_settlement_amount_raw').val() || 0);
        if (amount <= 0) {
            showHutangSettlementMessage('Jumlah pembayaran harus lebih dari 0', 'warning');
            return;
        }
        if (amount > activeHutangRemaining) {
            showHutangSettlementMessage('Jumlah pembayaran tidak boleh melebihi sisa hutang', 'warning');
            return;
        }

        const $button = $('#btn_save_hutang_settlement');
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan');

        $.ajax({
            url: hutangSettlementApiUrl + '?f=save_pembayaran',
            method: 'POST',
            dataType: 'json',
            data: $('#form_hutang_settlement').serialize()
        }).done(function(response) {
            if (!response || response.status !== 'success') {
                showHutangSettlementMessage(response?.msg || 'Gagal menyimpan pembayaran', 'danger');
                return;
            }

            showHutangSettlementMessage(response.msg || 'Pembayaran berhasil disimpan', 'success');
            setHutangSettlementAmount('');
            $('#form_hutang_settlement [name="no_bukti"], #form_hutang_settlement [name="keterangan"]').val('');
            loadHutangSettlement();
            if ($.fn.DataTable.isDataTable('#table_hutang')) {
                $('#table_hutang').DataTable().ajax.reload(null, false);
            }
        }).fail(function(xhr) {
            showHutangSettlementMessage(xhr.responseJSON?.msg || 'Gagal menyimpan pembayaran', 'danger');
        }).always(function() {
            $button.prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Simpan Pembayaran');
        });
    }

    function showHutangSettlementMessage(message, type) {
        $('#hutang_settlement_msg').html('<div class="alert alert-' + (type || 'primary') + ' alert-dismissible fade show" role="alert">' +
            escapeHutangSettlement(message) +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>');
    }

    function hutangSettlementStatusBadge(status, isPaid) {
        if (isPaid) return '<span class="badge bg-success">Lunas</span>';
        if (status === 'OVERDUE') return '<span class="badge bg-danger">Terlambat / Belum Lunas</span>';
        return '<span class="badge bg-warning text-dark">Belum Lunas</span>';
    }

    function formatHutangSettlementCurrency(value) {
        return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    }

    function setHutangSettlementAmount(value) {
        const digits = String(value ?? '').replace(/\D/g, '');
        const amount = digits === '' ? 0 : Number.parseInt(digits, 10) || 0;
        $('#hutang_settlement_amount').val(digits === '' ? '' : amount.toLocaleString('id-ID'));
        $('#hutang_settlement_amount_raw').val(digits === '' ? '' : String(amount));
    }

    function hutangSettlementToday() {
        const now = new Date();
        return new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 10);
    }

    function capitalizeHutangSettlement(value) {
        const text = String(value || '');
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function escapeHutangSettlement(value) {
        return $('<div>').text(value ?? '').html();
    }
</script>
