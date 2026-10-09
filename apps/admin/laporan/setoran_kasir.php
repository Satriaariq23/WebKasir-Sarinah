<?php
$kasirOptions = [];

if (isset($conn) && $conn instanceof mysqli) {
  $queries = [
    "SELECT id, firstname, username FROM users WHERE status = 1 AND level_id = 2 ORDER BY firstname ASC",
    "SELECT id, firstname, username FROM users WHERE status = 1 ORDER BY level_id ASC, firstname ASC"
  ];

  foreach ($queries as $sql) {
    $result = $conn->query($sql);
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $kasirOptions[] = $row;
      }
      $result->free();
    }

    if (!empty($kasirOptions)) {
      break;
    }
  }
}
?>

<div class="content-wrapper py-3 px-3">
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h3 class="mb-0">Setoran Kasir Harian</h3>
      <button type="button" class="btn btn-primary btn-sm" id="btn_tambah_setoran">
        <i class="bi bi-plus-circle me-1"></i> Tambah Setoran
      </button>
    </div>
    <div class="card-body">
      <div class="row g-2 align-items-end mb-4">
        <div class="col-md-4 col-lg-3">
          <label for="filter-date" class="form-label">Filter Tanggal</label>
          <input type="date" id="filter-date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-auto">
          <button class="btn btn-primary" id="btn_filter_setoran" type="button">
            <i class="bi bi-funnel me-1"></i> Filter
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="table_kasir" class="table table-hover align-middle">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>Kasir</th>
              <th>Jumlah Transaksi</th>
              <th>Total Penjualan</th>
              <th>Setoran</th>
              <th>Selisih</th>
              <th>Keterangan</th>
              <th>Aksi</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSetoranKasir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="form_setoran_kasir">
        <input type="hidden" name="setoran_id" id="setoran_id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal_setoran_title">Tambah Setoran Kasir</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="setoran_date" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="setoran_date" name="setoran_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label for="kasir_id" class="form-label">Kasir</label>
              <select class="form-select" id="kasir_id" name="kasir_id" required>
                <option value="">Pilih Kasir</option>
                <?php foreach ($kasirOptions as $kasir): ?>
                  <option value="<?= (int)$kasir['id'] ?>">
                    <?= e($kasir['firstname']) ?><?= !empty($kasir['username']) ? ' (' . e($kasir['username']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="total_transaksi_display" class="form-label">Total Penjualan Harian</label>
              <input type="hidden" id="total_transaksi" name="total_transaksi" value="0">
              <input type="hidden" id="jumlah_transaksi" name="jumlah_transaksi" value="0">
              <input type="text" class="form-control" id="total_transaksi_display" value="Rp 0" readonly>
            </div>
            <div class="col-md-6">
              <label for="jumlah_setoran" class="form-label">Jumlah Setoran</label>
              <input type="number" class="form-control" id="jumlah_setoran" name="jumlah_setoran" min="0" step="0.01" required>
            </div>
            <div class="col-md-6">
              <label for="selisih_display" class="form-label">Selisih</label>
              <input type="text" class="form-control" id="selisih_display" value="Rp 0" readonly>
            </div>
            <div class="col-md-12">
              <label for="keterangan" class="form-label">Keterangan</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Opsional"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn_save_setoran">
            <i class="bi bi-save me-1"></i> Simpan Setoran
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  let table_kasir;
  let setoranRows = {};
  const laporanApi = "<?= base_url ?>classes/Laporan.php";

  function formatRupiah(value) {
    const number = Number(value || 0);
    return 'Rp ' + number.toLocaleString('id-ID', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    });
  }

  function formatSignedRupiah(value) {
    const number = Number(value || 0);
    if (number > 0) {
      return '+' + formatRupiah(number);
    }
    if (number < 0) {
      return '-' + formatRupiah(Math.abs(number));
    }
    return formatRupiah(0);
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function load_setoran_kasir() {
    const filter_date = $('#filter-date').val();

    if (table_kasir) {
      table_kasir.destroy();
      $('#table_kasir').empty().append(`
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Kasir</th>
            <th>Jumlah Transaksi</th>
            <th>Total Penjualan</th>
            <th>Setoran</th>
            <th>Selisih</th>
            <th>Keterangan</th>
            <th>Aksi</th>
          </tr>
        </thead>
      `);
    }

    table_kasir = $('#table_kasir').DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: {
        url: laporanApi + "?f=get_setoran_kasir",
        type: "POST",
        data: {
          filter_date: filter_date
        },
        dataSrc: function(json) {
          if (json.error || json.status === 'error') {
            alert_toast(json.msg || json.error || 'Gagal memuat data setoran', 'error');
            return [];
          }

          setoranRows = {};
          (json.data || []).forEach(row => {
            setoranRows[row.row_key] = row;
          });
          return json.data || [];
        }
      },
      columns: [{
          data: 'no',
          className: 'text-center'
        },
        {
          data: 'tanggal'
        },
        {
          data: 'nama_kasir'
        },
        {
          data: 'jumlah_transaksi',
          className: 'text-center',
          render: d => Number(d || 0).toLocaleString('id-ID')
        },
        {
          data: 'total_penjualan',
          className: 'text-end',
          render: d => formatRupiah(d)
        },
        {
          data: 'jumlah_setoran',
          className: 'text-end',
          render: function(d, type, row) {
            if (!Number(row.setoran_id || 0)) {
              return '<span class="text-muted">Belum setor</span>';
            }
            return formatRupiah(d);
          }
        },
        {
          data: 'selisih',
          className: 'text-end',
          render: function(d, type, row) {
            if (!Number(row.setoran_id || 0)) {
              return '<span class="text-muted">-</span>';
            }

            const value = Number(d || 0);
            const cls = value === 0 ? 'text-success' : (value < 0 ? 'text-danger' : 'text-warning');
            return `<span class="${cls} fw-bold">${formatSignedRupiah(value)}</span>`;
          }
        },
        {
          data: 'keterangan',
          render: d => d ? escapeHtml(d) : '<span class="text-muted">-</span>'
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function(data, type, row) {
            if (!Number(row.kasir_id || 0)) {
              return '<span class="text-muted">-</span>';
            }

            const isEdit = Number(row.setoran_id || 0) > 0;
            const btnClass = isEdit ? 'btn-warning' : 'btn-primary';
            const label = isEdit ? 'Edit' : 'Input';
            return `<button type="button" class="btn ${btnClass} btn-sm btn-edit-setoran" data-row-key="${escapeHtml(row.row_key)}">
                      <i class="bi bi-pencil-square me-1"></i>${label}
                    </button>`;
          }
        }
      ]
    });
  }

  function resetSetoranForm() {
    $('#form_setoran_kasir')[0].reset();
    $('#setoran_id').val('');
    $('#setoran_date').val($('#filter-date').val() || '<?= date('Y-m-d') ?>');
    $('#total_transaksi').val('0');
    $('#jumlah_transaksi').val('0');
    $('#total_transaksi_display').val(formatRupiah(0));
    $('#selisih_display').val(formatRupiah(0)).removeClass('text-danger text-warning text-success fw-bold');
    $('#modal_setoran_title').text('Tambah Setoran Kasir');
  }

  function updateSelisihForm() {
    const total = Number($('#total_transaksi').val() || 0);
    const setoran = Number($('#jumlah_setoran').val() || 0);
    const selisih = setoran - total;
    const $display = $('#selisih_display');

    $display
      .val(formatSignedRupiah(selisih))
      .removeClass('text-danger text-warning text-success fw-bold');

    if (selisih === 0) {
      $display.addClass('text-success fw-bold');
    } else if (selisih < 0) {
      $display.addClass('text-danger fw-bold');
    } else {
      $display.addClass('text-warning fw-bold');
    }
  }

  function loadSetoranSummary(prefillExisting = true) {
    const setoranDate = $('#setoran_date').val();
    const kasirId = $('#kasir_id').val();

    if (!setoranDate || !kasirId) {
      $('#total_transaksi').val('0');
      $('#jumlah_transaksi').val('0');
      $('#total_transaksi_display').val(formatRupiah(0));
      updateSelisihForm();
      return;
    }

    $('#total_transaksi_display').val('Memuat...');

    $.ajax({
      url: laporanApi + "?f=get_setoran_summary",
      type: "POST",
      dataType: "json",
      data: {
        setoran_date: setoranDate,
        kasir_id: kasirId
      },
      success: function(res) {
        if (res.status !== 'success') {
          alert_toast(res.msg || 'Gagal mengambil total transaksi', 'error');
          return;
        }

        const data = res.data || {};
        const totalTransaksi = Number(data.total_transaksi || 0);
        const jumlahTransaksi = Number(data.jumlah_transaksi || 0);

        $('#total_transaksi').val(totalTransaksi);
        $('#jumlah_transaksi').val(jumlahTransaksi);
        $('#total_transaksi_display').val(`${formatRupiah(totalTransaksi)} (${jumlahTransaksi.toLocaleString('id-ID')} transaksi)`);

        if (prefillExisting && Number(data.setoran_id || 0) > 0) {
          $('#setoran_id').val(data.setoran_id);
          $('#jumlah_setoran').val(Number(data.jumlah_setoran || 0));
          $('#keterangan').val(data.keterangan || '');
          $('#modal_setoran_title').text('Edit Setoran Kasir');
        }

        updateSelisihForm();
      },
      error: function() {
        alert_toast('Gagal mengambil total transaksi', 'error');
      }
    });
  }

  function openSetoranModal(row = null) {
    resetSetoranForm();

    if (row) {
      if (!$(`#kasir_id option[value="${row.kasir_id}"]`).length) {
        $('#kasir_id').append(`<option value="${row.kasir_id}">${escapeHtml(row.nama_kasir)}</option>`);
      }

      $('#setoran_id').val(row.setoran_id || '');
      $('#setoran_date').val(row.tanggal_raw || $('#filter-date').val());
      $('#kasir_id').val(row.kasir_id);
      $('#jumlah_setoran').val(Number(row.setoran_id || 0) ? Number(row.jumlah_setoran || 0) : Number(row.total_penjualan || 0));
      $('#keterangan').val(row.keterangan || '');
      $('#modal_setoran_title').text(Number(row.setoran_id || 0) ? 'Edit Setoran Kasir' : 'Input Setoran Kasir');
      $('#total_transaksi').val(Number(row.total_penjualan || 0));
      $('#jumlah_transaksi').val(Number(row.jumlah_transaksi || 0));
      $('#total_transaksi_display').val(`${formatRupiah(row.total_penjualan)} (${Number(row.jumlah_transaksi || 0).toLocaleString('id-ID')} transaksi)`);
      updateSelisihForm();
      loadSetoranSummary(false);
    }

    $('#modalSetoranKasir').modal('show');
  }

  function saveSetoranKasir() {
    const $btn = $('#btn_save_setoran');
    const defaultHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');

    $.ajax({
      url: laporanApi + "?f=save_setoran_kasir",
      type: "POST",
      data: $('#form_setoran_kasir').serialize(),
      dataType: "json",
      success: function(res) {
        if (res.status === 'success') {
          alert_toast(res.msg || 'Setoran berhasil disimpan', 'success');
          $('#modalSetoranKasir').modal('hide');
          load_setoran_kasir();
        } else {
          alert_toast(res.msg || res.error || 'Gagal menyimpan setoran', 'error');
        }
      },
      error: function() {
        alert_toast('Gagal menyimpan setoran', 'error');
      },
      complete: function() {
        $btn.prop('disabled', false).html(defaultHtml);
      }
    });
  }

  $(function() {
    load_setoran_kasir();

    $('#btn_filter_setoran').click(load_setoran_kasir);
    $('#filter-date').on('change', load_setoran_kasir);
    $('#btn_tambah_setoran').click(function() {
      openSetoranModal();
    });
    $('#setoran_date, #kasir_id').on('change', function() {
      loadSetoranSummary(true);
    });
    $('#jumlah_setoran').on('input', updateSelisihForm);
    $('#form_setoran_kasir').on('submit', function(e) {
      e.preventDefault();
      saveSetoranKasir();
    });
    $('#table_kasir').on('click', '.btn-edit-setoran', function() {
      const key = $(this).data('row-key');
      openSetoranModal(setoranRows[key]);
    });
  });
</script>
