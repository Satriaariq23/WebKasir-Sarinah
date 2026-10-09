<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header">
      <h3>Laporan Laba Kotor</h3>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label>Filter Tanggal</label>
        <input type="date" id="filter-date" class="form-control" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary mt-2" onclick="load_laba_kotor()">Filter</button>
      </div>
      <table id="table_laba" class="table table-striped">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Invoice</th>
            <th>Pendapatan</th>
            <th>HPP</th>
            <th>Laba</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

<script>
  let table_laba;

  function load_laba_kotor() {
    const filter_date = $('#filter-date').val();

    if (table_laba) table_laba.destroy();

    table_laba = $('#table_laba').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: "<?= base_url ?>classes/Laporan.php?f=get_laba_kotor",
        type: "POST",
        data: {
          filter_date: filter_date
        }
      },
      columns: [{
          data: 'no'
        },
        {
          data: 'tanggal_penjualan'
        },
        {
          data: 'no_invoice'
        },
        {
          data: 'total_penjualan',
          render: d => 'Rp ' + parseFloat(d).toLocaleString('id-ID')
        },
        {
          data: 'total_hpp',
          render: d => 'Rp ' + parseFloat(d).toLocaleString('id-ID')
        },
        {
          data: 'laba',
          render: d => '<b>Rp ' + parseFloat(d).toLocaleString('id-ID') + '</b>'
        }
      ]
    });
  }

  $(function() {
    load_laba_kotor();
  });
</script>