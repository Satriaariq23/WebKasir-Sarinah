<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header">
      <h3>Produk Terlaris</h3>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label>Filter Tanggal Awal</label>
        <input type="date" id="filter-start" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
        <label class="mt-2">Filter Tanggal Akhir</label>
        <input type="date" id="filter-end" class="form-control" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary mt-2" onclick="load_produk_terlaris()">Filter</button>
      </div>
      <table id="table_terlaris" class="table table-striped">
        <thead>
          <tr>
            <th>No</th>
            <th>Produk</th>
            <th>Jumlah Terjual</th>
            <th>Total Penjualan</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

<script>
  let table_terlaris;

  function load_produk_terlaris() {
    const start = $('#filter-start').val();
    const end = $('#filter-end').val();

    if (table_terlaris) table_terlaris.destroy();

    table_terlaris = $('#table_terlaris').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: "<?= base_url ?>classes/Laporan.php?f=get_produk_terlaris",
        type: "POST",
        data: {
          start_date: start,
          end_date: end
        }
      },
      columns: [{
          data: 'no'
        },
        {
          data: 'nama_barang'
        },
        {
          data: 'total_qty'
        },
        {
          data: 'total_penjualan',
          render: d => 'Rp ' + parseFloat(d).toLocaleString('id-ID')
        }
      ]
    });
  }

  $(function() {
    load_produk_terlaris();
  });
</script>