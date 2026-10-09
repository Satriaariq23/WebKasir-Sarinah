<style>
  .finance-hub {
    --finance-accent: #181c32;
    --finance-soft: #f1f4f8;
    padding: 18px;
  }

  .finance-hub-hero {
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
    background: var(--finance-accent);
    box-shadow: 0 12px 30px rgba(24, 28, 50, .14);
  }

  .finance-hub-hero::after {
    content: none;
  }

  .finance-hub-title,
  .finance-hub-description,
  .finance-hub-icon {
    position: relative;
    z-index: 1;
  }

  .finance-hub-title {
    margin: 0 0 5px;
    color: #fff !important;
    font-size: clamp(1.25rem, 2vw, 1.75rem);
    font-weight: 800;
  }

  .finance-hub-description {
    margin: 0;
    color: rgba(255, 255, 255, .78);
  }

  .finance-hub-icon {
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

  .finance-hub > .content-wrapper { padding: 0 !important; }
  .finance-hub .card-xl-stretch { border: 0; border-radius: 16px; box-shadow: 0 8px 28px rgba(15, 23, 42, .07); }
  .finance-hub .table-responsive { border-radius: 12px; }

  @media (max-width: 767.98px) {
    .finance-hub { padding: 10px; }
    .finance-hub-hero { padding: 18px; border-radius: 14px; }
    .finance-hub-icon { width: 48px; height: 48px; flex-basis: 48px; }
  }
</style>

<div class="content-wrapper finance-hub" id="hutang_finance_hub">
  <header class="finance-hub-hero">
    <div>
      <h2 class="finance-hub-title">Manajemen Hutang Supplier</h2>
      <p class="finance-hub-description">Pantau saldo hutang dan catat seluruh pembayaran supplier dari satu halaman.</p>
    </div>
    <div class="finance-hub-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></div>
  </header>

<div class="content-wrapper py-3 px-3">
  <div class="row">
    <div class="col-md-12 grid-margin stretch-card">
      <div class="card card-xl-stretch mb-5 mb-xl-8">
        <div class="card-header align-items-center border-0 mt-4">
          <h3 class="card-title align-items-start flex-column">
            <span class="text-dark fw-bolder">Hutang Supplier</span>
          </h3>
          <div class="card-toolbar">
            <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
              <?php if ($_settings->userdata('level_id') < 2): ?>
                <a class="btn btn-sm btn-success" href="<?php echo base_url ?>admin/?page=pembelian" title="Input hutang melalui pembelian kredit">
                  <i class="bi bi-plus-lg"></i> Input Hutang
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-12" id="msg"></div>
        </div>

        <div class="separator"></div>
        <div class="card mb-2 mb-xxl-8">
          <div class="card-body pt-2 pb-0">
            <div class="table-responsive">
              <table id="table_hutang" class="table table-hover table-bordered align-middle w-100">
                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                  <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Invoice</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Sisa Hutang</th>
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
    </div>
  </div>
</div>

  <?php include __DIR__ . '/bayar/index.php'; ?>
</div>

<script>
  $(document).ready(function() {
    load_hutang_data();

    $(document).on('click', '.btn-detail', function() {
      openHutangSettlement($(this).data('id'));
    });
  });

  function load_hutang_data() {
    $('#table_hutang').DataTable({
      destroy: true,
      processing: true,
      serverSide: true,
      stateSave: true,
      ajax: {
        url: '../classes/HutangSupplier.php?f=get_data_hutang',
        type: 'POST',
        error: function(xhr) {
          const msg = xhr.responseJSON?.error || xhr.responseJSON?.msg || xhr.responseText || 'Gagal memuat data hutang.';
          console.error('Hutang DataTables error:', msg);
          alert('Gagal memuat data hutang: ' + msg);
        }
      },
      columns: [{
          data: 'no',
          className: 'text-center'
        },
        {
          data: 'tanggal_hutang'
        },
        {
          data: 'no_hutang'
        },
        {
          data: 'nama_supplier'
        },
        {
          data: 'total_hutang',
          className: 'text-end'
        },
        {
          data: 'sisa_hutang',
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
          orderable: false
        }
      ],
      columnDefs: [{
        targets: 8,
        render: function(data, type, row) {
          const canPay = String(row.status || '').toUpperCase() !== 'LUNAS';
          const label = canPay ? 'Bayar dan lihat riwayat' : 'Lihat riwayat pembayaran';
          const icon = canPay ? 'bi-cash-stack' : 'bi-clock-history';
          return `<button type="button" class="btn btn-sm btn-primary btn-detail" data-id="${data}" title="${label}"><i class="bi ${icon}"></i></button>`;
        }
      }]
    });
  }
</script>
