<div class="content-wrapper py-3 px-3">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-xl-stretch mb-5 mb-xl-8">
        <div class="card-header align-items-center border-0 mt-4">
          <h3 class="card-title align-items-start flex-column">
            <span class="text-dark fw-bolder">Cetak Member Card</span>
            <span class="text-muted mt-1 fw-bold fs-7">Kelola dan cetak kartu member pelanggan</span>
          </h3>
        </div>
        <div class="separator"></div>

        <!-- Filter Section -->
        <div class="card-body pb-3">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label">🔍 Cari Nama/Kode</label>
              <input type="text" id="search-input" class="form-control" placeholder="Ketik nama atau kode member..." onkeyup="debounce_search()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipe Member</label>
              <select id="filter-type" class="form-control" onchange="load_member_cards()">
                <option value="">Semua Tipe</option>
                <option value="PELANGGAN">Pelanggan (Sales)</option>
                <option value="MEMBER">Member (Retail)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">&nbsp;</label>
            </div>
          </div>
        </div>

        <!-- Member Cards Grid -->
        <div class="card-body pt-0">
          <!-- Pagination Info Top -->
          <div class="row mb-3">
            <div class="col-md-12">
              <small class="text-muted" id="pagination-info">Showing 0 to 0 of 0 entries</small>
            </div>
          </div>

          <div id="cards-container" class="row g-4">
            <!-- Cards akan dimuat di sini -->
          </div>

          <!-- Pagination Controls Bottom -->
          <div class="row mt-4">
            <div class="col-md-6">
              <small class="text-muted" id="pagination-info-bottom">Showing 0 to 0 of 0 entries</small>
            </div>
            <div class="col-md-6 text-end">
              <button class="btn btn-sm btn-outline-secondary" id="btn-prev" onclick="prev_page()" style="display: none;">
                <i class="bi bi-chevron-left"></i> Sebelumnya
              </button>
              <button class="btn btn-sm btn-outline-primary" id="btn-next" onclick="next_page()" style="display: none;">
                Berikutnya <i class="bi bi-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Print Preview -->
<div class="modal fade" id="modal-print-preview" tabindex="-1" role="dialog" aria-labelledby="modal-print-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-print-label">Preview Kartu Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="print-preview-content" style="background: #f5f5f5; padding: 20px; text-align: center;">
          <!-- Preview akan dimuat di sini -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" onclick="print_selected_card()">
          <i class="bi bi-printer"></i> Cetak
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .member-card {
    position: relative;
    width: 100%;
    height: 250px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    overflow: hidden;
  }

  .member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  }

  .member-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
  }

  .member-card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
  }

  .member-card-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .member-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .member-card-logo {
    font-size: 24px;
    font-weight: bold;
    letter-spacing: 2px;
  }

  .member-card-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .member-card-name {
    font-size: 18px;
    font-weight: bold;
    margin: 10px 0 5px 0;
    max-height: 45px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .member-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 12px;
  }

  .member-card-code {
    font-weight: bold;
    font-size: 14px;
  }

  .member-card-points {
    text-align: right;
  }

  .member-card-points-value {
    font-size: 18px;
    font-weight: bold;
  }

  .member-card-buttons {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    display: flex;
    gap: 5px;
  }

  .member-card-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    font-size: 14px;
  }

  .member-card-btn:hover {
    background: rgba(255, 255, 255, 0.4);
  }

  @media print {
    body {
      background: white;
    }

    .content-wrapper,
    .card-header,
    .separator,
    #modal-print-preview .modal-header,
    #modal-print-preview .modal-footer,
    .member-card-buttons {
      display: none !important;
    }

    .member-card {
      page-break-inside: avoid;
      margin-bottom: 20px;
      width: 100%;
      max-width: 500px;
    }
  }
</style>

<script>
  let currentCards = [];
  let currentStart = 0;
  let currentFilters = {
    search: '',
    customerType: ''
  };
  let totalRecords = 0;
  const pageSize = 10;
  let searchTimeout;

  function debounce_search() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      currentStart = 0;
      load_member_cards();
    }, 300);
  }

  function load_member_cards() {
    const searchQuery = $('#search-input').val().trim() || '';
    const customerType = $('#filter-type').val() || '';

    // Update filter tracking
    currentFilters.search = searchQuery;
    currentFilters.customerType = customerType;

    $.ajax({
      url: "<?= base_url ?>classes/Laporan.php?f=get_member_cards",
      type: "POST",
      data: {
        search: searchQuery,
        customer_type: customerType,
        start: currentStart,
        length: pageSize
      },
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success' && response.data) {
          currentCards = response.data;
          totalRecords = response.total;
          display_member_cards(response.data);
          update_pagination_info();
        } else {
          $('#cards-container').html('<div class="col-md-12"><p class="text-center text-muted">Tidak ada data member</p></div>');
          update_pagination_info();
        }
      },
      error: function(xhr, status, error) {
        console.error('Error:', error);
        $('#cards-container').html('<div class="col-md-12"><p class="text-center text-danger">Gagal memuat data</p></div>');
        update_pagination_info();
      }
    });
  }

  function update_pagination_info() {
    if (totalRecords === 0) {
      $('#pagination-info').text('Showing 0 to 0 of 0 entries');
      $('#pagination-info-bottom').text('Showing 0 to 0 of 0 entries');
      $('#btn-prev').hide();
      $('#btn-next').hide();
    } else {
      const start = currentStart + 1;
      const end = Math.min(currentStart + currentCards.length, totalRecords);

      $('#pagination-info').text(`Showing ${start} to ${end} of ${totalRecords} entries`);
      $('#pagination-info-bottom').text(`Showing ${start} to ${end} of ${totalRecords} entries`);

      // Show/hide prev button
      if (currentStart > 0) {
        $('#btn-prev').show();
      } else {
        $('#btn-prev').hide();
      }

      // Show/hide next button
      if (end < totalRecords) {
        $('#btn-next').show();
      } else {
        $('#btn-next').hide();
      }
    }
  }

  function next_page() {
    if ((currentStart + pageSize) < totalRecords) {
      currentStart += pageSize;
      load_member_cards();
      $('html, body').animate({
        scrollTop: 0
      }, 'fast');
    }
  }

  function prev_page() {
    if (currentStart >= pageSize) {
      currentStart -= pageSize;
      load_member_cards();
      $('html, body').animate({
        scrollTop: 0
      }, 'fast');
    }
  }

  function display_member_cards(cards) {
    let html = '';

    cards.forEach(function(card, index) {
      const cardType = card.type === 'member' ? 'Member (Retail)' : 'Pelanggan (Sales)';
      const bgGradient = card.type === 'member' ?
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' :
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';

      html += `
      <div class="col-md-6 col-lg-4">
        <div class="member-card" style="background: ${bgGradient};">
          <div class="member-card-buttons">
            <button class="member-card-btn" onclick="preview_card(${index})" title="Preview">
              <i class="bi bi-eye"></i>
            </button>
            <button class="member-card-btn" onclick="print_single_card(${index})" title="Print">
              <i class="bi bi-printer"></i>
            </button>
          </div>
          <div class="member-card-content">
            <div>
              <div class="member-card-header">
                <div class="member-card-logo">CV</div>
                <div class="member-card-badge">${cardType}</div>
              </div>
              <div class="member-card-name">${card.nama}</div>
            </div>
            <div class="member-card-footer">
              <div>
                <div class="member-card-code">${card.kode}</div>
                <small>${card.kota || 'Kota'}</small>
              </div>
              <div class="member-card-points">
                <small>Poin</small>
                <div class="member-card-points-value">${card.sisa_poin || 0}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    });

    $('#cards-container').html(html);
  }

  function preview_card(index) {
    if (index < 0 || index >= currentCards.length) return;

    const card = currentCards[index];
    const cardType = card.type === 'member' ? 'Member (Retail)' : 'Pelanggan (Sales)';
    const bgGradient = card.type === 'member' ?
      'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' :
      'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';

    let previewHtml = `
    <div style="margin: 0 auto; width: 500px; height: 280px; background: ${bgGradient}; border-radius: 16px; padding: 25px; color: white; box-shadow: 0 8px 30px rgba(0,0,0,0.3); position: relative; overflow: hidden;">
      <div style="position: absolute; top: -80px; right: -30px; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
      
      <div style="position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div style="font-size: 32px; font-weight: bold; letter-spacing: 3px;">CV</div>
          <div style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase;">${cardType}</div>
        </div>
        
        <!-- Name -->
        <div>
          <div style="font-size: 22px; font-weight: bold; margin: 15px 0 8px 0;">${card.nama}</div>
          <div style="font-size: 13px; opacity: 0.9;">Pelanggan Setia</div>
        </div>
        
        <!-- Footer -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
          <div>
            <div style="font-size: 16px; font-weight: bold;">${card.kode}</div>
            <small style="opacity: 0.8;">${card.kota || '-'}</small>
          </div>
          <div style="text-align: right;">
            <small style="opacity: 0.8;">Sisa Poin</small>
            <div style="font-size: 24px; font-weight: bold;">${card.sisa_poin || 0}</div>
          </div>
        </div>
      </div>
    </div>
  `;

    $('#print-preview-content').html(previewHtml);

    // Store current card index for printing
    window.currentPrintCardIndex = index;

    const modal = new bootstrap.Modal(document.getElementById('modal-print-preview'));
    modal.show();
  }

  function print_selected_card() {
    if (window.currentPrintCardIndex !== undefined) {
      print_single_card(window.currentPrintCardIndex);
    }
  }

  function print_single_card(index) {
    if (index < 0 || index >= currentCards.length) return;

    const card = currentCards[index];
    const cardType = card.type === 'member' ? 'Member (Retail)' : 'Pelanggan (Sales)';
    const bgGradient = card.type === 'member' ?
      'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' :
      'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';

    let printContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Cetak Member Card - ${card.nama}</title>
      <style>
        body { margin: 0; padding: 20px; background: #f5f5f5; font-family: Arial, sans-serif; }
        .card { 
          width: 500px; 
          height: 280px; 
          background: ${bgGradient}; 
          border-radius: 16px; 
          padding: 25px; 
          color: white; 
          box-shadow: 0 8px 30px rgba(0,0,0,0.3); 
          position: relative; 
          overflow: hidden;
          margin: 0 auto;
        }
        .card::before {
          content: '';
          position: absolute;
          top: -80px;
          right: -30px;
          width: 400px;
          height: 400px;
          background: rgba(255,255,255,0.1);
          border-radius: 50%;
        }
        .content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 32px; font-weight: bold; letter-spacing: 3px; }
        .badge { background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .name { font-size: 22px; font-weight: bold; margin: 15px 0 8px 0; }
        .subtitle { font-size: 13px; opacity: 0.9; }
        .footer { display: flex; justify-content: space-between; align-items: flex-end; }
        .code { font-size: 16px; font-weight: bold; }
        .city { font-size: 12px; opacity: 0.8; }
        .points { text-align: right; }
        .points-label { font-size: 12px; opacity: 0.8; }
        .points-value { font-size: 24px; font-weight: bold; }
        @media print { body { background: white; } }
      </style>
    </head>
    <body>
      <div class="card">
        <div class="content">
          <div>
            <div class="header">
              <div class="logo">CV</div>
              <div class="badge">${cardType}</div>
            </div>
            <div class="name">${card.nama}</div>
            <div class="subtitle">Pelanggan Setia</div>
          </div>
          <div class="footer">
            <div>
              <div class="code">${card.kode}</div>
              <div class="city">${card.kota || '-'}</div>
            </div>
            <div class="points">
              <div class="points-label">Sisa Poin</div>
              <div class="points-value">${card.sisa_poin || 0}</div>
            </div>
          </div>
        </div>
      </div>
    </body>
    </html>
  `;

    const printWindow = window.open('', '', 'width=600,height=400');
    printWindow.document.write(printContent);
    printWindow.document.close();

    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  }

  function print_all_cards() {
    if (currentCards.length === 0) {
      alert('Tidak ada member untuk dicetak');
      return;
    }

    let printContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Cetak Semua Member Card</title>
      <style>
        body { margin: 0; padding: 20px; background: white; font-family: Arial, sans-serif; }
        .cards-container { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .card { 
          width: 500px; 
          height: 280px; 
          border-radius: 16px; 
          padding: 25px; 
          color: white; 
          box-shadow: 0 8px 30px rgba(0,0,0,0.3); 
          position: relative; 
          overflow: hidden;
          page-break-inside: avoid;
        }
        .card::before {
          content: '';
          position: absolute;
          top: -80px;
          right: -30px;
          width: 400px;
          height: 400px;
          background: rgba(255,255,255,0.1);
          border-radius: 50%;
        }
        .content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 32px; font-weight: bold; letter-spacing: 3px; }
        .badge { background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .name { font-size: 22px; font-weight: bold; margin: 15px 0 8px 0; }
        .subtitle { font-size: 13px; opacity: 0.9; }
        .footer { display: flex; justify-content: space-between; align-items: flex-end; }
        .code { font-size: 16px; font-weight: bold; }
        .city { font-size: 12px; opacity: 0.8; }
        .points { text-align: right; }
        .points-label { font-size: 12px; opacity: 0.8; }
        .points-value { font-size: 24px; font-weight: bold; }
      </style>
    </head>
    <body>
      <div class="cards-container">
  `;

    currentCards.forEach(function(card) {
      const cardType = card.type === 'member' ? 'Member (Retail)' : 'Pelanggan (Sales)';
      const bgGradient = card.type === 'member' ?
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' :
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';

      printContent += `
      <div class="card" style="background: ${bgGradient};">
        <div class="content">
          <div>
            <div class="header">
              <div class="logo">CV</div>
              <div class="badge">${cardType}</div>
            </div>
            <div class="name">${card.nama}</div>
            <div class="subtitle">Pelanggan Setia</div>
          </div>
          <div class="footer">
            <div>
              <div class="code">${card.kode}</div>
              <div class="city">${card.kota || '-'}</div>
            </div>
            <div class="points">
              <div class="points-label">Sisa Poin</div>
              <div class="points-value">${card.sisa_poin || 0}</div>
            </div>
          </div>
        </div>
      </div>
    `;
    });

    printContent += `
      </div>
    </body>
    </html>
  `;

    const printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write(printContent);
    printWindow.document.close();

    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  }

  $(function() {
    load_member_cards();
  });
</script>