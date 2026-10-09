<style>
  .receivable-hub {
    --receivable-accent: #181c32;
    --receivable-soft: #f1f4f8;
    padding: 18px;
  }

  .receivable-hub-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
    padding: 22px 24px;
    border-radius: 18px;
    color: #fff;
    background: var(--receivable-accent);
    box-shadow: 0 12px 30px rgba(24, 28, 50, .14);
  }

  .receivable-hub-hero::after {
    content: none;
  }

  .receivable-hub-title,
  .receivable-hub-description,
  .receivable-hub-icon {
    position: relative;
    z-index: 1;
  }

  .receivable-hub-title {
    margin: 0 0 5px;
    color: #fff !important;
    font-size: clamp(1.25rem, 2vw, 1.75rem);
    font-weight: 800;
  }

  .receivable-hub-description {
    margin: 0;
    color: rgba(255, 255, 255, .78);
  }

  .receivable-hub-icon {
    display: grid;
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 16px;
    color: #fff;
    background: transparent;
    font-size: 1.65rem;
  }

  .receivable-hub > .content-wrapper { padding: 0 !important; }
  .receivable-hub .card-xl-stretch { border: 0; border-radius: 16px; box-shadow: 0 8px 28px rgba(15, 23, 42, .07); }
  .receivable-hub .table-responsive { border-radius: 12px; }

  @media (max-width: 767.98px) {
    .receivable-hub { padding: 10px; }
    .receivable-hub-hero { padding: 18px; border-radius: 14px; }
    .receivable-hub-icon { width: 48px; height: 48px; flex-basis: 48px; }
  }
</style>

<div class="content-wrapper receivable-hub" id="piutang_finance_hub">
  <header class="receivable-hub-hero">
    <div>
      <h2 class="receivable-hub-title">Manajemen Piutang Pelanggan</h2>
      <p class="receivable-hub-description">Kelola saldo piutang dan catat penerimaan pelanggan tanpa berpindah halaman.</p>
    </div>
    <div class="receivable-hub-icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></div>
  </header>

<div class="content-wrapper py-3 px-3">
  <div class="card card-xl-stretch mb-5 mb-xl-8">
    <div class="card-header align-items-center border-0 mt-4">
      <h3 class="card-title align-items-start flex-column">
        <span class="text-dark fw-bolder">Piutang Pelanggan</span>
        <span class="text-muted mt-1 fw-bold fs-7">Daftar piutang pelanggan dari penjualan kredit atau input manual</span>
      </h3>
      <div class="card-toolbar">
        <button type="button" class="btn btn-sm btn-success" id="btn_input_piutang">
          <i class="bi bi-plus-lg"></i> Input Piutang
        </button>
      </div>
    </div>
    <div class="separator"></div>
    <div class="card-body">
      <div id="piutang_msg"></div>
      <div class="table-responsive">
        <table id="table_piutang" class="table table-hover table-bordered align-middle w-100">
          <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>Invoice</th>
              <th>Pelanggan</th>
              <th>Total</th>
              <th>Sisa Piutang</th>
              <th>Jatuh Tempo</th>
              <th>Status</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal_piutang" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="form_piutang" class="modal-content">
      <input type="hidden" name="id" id="piutang_id">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_piutang_title">Input Piutang Pelanggan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-12">
            <label class="form-label fw-bold">Pelanggan</label>
            <select name="pelanggan_id" id="piutang_pelanggan_id" class="form-select" data-placeholder="Pilih pelanggan..." required></select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Tanggal Piutang</label>
            <input type="date" name="tanggal_piutang" id="tanggal_piutang" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Tanggal Jatuh Tempo</label>
            <input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Total Piutang</label>
            <input type="number" name="total_piutang" id="total_piutang" class="form-control text-end" min="1" step="1" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Ref. Penjualan</label>
            <input type="text" name="ref_penjualan" id="ref_penjualan" class="form-control" placeholder="Opsional">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-bold">Keterangan</label>
            <textarea name="keterangan" id="keterangan" class="form-control" rows="3" placeholder="Opsional"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn_save_piutang">
          <i class="bi bi-save"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

  <?php include __DIR__ . '/terima/index.php'; ?>
</div>

<script>
  const piutangMasterApiUrl = "<?= base_url ?>classes/PiutangPelanggan.php";
  let piutangTable;

  $(function() {
    initPiutangForm();
    loadPiutangData();

    $('#btn_input_piutang').on('click', function() {
      openPiutangForm();
    });

    $('#form_piutang').on('submit', function(e) {
      e.preventDefault();
      savePiutang();
    });

    $(document).on('click', '.btn-piutang-settlement', function() {
      openPiutangSettlement($(this).data('id'));
    });
  });

  function initPiutangForm() {
    $('#piutang_pelanggan_id').select2({
      dropdownParent: $('#modal_piutang'),
      placeholder: 'Pilih pelanggan...',
      allowClear: true,
      ajax: {
        url: piutangMasterApiUrl + '?f=search_pelanggan',
        dataType: 'json',
        delay: 250,
        data: params => ({
          q: params.term || ''
        }),
        processResults: data => ({
          results: data || []
        })
      }
    });
  }

  function loadPiutangData() {
    piutangTable = $('#table_piutang').DataTable({
      destroy: true,
      processing: true,
      serverSide: true,
      stateSave: true,
      ajax: {
        url: piutangMasterApiUrl + "?f=get_data_piutang",
        type: "POST",
        error: function(xhr) {
          const msg = xhr.responseJSON?.error || xhr.responseJSON?.msg || xhr.responseText || 'Gagal memuat data piutang.';
          console.error('Piutang DataTables error:', msg);
          showPiutangMessage('Gagal memuat data piutang: ' + msg, 'danger');
        }
      },
      columns: [{
          data: 'no',
          className: 'text-center'
        },
        {
          data: 'tanggal_piutang'
        },
        {
          data: 'no_piutang'
        },
        {
          data: 'nama_pelanggan'
        },
        {
          data: 'total_piutang',
          className: 'text-end'
        },
        {
          data: 'sisa_piutang',
          className: 'text-end'
        },
        {
          data: 'jatuh_tempo'
        },
        {
          data: 'status_badge'
        },
        {
          data: 'id',
          className: 'text-center',
          orderable: false,
          render: function(data, type, row) {
            const isLunas = String(row.status || '').toUpperCase() === 'LUNAS';
            const label = isLunas ? 'Lihat riwayat penerimaan' : 'Terima pembayaran dan lihat riwayat';
            const icon = isLunas ? 'bi-clock-history' : 'bi-cash-stack';
            return `<button type="button" class="btn btn-sm btn-primary btn-piutang-settlement" data-id="${data}" title="${label}"><i class="bi ${icon}"></i></button>`;
          }
        }
      ],
      order: [
        [1, 'desc']
      ]
    });
  }

  function openPiutangForm() {
    $('#form_piutang')[0].reset();
    $('#piutang_id').val('');
    $('#piutang_pelanggan_id').empty().trigger('change');
    $('#modal_piutang_title').text('Input Piutang Pelanggan');
    $('#tanggal_piutang').val(todayInput());
    $('#tanggal_jatuh_tempo').val(addDaysInput(30));

    $('#modal_piutang').modal('show');
  }

  function savePiutang() {
    const $btn = $('#btn_save_piutang');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan');

    $.ajax({
      url: piutangMasterApiUrl + '?f=save_piutang',
      type: 'POST',
      dataType: 'json',
      data: $('#form_piutang').serialize(),
      success: function(resp) {
        if (resp.status === 'success') {
          $('#modal_piutang').modal('hide');
          showPiutangMessage(resp.msg || 'Piutang berhasil disimpan', 'success');
          reloadPiutangTable();
        } else {
          showPiutangMessage(resp.msg || 'Gagal menyimpan piutang', 'danger');
        }
      },
      error: function(xhr) {
        const msg = xhr.responseJSON?.msg || xhr.responseText || 'Gagal menyimpan piutang';
        showPiutangMessage(msg, 'danger');
      },
      complete: function() {
        $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Simpan');
      }
    });
  }

  function reloadPiutangTable() {
    if (piutangTable) {
      piutangTable.ajax.reload(null, false);
    }
  }

  function showPiutangMessage(msg, type = 'primary') {
    $('#piutang_msg').html(`
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${escapePiutangHtml(msg)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
      </div>
    `);
  }

  function escapePiutangHtml(text) {
    return $('<div>').text(text || '').html();
  }

  function todayInput() {
    return dateToInput(new Date());
  }

  function addDaysInput(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return dateToInput(date);
  }

  function dateToInput(date) {
    const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return localDate.toISOString().slice(0, 10);
  }

</script>
