<?php
$salesProfitPercent = $_settings->info('sales_profit_percent');
if ($salesProfitPercent === false || $salesProfitPercent === '') {
  $salesProfitPercent = 100;
}
?>
<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header">
      <h3>Profit Penjualan</h3>
    </div>
    <div class="card-body">
      <div class="alert alert-light-primary mb-4">
        <strong>Persentase default profit sales: <?= e($salesProfitPercent) ?>%</strong>
        <div class="small mt-1">
          Profit kantor = omzet bersih - harga beli. Profit sales =
          (omzet bersih - New Modal) x persentase masing-masing sales.
          Nilai default hanya dipakai jika persentase sales belum disetel.
          Transaksi VOID/DELETED tidak dihitung.
        </div>
      </div>
      <div class="table-responsive">
        <table id="table_profit" class="table table-striped w-100">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>Invoice</th>
              <th>Sales</th>
              <th>Persentase</th>
              <th>Omzet</th>
              <th>HPP</th>
              <th>Profit Kantor</th>
              <th>Profit Sales</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  $(function() {
    let table_profit;

    function load_profit_penjualan() {
      if (table_profit) table_profit.destroy();

      table_profit = $('#table_profit').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        ajax: {
          url: '<?= base_url ?>classes/Laporan.php?f=get_profit_penjualan',
          type: 'POST',
          data: function(d) {
            d.csrf_token = '<?= csrf_token() ?>';
          }
        },
        columns: [{
            data: 'no'
          },
          {
            data: 'sale_date'
          },
          {
            data: 'sale_invoice'
          },
          {
            data: 'sales_name'
          },
          {
            data: 'sales_profit_percent',
            render: d => parseFloat(d || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%'
          },
          {
            data: 'sale_total',
            render: d => 'Rp ' + parseFloat(d).toLocaleString('id-ID')
          },
          {
            data: 'total_hpp',
            render: d => 'Rp ' + parseFloat(d).toLocaleString('id-ID')
          },
          {
            data: 'profit_kantor',
            render: d => '<b>Rp ' + parseFloat(d).toLocaleString('id-ID') + '</b>'
          },
          {
            data: 'profit_sales',
            render: d => '<b>Rp ' + parseFloat(d).toLocaleString('id-ID') + '</b>'
          }
        ]
      });
    }

    load_profit_penjualan();
  });
</script>
