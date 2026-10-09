<?php
$salesmen = [];
$pelanggan = [];
$defaultDate = date('Y-m-d');

if (isset($conn) && $conn instanceof mysqli) {
    if ($dateQry = $conn->query("SELECT DATE(MAX(sale_date)) AS last_date FROM sale WHERE COALESCE(sale_status, 'ACTIVE') = 'ACTIVE' AND sale_channel IN ('sales', 'online')")) {
        $row = $dateQry->fetch_assoc();
        if (!empty($row['last_date'])) {
            $defaultDate = $row['last_date'];
        }
        $dateQry->free();
    }

    if ($salesQry = $conn->query("SELECT id, firstname, username FROM users WHERE level_id = 3 AND status = 1 ORDER BY firstname ASC")) {
        while ($row = $salesQry->fetch_assoc()) {
            $salesmen[] = $row;
        }
        $salesQry->free();
    }

    $pelangganSql = "SELECT p.id, p.kode, p.nama
                     FROM pelanggan p
                     WHERE p.status = 1
                       AND EXISTS (
                         SELECT 1
                         FROM sale s
                         WHERE s.cust_id = p.id
                           AND COALESCE(s.sale_status, 'ACTIVE') = 'ACTIVE'
                            AND s.sale_channel IN ('sales', 'online')
                       )
                     ORDER BY p.nama ASC";
    if ($pelangganQry = $conn->query($pelangganSql)) {
        while ($row = $pelangganQry->fetch_assoc()) {
            $pelanggan[] = $row;
        }
        $pelangganQry->free();
    }
}

$companyName = $_settings->info('name') ?: 'CV. Sarinah Logam';
$companyAddress = $_settings->info('alamat') ?: '';
$companyContact = $_settings->info('contact') ?: ($_settings->info('phone') ?: '');
$companyLogo = validate_image($_settings->info('logo'));
?>

<style>
  .sales-report-filter {
    max-width: 760px;
  }

  .sales-report-filter .form-check-input {
    cursor: pointer;
  }

  .sales-report-filter .select2-container {
    width: 100% !important;
  }

  .sales-summary-box {
    border: 1px solid #e4e6ef;
    border-radius: 6px;
    padding: 14px 16px;
    background: #f8f9fb;
    height: 100%;
  }

  .sales-summary-label {
    color: #7e8299;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
  }

  .sales-summary-value {
    color: #181c32;
    font-size: 18px;
    font-weight: 700;
  }

  .filter-select-group.d-none {
    display: none !important;
  }
</style>

<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h3 class="mb-0">Laporan Penjualan Sales</h3>
        <div class="text-muted fs-7">Periode, salesman, pelanggan, detail item, dan rekap total.</div>
      </div>
      <a href="<?= base_url ?>admin/" class="btn btn-light btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
    </div>
    <div class="card-body">
      <div class="sales-report-filter border rounded p-4 mb-5">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label fw-bold">Tanggal</label>
            <input type="date" id="date_start" class="form-control" value="<?= htmlspecialchars($defaultDate) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold">s/d</label>
            <input type="date" id="date_end" class="form-control" value="<?= htmlspecialchars($defaultDate) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold d-block">Rincian</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="report_type" id="report_detail" value="detail" checked>
              <label class="form-check-label" for="report_detail">Rincian Detail</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="report_type" id="report_total" value="total">
              <label class="form-check-label" for="report_total">Rincian Total</label>
            </div>
          </div>
        </div>

        <div class="row g-3 align-items-end mt-1">
          <div class="col-md-3">
            <label class="form-label fw-bold">Kanal</label>
            <select id="sale_channel" class="form-select">
              <option value="all">Sales &amp; Sales Online</option>
              <option value="sales">Sales</option>
              <option value="online">Sales Online</option>
            </select>
          </div>
          <div class="col-md-2">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="filter_type" id="filter_salesman" value="salesman" checked>
              <label class="form-check-label fw-bold" for="filter_salesman">Salesman</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="filter_type" id="filter_pelanggan" value="pelanggan">
              <label class="form-check-label fw-bold" for="filter_pelanggan">Pelanggan</label>
            </div>
          </div>
          <div class="col-md-3 filter-select-group" id="salesman_filter_group">
            <label class="form-label">Pilih Salesman</label>
            <select id="salesman_id" class="form-select">
              <option value="">Semua salesman</option>
              <?php foreach ($salesmen as $salesman): ?>
                <option value="<?= (int)$salesman['id'] ?>">
                  <?= htmlspecialchars($salesman['firstname'] ?: $salesman['username']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 filter-select-group d-none" id="pelanggan_filter_group">
            <label class="form-label">Pilih Pelanggan</label>
            <select id="pelanggan_id" class="form-select">
              <option value="">Semua pelanggan</option>
              <?php foreach ($pelanggan as $customer): ?>
                <option value="<?= (int)$customer['id'] ?>">
                  <?= htmlspecialchars(trim(($customer['kode'] ? $customer['kode'] . ' - ' : '') . $customer['nama'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
          <div class="text-muted fs-8">Kosongkan pilihan untuk melihat semua.</div>
          <div class="d-flex gap-2">
            <button type="button" id="btn_load_sales_report" class="btn btn-primary">
              <i class="bi bi-check-circle"></i> OK
            </button>
            <button type="button" id="btn_reset_sales_report" class="btn btn-light-danger">
              <i class="bi bi-x-circle"></i> Batal
            </button>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-5">
        <div class="col-md-3">
          <div class="sales-summary-box">
            <div class="sales-summary-label">Transaksi</div>
            <div class="sales-summary-value" id="summary_transactions">0</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="sales-summary-box">
            <div class="sales-summary-label">Baris Laporan</div>
            <div class="sales-summary-value" id="summary_rows">0</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="sales-summary-box">
            <div class="sales-summary-label">Total Qty</div>
            <div class="sales-summary-value" id="summary_qty">0</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="sales-summary-box">
            <div class="sales-summary-label">Total Penjualan</div>
            <div class="sales-summary-value" id="summary_sales">Rp 0</div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table id="sales_report_table" class="table table-striped table-bordered align-middle w-100"></table>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  let salesReportTable = null;
  let currentRows = [];
  let currentSummary = {};

  const company = {
    name: <?= json_encode($companyName) ?>,
    address: <?= json_encode($companyAddress) ?>,
    contact: <?= json_encode($companyContact) ?>,
    logo: <?= json_encode($companyLogo) ?>
  };

  const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  const plainMoney = value => Number(value || 0).toLocaleString('id-ID');
  const number = value => Number(value || 0).toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  });
  const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
  const select2Options = {
    width: '100%',
    placeholder: 'Semua',
    allowClear: true
  };

  function initSelect2($select) {
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    $select.select2(select2Options);
  }

  initSelect2($('#salesman_id'));
  initSelect2($('#pelanggan_id'));

  function currentFilterType() {
    return $('input[name="filter_type"]:checked').val() || 'salesman';
  }

  function currentReportType() {
    return $('input[name="report_type"]:checked').val() || 'detail';
  }

  function syncFilterInputs() {
    const type = currentFilterType();
    const useSalesman = type === 'salesman';

    if (useSalesman) {
      $('#pelanggan_id').val(null);
      $('#pelanggan_filter_group').addClass('d-none');
      $('#salesman_filter_group').removeClass('d-none');
      $('#salesman_id').prop('disabled', false);
      $('#pelanggan_id').prop('disabled', true);
    } else {
      $('#salesman_id').val(null);
      $('#salesman_filter_group').addClass('d-none');
      $('#pelanggan_filter_group').removeClass('d-none');
      $('#salesman_id').prop('disabled', true);
      $('#pelanggan_id').prop('disabled', false);
    }

    initSelect2($('#salesman_id'));
    initSelect2($('#pelanggan_id'));
  }

  function setSummary(summary) {
    summary = summary || {};
    $('#summary_transactions').text(Number(summary.jumlah_transaksi || 0).toLocaleString('id-ID'));
    $('#summary_rows').text(Number(summary.jumlah_baris || 0).toLocaleString('id-ID'));
    $('#summary_qty').text(number(summary.total_qty || 0));
    $('#summary_sales').text(money(summary.total_penjualan || 0));
  }

  function buildColumns() {
    if (currentReportType() === 'total') {
      return [
        { title: 'No', data: null, className: 'text-center', render: (data, type, row, meta) => meta.row + 1 },
        { title: 'Tanggal', data: 'tanggal', className: 'text-center', render: data => escapeHtml(data || '-') },
        { title: 'No Faktur', data: 'sale_invoice', render: (data, type, row) => escapeHtml(row.sale_nofak || data || '-') },
        { title: 'Kanal', data: 'sale_channel', render: data => data === 'online' ? 'Sales Online' : 'Sales' },
        { title: 'Pelanggan', data: 'pelanggan', render: data => escapeHtml(data || '-') },
        { title: 'Salesman', data: 'sales_name', render: data => escapeHtml(data || '-') },
        { title: 'Total Penjualan', data: 'total_penjualan', className: 'text-end fw-bold', render: data => money(data) }
      ];
    }

    return [
      { title: 'No', data: null, className: 'text-center', render: (data, type, row, meta) => meta.row + 1 },
      { title: 'Tanggal', data: 'tanggal', className: 'text-center', render: data => escapeHtml(data || '-') },
      { title: 'Invoice', data: 'sale_invoice', render: data => escapeHtml(data || '-') },
      { title: 'Kanal', data: 'sale_channel', render: data => data === 'online' ? 'Sales Online' : 'Sales' },
      { title: 'Salesman', data: 'sales_name', render: data => escapeHtml(data || '-') },
      { title: 'Pelanggan', data: 'pelanggan', render: data => escapeHtml(data || '-') },
      { title: 'Barcode', data: 'barcode', render: data => escapeHtml(data || '-') },
      { title: 'Nama Barang', data: 'nama_produk', render: data => escapeHtml(data || '-') },
      { title: 'Qty', data: 'qty', className: 'text-end', render: data => number(data) },
      { title: 'Harga', data: 'harga', className: 'text-end', render: data => money(data) },
      { title: 'Diskon %', data: 'diskon', className: 'text-end', render: data => number(data) },
      { title: 'Subtotal', data: 'subtotal', className: 'text-end fw-bold', render: data => money(data) }
    ];
  }

  function getSelectedText(selector, fallback) {
    const text = ($(selector).find('option:selected').text() || '').trim();
    return text && text.toLowerCase() !== 'semua' ? text : fallback;
  }

  function getReportTitle() {
    const detailText = currentReportType() === 'detail' ? 'Rincian Detail' : 'Rincian Total';
    const filterText = currentFilterType() === 'pelanggan' ? 'Per Pelanggan' : 'Per Salesman';
    return `Laporan Penjualan Sales ${detailText} ${filterText}`;
  }

  function getPrintDateTime() {
    const now = new Date();
    return {
      date: now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }),
      time: now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':')
    };
  }

  function printHeaderHtml() {
    const printedAt = getPrintDateTime();
    const filterName = currentFilterType() === 'pelanggan'
      ? getSelectedText('#pelanggan_id', 'Semua Pelanggan')
      : getSelectedText('#salesman_id', 'Semua Salesman');

    return `
      <div class="report-header">
        <div class="company-block">
          ${company.logo ? `<img src="${escapeHtml(company.logo)}" class="company-logo" alt="">` : ''}
          <div>
            <div class="company-name">${escapeHtml(company.name)}</div>
            ${company.address ? `<div class="company-small">${escapeHtml(company.address)}</div>` : ''}
            ${company.contact ? `<div class="company-small">${escapeHtml(company.contact)}</div>` : ''}
          </div>
        </div>
        <div class="print-meta">
          <div>Hal. 1</div>
          <div>Tgl. Cetak&nbsp;&nbsp; ${escapeHtml(printedAt.date)}</div>
          <div>Jam&nbsp;&nbsp; ${escapeHtml(printedAt.time)}</div>
        </div>
      </div>
      <div class="report-title">${escapeHtml(getReportTitle())}</div>
      <div class="report-period">
        Periode Tgl. ${escapeHtml($('#date_start').val() || '-')}
        &nbsp;&nbsp; s/d Tgl. ${escapeHtml($('#date_end').val() || '-')}
        &nbsp;&nbsp; ${escapeHtml(filterName)}
      </div>
    `;
  }

  function buildDetailReportRows(rows) {
    const groups = {};
    rows.forEach(row => {
      const key = row.sale_invoice || '-';
      if (!groups[key]) groups[key] = [];
      groups[key].push(row);
    });

    let body = '';
    Object.keys(groups).forEach(invoice => {
      const items = groups[invoice];
      const first = items[0] || {};
      const faktur = first.sale_nofak || first.sale_invoice || '-';
      let total = 0;

      body += `
        <tr class="section-row">
          <td>${escapeHtml(first.tanggal || '-')}</td>
          <td>${escapeHtml(faktur)}</td>
          <td colspan="2">NAMA BARANG</td>
          <td class="right">QUANTITY</td>
          <td class="right">HARGA</td>
          <td class="right">JUMLAH</td>
        </tr>
      `;

      items.forEach(item => {
        total += Number(item.subtotal || 0);
        body += `
          <tr>
            <td></td>
            <td>${escapeHtml(item.barcode || '')}</td>
            <td></td>
            <td>${escapeHtml(item.nama_produk || '-')}</td>
            <td class="right">${number(item.qty)}</td>
            <td class="right">${plainMoney(item.harga)}</td>
            <td class="right">${plainMoney(item.subtotal)}</td>
          </tr>
        `;
      });

      body += `
        <tr class="group-total-row">
          <td colspan="3">Nama&nbsp; ${escapeHtml(first.pelanggan || '-')} ${first.pelanggan_kode ? ' - ' + escapeHtml(first.pelanggan_kode) : ''}</td>
          <td colspan="2">Dibuat Oleh : ${escapeHtml(first.sales_name || '-')}</td>
          <td class="right">TOTAL</td>
          <td class="right">${plainMoney(total)}</td>
        </tr>
      `;
    });

    return body || '<tr><td colspan="7" class="center muted">Tidak ada data penjualan sales</td></tr>';
  }

  function buildTotalReportRows(rows) {
    const groupBySales = currentFilterType() === 'salesman';
    const groups = {};
    rows.forEach(row => {
      const key = groupBySales ? (row.sales_name || 'Tanpa Salesman') : (row.pelanggan || 'Tanpa Pelanggan');
      if (!groups[key]) groups[key] = [];
      groups[key].push(row);
    });

    let body = '';
    Object.keys(groups).forEach(groupName => {
      let groupTotal = 0;
      body += `<tr class="section-row"><td colspan="4">${escapeHtml(groupBySales ? 'Salesman' : 'Pelanggan')} : ${escapeHtml(groupName)}</td><td class="right">JUMLAH</td></tr>`;
      groups[groupName].forEach(row => {
        const amount = Number(row.total_penjualan || 0);
        groupTotal += amount;
        body += `
          <tr>
            <td>${escapeHtml(row.tanggal || '-')}</td>
            <td>${escapeHtml(row.sale_nofak || row.sale_invoice || '-')}</td>
            <td>-</td>
            <td>${escapeHtml(groupBySales ? (row.pelanggan || '-') : (row.sales_name || '-'))}${row.pelanggan_kode && groupBySales ? ' - ' + escapeHtml(row.pelanggan_kode) : ''}</td>
            <td class="right">${plainMoney(amount)}</td>
          </tr>
        `;
      });
      body += `<tr class="group-total-row"><td colspan="4" class="right">TOTAL ${escapeHtml(groupName)}</td><td class="right">${plainMoney(groupTotal)}</td></tr>`;
    });

    return body || '<tr><td colspan="5" class="center muted">Tidak ada data penjualan sales</td></tr>';
  }

  function buildPrintHtml() {
    const isDetail = currentReportType() === 'detail';
    const colgroup = isDetail
      ? '<col style="width:11%"><col style="width:14%"><col style="width:8%"><col style="width:32%"><col style="width:11%"><col style="width:11%"><col style="width:13%">'
      : '<col style="width:13%"><col style="width:18%"><col style="width:4%"><col style="width:45%"><col style="width:20%">';
    const head = isDetail
      ? '<tr><th>TANGGAL</th><th>NO. NOTA</th><th></th><th>NAMA BARANG</th><th class="right">QUANTITY</th><th class="right">HARGA</th><th class="right">JUMLAH</th></tr>'
      : '<tr><th>TANGGAL</th><th>NO.FAKTUR</th><th></th><th>NAMA</th><th class="right">JUMLAH</th></tr>';
    const rows = isDetail ? buildDetailReportRows(currentRows) : buildTotalReportRows(currentRows);
    const colspan = isDetail ? 6 : 4;

    return `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>${escapeHtml(getReportTitle())}</title>
        <style>
          @page { size: A4 portrait; margin: 12mm; }
          body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; }
          .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #222; padding-bottom: 6px; }
          .company-block { display: flex; gap: 12px; align-items: center; }
          .company-logo { width: 72px; max-height: 54px; object-fit: contain; }
          .company-name { font-size: 22px; font-weight: 700; line-height: 1.1; }
          .company-small { font-size: 11px; line-height: 1.35; }
          .print-meta { text-align: left; line-height: 1.55; min-width: 130px; }
          .report-title { font-size: 14px; font-weight: 700; margin-top: 8px; }
          .report-period { font-weight: 700; margin: 4px 0 8px; }
          table { width: 100%; border-collapse: collapse; table-layout: fixed; }
          th { border-top: 1px solid #222; border-bottom: 1px solid #222; padding: 4px 3px; text-align: left; }
          td { padding: 3px; vertical-align: top; border-bottom: 1px dotted #bbb; }
          .section-row td { font-weight: 700; border-top: 1px solid #222; border-bottom: 1px solid #222; }
          .group-total-row td { font-weight: 700; border-bottom: 1px solid #222; }
          .grand-total td { font-weight: 700; border-top: 1px solid #222; border-bottom: 1px solid #222; }
          .right { text-align: right; }
          .center { text-align: center; }
          .muted { color: #777; }
        </style>
      </head>
      <body>
        ${printHeaderHtml()}
        <table>
          <colgroup>${colgroup}</colgroup>
          <thead>${head}</thead>
          <tbody>
            ${rows}
            <tr class="grand-total">
              <td colspan="${colspan}" class="right">GRAND TOTAL</td>
              <td class="right">${plainMoney(currentSummary.total_penjualan || 0)}</td>
            </tr>
          </tbody>
        </table>
      </body>
      </html>
    `;
  }

  function printSalesReport() {
    const printWindow = window.open('', '_blank', 'width=1100,height=800');
    if (!printWindow) {
      alert_toast('Popup print diblokir browser', 'warning');
      return;
    }

    printWindow.document.open();
    printWindow.document.write(buildPrintHtml());
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
    }, 350);
  }

  function renderTable(rows) {
    currentRows = rows || [];

    if (salesReportTable) {
      salesReportTable.destroy();
      salesReportTable = null;
    }

    $('#sales_report_table').empty();
    salesReportTable = $('#sales_report_table').DataTable({
      data: rows || [],
      columns: buildColumns(),
      responsive: true,
      autoWidth: false,
      pageLength: 25,
      dom: 'Bfrtip',
      buttons: [
        'copy',
        'excel',
        {
          text: 'Print',
          action: printSalesReport
        }
      ],
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        zeroRecords: 'Tidak ada data penjualan sales',
        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
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
  }

  function loadSalesReport() {
    start_loader();
    $.ajax({
      url: '<?= base_url ?>classes/Laporan.php?f=get_laporan_sales',
      type: 'POST',
      dataType: 'json',
      data: {
        date_start: $('#date_start').val(),
        date_end: $('#date_end').val(),
        filter_type: currentFilterType(),
        report_type: currentReportType(),
        sale_channel: $('#sale_channel').val(),
        salesman_id: $('#salesman_id').val(),
        pelanggan_id: $('#pelanggan_id').val()
      },
      success(resp) {
        if (resp.status !== 'success') {
          alert_toast(resp.error || 'Gagal memuat laporan sales', 'danger');
          return;
        }

        currentSummary = resp.summary || {};
        setSummary(currentSummary);
        renderTable(resp.data || []);
      },
      error(xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Gagal memuat laporan sales';
        alert_toast(msg, 'danger');
      },
      complete() {
        end_loader();
      }
    });
  }

  $('input[name="filter_type"]').on('click change', function() {
    syncFilterInputs();
  });

  $('input[name="report_type"]').on('change', function() {
    loadSalesReport();
  });

  $('#sale_channel').on('change', loadSalesReport);

  $('#btn_load_sales_report').on('click', loadSalesReport);
  $('#btn_reset_sales_report').on('click', function() {
    const today = '<?= htmlspecialchars($defaultDate) ?>';
    $('#date_start, #date_end').val(today);
    $('#filter_salesman').prop('checked', true);
    $('#report_detail').prop('checked', true);
    $('#sale_channel').val('all');
    $('#salesman_id, #pelanggan_id').val(null).trigger('change');
    syncFilterInputs();
    loadSalesReport();
  });

  syncFilterInputs();
  loadSalesReport();
});
</script>
