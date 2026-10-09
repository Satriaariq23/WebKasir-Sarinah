<?php
$ppnRate = $_settings->info('ppn_rate');
if ($ppnRate === false || $ppnRate === '' || !is_numeric($ppnRate)) {
    $ppnRate = 11;
}
$ppnRate = max(0, min(100, (float)$ppnRate));
$companyName = $_settings->info('name') ?: 'CV. Sarinah Logam';
?>

<style>
    .ppn-summary-card {
        border: 1px solid #e4e6ef;
        border-radius: 8px;
        padding: 14px 16px;
        background: #f8f9fb;
        height: 100%;
    }

    .ppn-summary-label {
        color: #7e8299;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .ppn-summary-value {
        color: #181c32;
        font-size: 19px;
        font-weight: 700;
        margin-top: 4px;
    }

    .ppn-table-wrap {
        width: 100%;
        overflow-x: hidden;
        padding-bottom: 2px;
    }

    #ppn_report_table {
        width: 100% !important;
        margin: 0 !important;
        border-collapse: collapse !important;
        border: 1px solid #dfe3e8 !important;
    }

    #ppn_report_table td,
    #ppn_report_table th {
        padding: 10px 12px !important;
        border: 1px solid #e1e5ea !important;
        white-space: nowrap;
        vertical-align: middle;
    }

    #ppn_report_table thead th {
        background: #f5f7fa !important;
        color: #3f4254;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.25;
        text-align: center;
    }

    #ppn_report_table tbody td {
        background-clip: padding-box;
        font-size: 12px;
    }

    #ppn_report_table tbody tr:nth-child(even) td {
        background-color: #fafbfc;
    }

    #ppn_report_table tbody tr:hover td {
        background-color: #f1f7ff !important;
    }

    #ppn_report_table td.dtr-control {
        cursor: pointer;
    }

    #ppn_report_table > tbody > tr.child > td {
        padding: 0 !important;
        background: #f8fbff !important;
    }

    .ppn-responsive-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
        width: 100%;
        padding: 8px 12px;
    }

    .ppn-responsive-detail {
        display: grid;
        grid-template-columns: minmax(105px, 38%) minmax(0, 1fr);
        gap: 10px;
        padding: 8px 10px;
        border-bottom: 1px solid #e9edf2;
    }

    .ppn-responsive-detail__label {
        color: #7e8299;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .ppn-responsive-detail__value {
        min-width: 0;
        color: #181c32;
        overflow-wrap: anywhere;
    }

    #ppn_report_table_wrapper .dt-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    #ppn_report_table_wrapper .dt-buttons .btn {
        flex: 0 0 auto !important;
        width: auto !important;
        min-width: 88px;
    }

    #ppn_report_table_wrapper .dataTables_filter {
        margin-bottom: 12px;
    }

    #ppn_report_table_wrapper .dataTables_info,
    #ppn_report_table_wrapper .dataTables_paginate {
        padding-top: 14px;
    }

    .ppn-responsive-hint {
        display: none;
    }

    @media (max-width: 991.98px) {
        .ppn-responsive-hint {
            display: block;
        }

        .ppn-responsive-details {
            grid-template-columns: 1fr;
        }

        #ppn_report_table td,
        #ppn_report_table th {
            padding: 9px 8px !important;
        }
    }

    @media (max-width: 767.98px) {
        .content-wrapper {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }

        .content-wrapper > .card > .card-header,
        .content-wrapper > .card > .card-body {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .ppn-summary-value {
            font-size: 16px;
        }

        #ppn_report_table_wrapper .dt-buttons {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        #ppn_report_table_wrapper .dt-buttons .btn {
            width: 100% !important;
            min-width: 0;
            margin: 0 !important;
        }

        #ppn_report_table_wrapper .dataTables_filter {
            float: none !important;
            width: 100%;
            text-align: left !important;
        }

        #ppn_report_table_wrapper .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        #ppn_report_table_wrapper .dataTables_filter input {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 0 !important;
        }

        #ppn_report_table_wrapper .dataTables_info,
        #ppn_report_table_wrapper .dataTables_paginate {
            float: none !important;
            width: 100%;
            text-align: center !important;
        }

        #ppn_report_table {
            font-size: 11px;
        }
    }

    @media (max-width: 479.98px) {
        #ppn_report_table_wrapper .dt-buttons {
            grid-template-columns: 1fr;
        }

        .ppn-responsive-detail {
            grid-template-columns: 95px minmax(0, 1fr);
        }
    }
</style>

<div class="content-wrapper py-3 px-3">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h3 class="mb-0"><i class="bi bi-receipt me-2"></i>Laporan Penjualan PPN &amp; Non-PPN</h3>
                <div class="text-muted fs-7">
                    Nilai PPN dihitung per barang dari harga inklusif PPN. Retur yang sudah diposting tampil sebagai pengurang.
                </div>
            </div>
            <a href="<?= base_url ?>admin/" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card-body">
            <div class="border rounded p-4 mb-5">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold">Tanggal Awal</label>
                        <input type="date" id="ppn_date_start" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold">Tanggal Akhir</label>
                        <input type="date" id="ppn_date_end" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold">Status Barang</label>
                        <select id="ppn_tax_type" class="form-select">
                            <option value="all">Semua</option>
                            <option value="ppn">PPN</option>
                            <option value="non_ppn">Non-PPN</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold">Sumber</label>
                        <select id="ppn_source" class="form-select">
                            <option value="all">Semua Kanal</option>
                            <option value="sales">Sales</option>
                            <option value="online">Sales Online</option>
                            <option value="kasir">Kasir</option>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-8 d-flex gap-2">
                        <button type="button" id="btn_load_ppn_report" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-search"></i> Tampilkan
                        </button>
                        <button type="button" id="btn_reset_ppn_report" class="btn btn-light">
                            Reset
                        </button>
                    </div>
                </div>
                <div class="text-muted fs-8 mt-3">
                    Tarif aktif: <strong id="active_ppn_rate"><?= htmlspecialchars(rtrim(rtrim(number_format($ppnRate, 2, '.', ''), '0'), '.')) ?>%</strong>.
                    Harga barang PPN dianggap sudah termasuk PPN.
                </div>
            </div>

            <div class="row g-3 mb-5">
                <div class="col-xl col-md-4 col-6">
                    <div class="ppn-summary-card">
                        <div class="ppn-summary-label">DPP Barang PPN</div>
                        <div class="ppn-summary-value" id="summary_dpp_ppn">Rp 0</div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-6">
                    <div class="ppn-summary-card">
                        <div class="ppn-summary-label">PPN Keluaran</div>
                        <div class="ppn-summary-value text-danger" id="summary_ppn">Rp 0</div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-6">
                    <div class="ppn-summary-card">
                        <div class="ppn-summary-label">Total Barang PPN</div>
                        <div class="ppn-summary-value" id="summary_total_ppn">Rp 0</div>
                    </div>
                </div>
                <div class="col-xl col-md-6 col-6">
                    <div class="ppn-summary-card">
                        <div class="ppn-summary-label">Total Non-PPN</div>
                        <div class="ppn-summary-value" id="summary_non_ppn">Rp 0</div>
                    </div>
                </div>
                <div class="col-xl col-md-6 col-12">
                    <div class="ppn-summary-card bg-light-primary">
                        <div class="ppn-summary-label">Total Gabungan</div>
                        <div class="ppn-summary-value text-primary" id="summary_grand_total">Rp 0</div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-0">Rincian per Barang</h5>
                    <small class="text-muted" id="ppn_report_count">0 transaksi, 0 baris</small>
                </div>
                <small class="text-muted">Ekspor tersedia: Excel, CSV, PDF, dan cetak.</small>
            </div>
            <div class="ppn-responsive-hint alert alert-light-primary py-2 px-3 mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Ketuk kolom nomor pada baris untuk melihat rincian yang disembunyikan pada layar kecil.
            </div>

            <div class="ppn-table-wrap">
                <table id="ppn_report_table" class="table table-bordered table-striped table-hover w-100"></table>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    let ppnReportTable = null;
    let currentSummary = {};
    const exportColumns = Array.from({ length: 14 }, (value, index) => index);

    const rupiah = value => 'Rp ' + Math.round(Number(value || 0)).toLocaleString('id-ID');
    const number = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const escapeHtml = value => $('<div>').text(value == null ? '' : String(value)).html();

    function amountRenderer(data, type) {
        const value = Number(data || 0);
        return type === 'display' ? rupiah(value) : value;
    }

    function numberRenderer(data, type) {
        const value = Number(data || 0);
        return type === 'display' ? number(value) : value;
    }

    function exportTitle() {
        const status = $('#ppn_tax_type option:selected').text();
        const source = $('#ppn_source option:selected').text();
        return `Laporan PPN dan Non-PPN - ${status} - ${source}`;
    }

    function exportFileName() {
        return `laporan_ppn_${$('#ppn_date_start').val()}_sd_${$('#ppn_date_end').val()}`;
    }

    function activeRate() {
        const value = Number(currentSummary.ppn_rate);
        return Number.isFinite(value) ? value : <?= json_encode($ppnRate) ?>;
    }

    function exportPeriod() {
        return `Periode ${$('#ppn_date_start').val()} s/d ${$('#ppn_date_end').val()} | Tarif PPN ${activeRate().toLocaleString('id-ID')}%`;
    }

    function setSummary(summary) {
        currentSummary = summary || {};
        $('#summary_dpp_ppn').text(rupiah(currentSummary.dpp_ppn));
        $('#summary_ppn').text(rupiah(currentSummary.ppn));
        $('#summary_total_ppn').text(rupiah(currentSummary.total_ppn));
        $('#summary_non_ppn').text(rupiah(currentSummary.total_non_ppn));
        $('#summary_grand_total').text(rupiah(currentSummary.grand_total));
        $('#active_ppn_rate').text(number(currentSummary.ppn_rate || 0) + '%');
        $('#ppn_report_count').text(
            `${Number(currentSummary.jumlah_transaksi || 0).toLocaleString('id-ID')} transaksi, ` +
            `${Number(currentSummary.jumlah_baris || 0).toLocaleString('id-ID')} baris`
        );
    }

    function renderTable(rows) {
        if (ppnReportTable) {
            ppnReportTable.destroy();
            $('#ppn_report_table').empty();
        }

        ppnReportTable = $('#ppn_report_table').DataTable({
            data: rows || [],
            columns: [
                { title: 'No', data: null, className: 'text-center', orderable: false, responsivePriority: 1, render: (data, type, row, meta) => meta.row + 1 },
                { title: 'Tanggal', data: 'tanggal', className: 'text-center', responsivePriority: 2, render: data => escapeHtml(data || '-') },
                {
                    title: 'Jenis', data: 'jenis_transaksi', className: 'text-center', responsivePriority: 8,
                    render: data => `<span class="badge ${data === 'RETUR' ? 'badge-light-danger' : 'badge-light-success'}">${escapeHtml(data)}</span>`
                },
                { title: 'Invoice', data: 'invoice', responsivePriority: 3, render: data => escapeHtml(data || '-') },
                { title: 'Invoice Asal', data: 'invoice_asal', responsivePriority: 12, render: data => escapeHtml(data || '-') },
                { title: 'Sumber', data: 'sumber', className: 'text-center', responsivePriority: 9, render: data => escapeHtml(data || '-') },
                { title: 'Petugas', data: 'petugas', responsivePriority: 7, render: data => escapeHtml(data || '-') },
                { title: 'Barcode', data: 'barcode', responsivePriority: 11, render: data => escapeHtml(data || '-') },
                { title: 'Nama Barang', data: 'nama_produk', responsivePriority: 2, render: data => escapeHtml(data || '-') },
                {
                    title: 'Status', data: 'status_ppn', className: 'text-center', responsivePriority: 4,
                    render: data => `<span class="badge ${data === 'PPN' ? 'badge-light-danger' : 'badge-light-secondary'}">${escapeHtml(data)}</span>`
                },
                { title: 'Qty', data: 'qty', className: 'text-end', responsivePriority: 5, render: numberRenderer },
                { title: 'DPP', data: 'dpp', className: 'text-end', responsivePriority: 8, render: amountRenderer },
                { title: 'PPN', data: 'ppn', className: 'text-end', responsivePriority: 6, render: amountRenderer },
                { title: 'Total', data: 'total', className: 'text-end fw-bold', responsivePriority: 1, render: amountRenderer }
            ],
            order: [],
            pageLength: 25,
            responsive: {
                details: {
                    type: 'inline',
                    renderer: function(api, rowIndex, columns) {
                        const hiddenColumns = columns
                            .filter(column => column.hidden)
                            .map(column => `
                                <div class="ppn-responsive-detail">
                                    <div class="ppn-responsive-detail__label">${escapeHtml(column.title || '-')}</div>
                                    <div class="ppn-responsive-detail__value">${column.data || '-'}</div>
                                </div>
                            `);

                        return hiddenColumns.length
                            ? $('<div class="ppn-responsive-details"></div>').append(hiddenColumns.join(''))
                            : false;
                    }
                }
            },
            autoWidth: false,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: 'Excel',
                    title: exportTitle,
                    filename: exportFileName,
                    messageTop: exportPeriod,
                    exportOptions: { columns: exportColumns }
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    title: exportTitle,
                    filename: exportFileName,
                    exportOptions: { columns: exportColumns }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: exportTitle,
                    filename: exportFileName,
                    messageTop: exportPeriod,
                    orientation: 'landscape',
                    pageSize: 'A3',
                    exportOptions: { columns: exportColumns }
                },
                {
                    extend: 'print',
                    text: 'Cetak / Simpan PDF',
                    title: exportTitle,
                    messageTop: () => `<div>${escapeHtml(<?= json_encode($companyName) ?>)}</div><div>${escapeHtml(exportPeriod())}</div>`,
                    exportOptions: { columns: exportColumns },
                    customize: function(win) {
                        $(win.document.body).css('font-size', '9pt');
                        $(win.document.body).find('table').addClass('compact').css('font-size', '8pt');
                    }
                }
            ],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                zeroRecords: 'Tidak ada data pada filter ini',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ baris',
                infoEmpty: 'Menampilkan 0 data',
                infoFiltered: '(disaring dari _MAX_ data)',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            }
        });

        setTimeout(function() {
            if (ppnReportTable) {
                ppnReportTable.columns.adjust();
                ppnReportTable.responsive.recalc();
            }
        }, 0);
    }

    function loadReport() {
        const start = $('#ppn_date_start').val();
        const end = $('#ppn_date_end').val();
        if (!start || !end) {
            alert_toast('Tanggal awal dan akhir wajib diisi.', 'warning');
            return;
        }

        start_loader();
        $.ajax({
            url: '<?= base_url ?>classes/Laporan.php?f=get_laporan_ppn',
            type: 'POST',
            dataType: 'json',
            data: {
                date_start: start,
                date_end: end,
                tax_type: $('#ppn_tax_type').val(),
                source: $('#ppn_source').val()
            },
            success: function(resp) {
                if (resp.status !== 'success') {
                    alert_toast(resp.error || 'Gagal memuat laporan PPN.', 'danger');
                    return;
                }
                setSummary(resp.summary || {});
                renderTable(resp.data || []);
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Gagal memuat laporan PPN.';
                alert_toast(message, 'danger');
            },
            complete: end_loader
        });
    }

    $('#btn_load_ppn_report').on('click', loadReport);
    $('#btn_reset_ppn_report').on('click', function() {
        $('#ppn_date_start').val('<?= date('Y-m-01') ?>');
        $('#ppn_date_end').val('<?= date('Y-m-d') ?>');
        $('#ppn_tax_type, #ppn_source').val('all');
        loadReport();
    });

    loadReport();
});
</script>
