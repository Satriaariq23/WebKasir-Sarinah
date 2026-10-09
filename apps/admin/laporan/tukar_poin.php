<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header">
      <h3>Laporan Penukaran Poin Hadiah</h3>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label>Filter Tanggal Awal</label>
        <input type="date" id="filter-start" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
        <label class="mt-2">Filter Tanggal Akhir</label>
        <input type="date" id="filter-end" class="form-control" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary mt-2" onclick="load_tukar_poin()">Filter</button>
      </div>
      <table id="table_tukar" class="table table-striped">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th>Hadiah</th>
            <th>Poin Digunakan</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

<script>
  let table_tukar;

  function load_tukar_poin() {
    const start = $('#filter-start').val();
    const end = $('#filter-end').val();

    if (table_tukar) table_tukar.destroy();

    table_tukar = $('#table_tukar').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: "<?= base_url ?>classes/Laporan.php?f=get_tukar_poin",
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
          data: 'tanggal'
        },
        {
          data: 'nama_pelanggan'
        },
        {
          data: 'nama_hadiah'
        },
        {
          data: 'poin_digunakan'
        }
      ]
    });
  }

  $(function() {
    load_tukar_poin();
  });
</script>