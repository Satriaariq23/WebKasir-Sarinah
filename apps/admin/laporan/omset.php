<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0">Laporan Omset</h3>
      <div class="fw-bold">Total: <span id="total_omset">Rp 0</span></div>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-md-2">
          <label class="form-label">Dari</label>
          <input type="date" id="date_start" class="form-control" value="<?= date('Y-m-01') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Sampai</label>
          <input type="date" id="date_end" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Sumber</label>
          <select id="source" class="form-select">
            <option value="sales">Sales</option>
            <option value="online">Sales Online</option>
            <option value="kasir">Kasir</option>
            <option value="all">Semua</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Mode</label>
          <select id="mode" class="form-select">
            <option value="per_sales">Per Sales</option>
            <option value="per_penjualan">Per Penjualan</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" id="btn_filter">Filter</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered" id="omset_table">
          <thead></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  const formatRupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  const escapeHtml = value => $('<div>').text(value == null ? '' : String(value)).html();

  function renderTable(rows) {
    const mode = $('#mode').val();
    const $thead = $('#omset_table thead');
    const $tbody = $('#omset_table tbody');
    $tbody.empty();

    if (mode === 'per_penjualan') {
      $thead.html('<tr><th>No</th><th>Tanggal</th><th>Invoice</th><th>Kanal</th><th>Sales</th><th>Pelanggan</th><th class="text-end">Omset</th></tr>');
      rows.forEach((row, index) => {
        $tbody.append(`<tr>
          <td>${index + 1}</td>
          <td>${escapeHtml(row.tanggal || '-')}</td>
          <td>${escapeHtml(row.sale_invoice || '-')}</td>
          <td>${escapeHtml(row.channel_name || '-')}</td>
          <td>${escapeHtml(row.sales_name || '-')}</td>
          <td>${escapeHtml(row.pelanggan || '-')}</td>
          <td class="text-end">${formatRupiah(row.omset)}</td>
        </tr>`);
      });
    } else {
      $thead.html('<tr><th>No</th><th>Sales</th><th class="text-end">Jumlah Transaksi</th><th class="text-end">Omset</th></tr>');
      rows.forEach((row, index) => {
        $tbody.append(`<tr>
          <td>${index + 1}</td>
          <td>${escapeHtml(row.sales_name || '-')}</td>
          <td class="text-end">${Number(row.jumlah_transaksi || 0).toLocaleString('id-ID')}</td>
          <td class="text-end">${formatRupiah(row.omset)}</td>
        </tr>`);
      });
    }
  }

  function loadOmset() {
    $.ajax({
      url: '<?= base_url ?>classes/Laporan.php?f=get_omset_penjualan',
      method: 'POST',
      dataType: 'json',
      data: {
        date_start: $('#date_start').val(),
        date_end: $('#date_end').val(),
        source: $('#source').val(),
        mode: $('#mode').val()
      },
      success(resp) {
        if (resp.status !== 'success') {
          alert_toast(resp.error || 'Gagal memuat laporan omset', 'danger');
          return;
        }
        $('#total_omset').text(formatRupiah(resp.summary.total_omset));
        renderTable(resp.data || []);
      }
    });
  }

  $('#btn_filter, #mode, #source').on('click change', loadOmset);
  loadOmset();
});
</script>
