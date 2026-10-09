<!-- Modal Full Screen untuk Input Retur Pembelian -->
<div class="modal fade" id="returPembelianModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="returPembelianModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="returPembelianModalLabel">
                    <i class="bi bi-arrow-return-left me-2"></i>Form Retur Pembelian
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="retur-pembelian-form">
                    <input type="hidden" name="retur_purc_id" value="">
                    
                    <!-- Header Form -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label required">Tanggal Retur</label>
                            <input type="date" class="form-control" name="retur_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select" style="width: 100%;" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Pembelian Asal</label>
                            <select name="purc_id" id="purc_id" class="form-select" style="width: 100%;" required disabled></select>
                            <div class="form-text">Invoice pembelian yang barangnya akan dikembalikan.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Jenis Retur</label>
                            <select name="jenis_retur" id="jenis_retur" class="form-select" required>
                                <option value="1">Pengembalian Produk / Dana</option>
                                <option value="2">Potong Hutang Supplier</option>
                                <option value="3">Tukar / Ganti Produk</option>
                            </select>
                            <div class="form-text">Barang yang diretur selalu mengurangi stok. Potong hutang juga mengurangi sisa hutang invoice.</div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">No. Retur (Auto)</label>
                            <input type="text" class="form-control" name="retur_no" readonly placeholder="Auto Generate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. Invoice Pembelian</label>
                            <input type="text" id="invoice_info" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Pembelian</label>
                            <input type="text" id="purc_date_info" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Alasan Retur</label>
                            <textarea name="alasan_retur" class="form-control" rows="2" placeholder="Keterangan alasan retur" required></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Detail Produk Retur -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold">Detail Produk Retur</h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle" id="produk_retur_table">
                            <thead class="bg-light fw-bold text-center">
                                <tr>
                                    <th style="width: 30%">Produk</th>
                                    <th style="width: 8%">Qty Beli</th>
                                    <th style="width: 8%" title="Jumlah maksimum yang masih dapat diretur, termasuk qty retur saat ini ketika mengedit.">Maks. Retur</th>
                                    <th style="width: 8%">Qty Retur</th>
                                    <th style="width: 14%">Harga Beli</th>
                                    <th style="width: 14%">Subtotal Retur</th>
                                    <th style="width: 6%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="produk_retur_rows">
                                <!-- baris produk dinamis dari pembelian -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Diskon Retur (Rp)</td>
                                    <td><input type="text" id="retur_disc" name="retur_disc" class="form-control text-end" value="0"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total Retur (Rp)</td>
                                    <td><input type="text" readonly id="total_retur" name="total_retur" class="form-control text-end fw-bold" value="0"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div id="form-msg" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Batal
                </button>
                <button type="button" class="btn btn-warning" id="submit-retur-pembelian">
                    <i class="bi bi-save"></i> Simpan Retur
                </button>
            </div>
        </div>
    </div>
</div>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <!--begin::Header-->
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Retur Pembelian</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Daftar / Riwayat Retur Pembelian</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Input Retur Pembelian Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if($_settings->userdata('level_id') < 2): ?>
                                <div class="form-group col-auto pt-5">
                                    <button class="btn btn-icon btn-warning btn-sm" id="btn_add_retur">
                                        <i class="bi bi-arrow-return-left fs-3"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <!--end::Header-->
                <div class="card-body pt-2 pb-0">
                    <div class="table-responsive">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tgl_awal" value="<?php echo date('Y-m-01') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tgl_akhir" value="<?php echo date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis Retur</label>
                                <select id="jenis_retur_filter" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="1">Retur Produk</option>
                                    <option value="2">Potong Tagihan</option>
                                    <option value="3">Ganti Produk</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Supplier</label>
                                <select id="supplier_filter" name="supplier_filter" class="form-select" style="width: 100%;"></select>
                            </div>
                        </div>

                        <div class="text-end mb-2">
                            <button class="btn btn-sm btn-primary" id="filter-btn"><i class="bi bi-funnel"></i> Filter</button>
                            <button class="btn btn-sm btn-secondary" id="reset-btn"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                            <button class="btn btn-sm btn-success" id="export-btn"><i class="bi bi-file-earmark-excel"></i> Export</button>
                        </div>
                        <table id="table" class="table table-hover table-bordered align-middle w-100">
                            <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                <tr>
                                    <th class="px-2 text-center" style="width: 5%;">No.</th>
                                    <th class="text-center">Tanggal Retur</th>
                                    <th class="text-center">No. Retur/No. Pembelian</th>
                                    <th class="text-center">Jenis Retur</th>
                                    <th class="text-center">Supplier</th>
                                    <th class="text-center">Total Retur</th>
                                    <th class="text-center">Status</th>
                                    <th class="px-2 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
    .dataTables_processing {
        z-index: 9999 !important;
        background: rgba(255, 255, 255, 0.9) !important;
        border-radius: 8px !important;
        padding: 20px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
    }

    .table-container {
        position: relative;
        min-height: 200px;
    }
    
    /* Efek getar untuk validasi */
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }

    .shake-input {
      animation: shake 0.4s ease-in-out;
      border-color: #dc3545 !important;
      background-color: #ffe5e5;
    }
    
    .modal-fullscreen .modal-body {
        overflow-y: auto;
        max-height: calc(100vh - 120px);
    }
</style>

<script>
    const baseUrl = "../classes/ReturPembelian.php";

    // Helper aman untuk HTML
    const escapeHtml = text => $('<div>').text(text || '').html();

    let returPembelianDT;
    let currentReturId = null;

    // ✅ FIXED: Fungsi yang lebih aman menangani semua format input
    const formatRupiah = value => SarinahNumbers.formatMoney(value, { prefix: true });
    const parseRupiah = value => SarinahNumbers.parseMoney(value);

    const normalizeReturPurchaseQuantity = value => {
        const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
        return Object.is(rounded, -0) ? 0 : rounded;
    };
    const returPurchaseQuantityFromInput = value => normalizeReturPurchaseQuantity(value);
    const returPurchaseQuantityFromDisplay = value => normalizeReturPurchaseQuantity(
        SarinahNumbers.parseLocaleQuantity(value)
    );
    const returPurchaseQuantityInput = value => normalizeReturPurchaseQuantity(value)
        .toFixed(4)
        .replace(/\.?0+$/, '') || '0';
    const formatReturPurchaseQuantity = value => SarinahNumbers.formatQuantity(
        normalizeReturPurchaseQuantity(value),
        { minimumFractionDigits: 0, maximumFractionDigits: 4 }
    );

    /** ======================= [ MODAL FUNCTIONS ] ======================= **/
    function openReturPembelianModal(retur_id = null) {
        currentReturId = retur_id;
        
        // Reset form terlebih dahulu
        resetForm();
        
        if (retur_id) {
            $('#returPembelianModalLabel').html('<i class="bi bi-arrow-return-left me-2"></i>Edit Retur Pembelian');
            // Tampilkan loading state
            $('#returPembelianModal .modal-body').addClass('loading');
            loadReturData(retur_id);
        } else {
            $('#returPembelianModalLabel').html('<i class="bi bi-arrow-return-left me-2"></i>Tambah Retur Pembelian Baru');
            generateAutoReturNo();
            // Langsung tampilkan modal untuk tambah baru
            $('#returPembelianModal').modal('show');
        }
    }

    function resetForm() {
        console.log('🔄 Resetting form retur...');
        
        // Reset form values
        $('#retur-pembelian-form')[0].reset();
        
        // Clear dynamic rows
        showProductPlaceholder('Pilih supplier, lalu pilih pembelian asal.');
        
        // Reset values
        $('#retur_disc').val('0');
        $('#total_retur').val('0');
        $('[name="retur_purc_id"]').val('');
        
        // Reset Select2 elements
        $('#supplier_id').val(null).trigger('change');
        $('#purc_id').empty().prop('disabled', true).trigger('change');
        
        // Clear info fields
        $('#invoice_info').val('');
        $('#purc_date_info').val('');
        
        // Clear messages
        $('#form-msg').empty();
        
        // Reset jenis retur
        $('#jenis_retur').val('1').trigger('change');
        
        // Set default date
        $('[name="retur_date"]').val('<?= date('Y-m-d') ?>');
        
        console.log('✅ Form retur reset completed');
    }

    function generateAutoReturNo() {
        $('[name="retur_no"]').val('Memuat...');
        $.getJSON(baseUrl + '?f=get_next_retur_no')
            .done(resp => $('[name="retur_no"]').val(resp.retur_no || 'Otomatis saat disimpan'))
            .fail(() => $('[name="retur_no"]').val('Otomatis saat disimpan'));
    }

    function showProductPlaceholder(message) {
        $('#produk_retur_rows').html(`
            <tr class="product-placeholder">
                <td colspan="7" class="text-center text-muted py-6">
                    <i class="bi bi-info-circle me-2"></i>${escapeHtml(message)}
                </td>
            </tr>`);
    }

    function loadReturData(retur_id) {
        console.log('📥 Loading retur pembelian data for ID:', retur_id);
        start_loader();
        
        // Clear existing data first
        resetForm();
        
        $.ajax({
            url: baseUrl + "?f=get_retur_by_id",
            method: "GET",
            data: { id: retur_id },
            dataType: "json",
            success: function(resp) {
                console.log('📦 Retur pembelian data response:', resp);
                
                if (resp.status === "success") {
                    const header = resp.header;
                    const details = resp.details;
                    
                    console.log('🔄 Setting header data:', header);
                    
                    // ===== SET HEADER DATA =====
                    $('[name="retur_purc_id"]').val(header.retur_purc_id || '');
                    $('[name="retur_date"]').val(header.retur_date ? header.retur_date.split(' ')[0] : '<?= date('Y-m-d') ?>');
                    $('[name="retur_no"]').val(header.retur_no || '');
                    $('[name="jenis_retur"]').val(header.jenis_retur || '1').trigger('change');
                    $('[name="alasan_retur"]').val(header.alasan_retur || '');
                    $('#retur_disc').val(formatRupiah(header.retur_disc || 0));
                    
                    // ===== SET SUPPLIER DAN PEMBELIAN ASAL =====
                    if (header.supplier_id) {
                        $('#supplier_id').empty().append(new Option(
                            header.supplier_nama || 'Supplier',
                            header.supplier_id,
                            true,
                            true
                        )).trigger('change.select2');
                    }

                    if (header.purc_id) {
                        $('#purc_id').empty().prop('disabled', false);
                        
                        // Add new option and trigger change
                        const newOption = new Option(
                            `${header.purc_invoice || 'Pembelian'} - ${header.purc_date || ''}`,
                            header.purc_id, 
                            true, 
                            true
                        );
                        $('#purc_id').append(newOption).trigger('change');
                        
                        // Trigger change event untuk Select2
                        setTimeout(() => {
                            $('#purc_id').val(header.purc_id).trigger('change.select2');
                        }, 100);
                    }

                    $('#invoice_info').val(header.purc_invoice || '');
                    $('#purc_date_info').val(header.purc_date || '');
                    
                    // ===== SET DETAIL PRODUK RETUR =====
                    console.log('🔄 Setting product retur details:', details);
                    $('#produk_retur_rows').empty();
                    
                    if (details && details.length > 0) {
                        details.forEach((item, index) => {
                            console.log(`📝 Adding product retur row ${index + 1}:`, item);
                            addProductReturRow(item);
                        });
                    } else {
                        console.warn('⚠️ No product retur details found');
                    }
                    
                    // ===== HITUNG ULANG TOTAL =====
                    setTimeout(() => {
                        hitungTotalRetur();
                        console.log('✅ Retur pembelian data loaded successfully');
                        
                        // Tampilkan modal setelah data selesai dimuat
                        $('#returPembelianModal').modal('show');
                    }, 500);
                    
                } else {
                    console.error('❌ Failed to load retur pembelian data:', resp.msg);
                    showMessage('Gagal memuat data retur pembelian: ' + (resp.msg || 'Unknown error'), 'danger', '#form-msg');
                    // Tetap tampilkan modal meski gagal load data
                    $('#returPembelianModal').modal('show');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error loading retur pembelian data:', error);
                console.log('XHR:', xhr);
                showMessage('Gagal memuat data retur pembelian: ' + error, 'danger', '#form-msg');
                // Tetap tampilkan modal meski error
                $('#returPembelianModal').modal('show');
            },
            complete: function() {
                end_loader();
                $('#returPembelianModal .modal-body').removeClass('loading');
            }
        });
    }

    function addProductReturRow(item = null) {
        const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 9);
        const row = `
        <tr id="${rowId}">
            <td>
                <input type="hidden" name="retur_detail_id[]" value="${item ? item.retur_detail_id : ''}">
                <input type="hidden" name="purc_detail_id[]" value="${item ? item.purc_detail_id : ''}">
                <input type="hidden" class="produk_id" name="produk_id[]" value="${item ? item.produk_id : ''}">
                <div class="fw-bold">${item ? escapeHtml(item.nama_produk) : ''}</div>
                <small class="text-muted">${item ? escapeHtml(item.barcode || '') : ''}</small>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm text-center qty_beli" 
                       value="${item ? formatReturPurchaseQuantity(item.qty_beli) : '0'}" readonly>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm text-center qty_sisa" 
                       value="${item ? formatReturPurchaseQuantity(item.qty_sisa) : '0'}"
                       title="Jumlah maksimum yang dapat diretur" readonly>
            </td>
            <td>
                <input type="number" step="0.0001" min="0" max="${item ? returPurchaseQuantityInput(item.qty_sisa) : 0}" 
                       class="form-control form-control-sm text-center qty_retur" 
                       name="qty_retur[]" 
                       value="${item ? returPurchaseQuantityInput(item.qty_retur) : '0'}"
                       ${item && normalizeReturPurchaseQuantity(item.qty_sisa) > 0 ? '' : 'readonly'}>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm text-end harga" 
                       value="${item ? formatRupiah(item.harga) : '0'}" readonly>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm text-end subtotal_retur" 
                       name="subtotal_retur[]" readonly 
                       value="${item ? formatRupiah(item.subtotal_retur) : '0'}">
            </td>
            <td class="text-center">
                <span class="badge ${item ? (item.status_retur == '1' ? 'bg-success' : 'bg-warning') : 'bg-secondary'}">
                    ${item ? (item.status_retur == '1' ? 'Diretur' : 'Pending') : 'Baru'}
                </span>
            </td>
        </tr>`;
        
        $('#produk_retur_rows').append(row);
        
    }

    function hitungSubtotalRetur(tr) {
        const qtyRetur = returPurchaseQuantityFromInput(tr.find('.qty_retur').val());
        const harga = parseRupiah(tr.find('.harga').val());
        const subtotal = qtyRetur * harga;
        tr.find('.subtotal_retur').val(formatRupiah(subtotal));
    }

    function hitungTotalRetur() {
        let total = 0;

        // Hitung total dari semua subtotal retur
        $('#produk_retur_rows tr').each(function () {
            const subtotal = parseRupiah($(this).find('.subtotal_retur').val());
            total += subtotal;
        });

        // Hitung diskon retur
        const disc = parseRupiah($('#retur_disc').val());
        const totalAfterDisc = Math.max(total - disc, 0);

        $('#total_retur').val(formatRupiah(totalAfterDisc));
    }

    /** ======================= [ FORM VALIDATION & SUBMISSION ] ======================= **/
    function validateReturForm() {
        const supplierId = $('#supplier_id').val();
        const purcId = $('#purc_id').val();
        const returDate = $('[name="retur_date"]').val();
        const alasanRetur = $('[name="alasan_retur"]').val();
        
        if (!supplierId) {
            showMessage('Pilih supplier terlebih dahulu', 'danger', '#form-msg');
            $('#supplier_id').select2('open');
            return false;
        }

        if (!purcId) {
            showMessage('Pilih pembelian yang akan diretur', 'danger', '#form-msg');
            $('#purc_id').focus();
            return false;
        }
        
        if (!returDate) {
            showMessage('Tanggal retur harus diisi', 'danger', '#form-msg');
            $('[name="retur_date"]').focus();
            return false;
        }
        
        if (!alasanRetur.trim()) {
            showMessage('Alasan retur harus diisi', 'danger', '#form-msg');
            $('[name="alasan_retur"]').focus();
            return false;
        }
        
        let hasValidReturProducts = false;
        let invalidQty = false;
        $('#produk_retur_rows tr').each(function() {
            const qtyInput = $(this).find('.qty_retur');
            const qtyRetur = returPurchaseQuantityFromInput(qtyInput.val());
            const qtySisa = returPurchaseQuantityFromDisplay($(this).find('.qty_sisa').val());

            qtyInput.val(returPurchaseQuantityInput(qtyRetur));
            
            if (qtyRetur > 0 && qtyRetur <= qtySisa) {
                hasValidReturProducts = true;
            } else if (qtyRetur < 0 || qtyRetur > qtySisa) {
                invalidQty = true;
                return false;
            }
        });

        if (invalidQty) {
            showMessage('Qty retur tidak boleh melebihi qty sisa', 'danger', '#form-msg');
            return false;
        }
        
        if (!hasValidReturProducts) {
            showMessage('Tambahkan minimal satu produk dengan quantity retur yang valid', 'danger', '#form-msg');
            return false;
        }
        
        const totalRetur = parseRupiah($('#total_retur').val());
        if (totalRetur <= 0) {
            showMessage('Total retur harus lebih dari 0', 'danger', '#form-msg');
            return false;
        }
        
        return true;
    }

    function submitReturForm() {
        if (!validateReturForm()) return;
        
        start_loader();
        const formData = new FormData($('#retur-pembelian-form')[0]);
        
        $.ajax({
            url: baseUrl + "?f=" + (currentReturId ? 'update_retur' : 'save_retur'),
            method: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                end_loader();
                if (resp.status === 'success') {
                    showMessage(resp.msg, 'success', '#form-msg');
                    setTimeout(() => {
                        $('#returPembelianModal').modal('hide');
                        forceReloadTable();
                    }, 1500);
                } else {
                    showMessage(resp.msg || 'Gagal menyimpan data retur', 'danger', '#form-msg');
                }
            },
            error: function(xhr, status, error) {
                end_loader();
                console.error('AJAX Error:', error);
                const message = xhr.responseJSON && xhr.responseJSON.msg
                    ? xhr.responseJSON.msg
                    : 'Terjadi kesalahan saat menyimpan retur';
                showMessage(message, 'danger', '#form-msg');
            }
        });
    }

    function showMessage(msg, type = 'primary', container = '#msg') {
        const alert = $('<div>')
            .addClass(`alert alert-${type} alert-dismissible fade show`)
            .html(`
                ${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `)
            .hide();
        $(container).html(alert);
        alert.show('slow');
        setTimeout(() => alert.hide('slow').remove(), 5000);
    }

    /** ======================= [ ACTION BUTTONS RENDERER - REFACTORED ] ======================= **/
    function actionButtons(row) {
        const returId = row.retur_purc_id;
        const returNo = escapeHtml(row.retur_no || '');
        const status = row.status || 'ACTIVE';

        let buttons = `
            <div class="btn-group" role="group">
                <button class="btn btn-icon btn-light-info btn-sm me-1 print_retur" 
                    data-id="${returId}" title="Lihat / Print Retur">
                    <i class="fa fa-print"></i>
                </button>
        `;

        <?php if($_settings->userdata('level_id') < 2): ?>
        if (status === 'ACTIVE') {
            buttons += `
                <button class="btn btn-icon btn-light-warning btn-sm me-1 edit_retur" 
                    data-id="${returId}" title="Edit Retur">
                    <i class="fa fa-edit"></i>
                </button>
                <button class="btn btn-icon btn-light-danger btn-sm me-1 delete_retur"
                    data-id="${returId}" data-retur_no="${returNo}" title="Hapus Retur">
                    <i class="fa fa-trash"></i>
                </button>
            `;
        }
        <?php endif; ?>

        buttons += `</div>`;
        return buttons;
    }

    /** ======================= [ DELETE FUNCTION ] ======================= **/
    function deleteRetur(returId) {
        start_loader();
        console.log('Starting DELETE for retur_id:', returId);
        
        $.ajax({
            url: baseUrl + "?f=delete_retur",
            method: "POST",
            data: { retur_id: returId },
            dataType: "json",   
            timeout: 30000,
            success: function(resp) {
                console.log('DELETE Response:', resp);
                
                if (resp && resp.status === "success") {
                    show_msg("Retur berhasil dihapus", "success");
                    $('#confirm_modal').modal('hide');
                    setTimeout(() => {
                        forceReloadTable();
                    }, 300);
                    
                } else {
                    const msg = resp?.msg || "Gagal menghapus retur.";
                    console.error("Server Error:", resp);
                    show_msg(msg, "danger");
                }
                end_loader();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert_toast("Terjadi kesalahan koneksi ke server: " + error, "error");
                end_loader();
            }        
        });
    }

    function show_msg(message, type = "primary") {
        $('.alert_msg').remove();
        let _el = $('<div>')
            .hide()
            .addClass('alert alert-' + type + ' alert_msg')
            .html('<i class="fa fa-check-circle me-2"></i>' + message);
        $('#msg').append(_el);
        _el.show('slow');
        setTimeout(() => _el.hide('slow').remove(), 3000);
    }

    /** ======================= [ EVENT HANDLERS ] ======================= **/
    function attachEventHandlers() {
        $(document)
            .off('click', '.print_retur')
            .off('click', '.edit_retur')
            .off('click', '.delete_retur')
            .on('click', '.print_retur', function() {
                const id = $(this).data('id');
                if (!id) {
                    console.error('ID tidak valid untuk print');
                    return;
                }
                window.open('<?php echo base_url ?>admin?page=retur_pembelian/retur_print&id=' + encodeURIComponent(id), '_blank');
            })
            .on('click', '.edit_retur', function() {
                const id = $(this).data('id');
                if (!id) {
                    Swal.fire("Error", "ID retur tidak valid", "error");
                    return;
                }
                openReturPembelianModal(id);
            })
            .on('click', '.delete_retur', function() {
                const returId = $(this).data('id');
                const returNo = $(this).data('retur_no');
                if (!returId) {
                    Swal.fire("Error", "ID retur tidak valid", "error");
                    return;
                }
                _conf(`Yakin ingin menghapus retur <b>${escapeHtml(returNo)}</b>?`, "deleteRetur", [returId]);
            });
    }

    $(document).ready(function() {
        // Initialize DataTables
        load_data();
        
        // Modal events
        $('#btn_add_retur').click(() => openReturPembelianModal());
        
        $('#submit-retur-pembelian').click(submitReturForm);
        
        $(document).on('input', '.qty_retur', function() {
            const tr = $(this).closest('tr');
            const qtyRetur = returPurchaseQuantityFromInput($(this).val());
            const qtySisa = returPurchaseQuantityFromDisplay(tr.find('.qty_sisa').val());
            
            if (qtyRetur > qtySisa) {
                $(this).val(returPurchaseQuantityInput(qtySisa));
                showMessage('Qty retur tidak boleh melebihi qty sisa', 'warning', '#form-msg');
            }
            
            hitungSubtotalRetur(tr);
            hitungTotalRetur();
        });
        
        $(document).on('input', '#retur_disc', function() {
            const val = parseRupiah($(this).val());
            $(this).val(formatRupiah(val));
            hitungTotalRetur();
        });
        
        // Alur input: pilih supplier terlebih dahulu, lalu invoice pembelian asal.
        $('#supplier_id').select2({
            placeholder: 'Pilih supplier...',
            allowClear: true,
            dropdownParent: $('#returPembelianModal'),
            ajax: {
                url: baseUrl + '?f=search_supplier',
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term || '' }),
                processResults: data => ({
                    results: data.filter(item => item.id).map(item => ({
                        id: item.id,
                        text: `${item.kode ? item.kode + ' - ' : ''}${item.nama}`
                    }))
                }),
                cache: true
            },
            minimumInputLength: 0
        }).on('select2:select', function() {
            $('#purc_id').empty().prop('disabled', false).trigger('change');
            $('#invoice_info, #purc_date_info').val('');
            showProductPlaceholder('Pilih pembelian asal dari supplier ini.');
        }).on('select2:clear', function() {
            $('#purc_id').empty().prop('disabled', true).trigger('change');
            $('#invoice_info, #purc_date_info').val('');
            showProductPlaceholder('Pilih supplier, lalu pilih pembelian asal.');
        });

        $('#purc_id').select2({
            placeholder: 'Pilih invoice pembelian...',
            allowClear: true,
            dropdownParent: $('#returPembelianModal'),
            ajax: {
                url: baseUrl + "?f=search_pembelian",
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term || '',
                    supplier_id: $('#supplier_id').val() || 0
                }),
                processResults: data => ({
                    results: (Array.isArray(data) ? data : []).map(item => ({
                        id: item.purcid,
                        text: `${item.purc_invoice}${item.purc_nofak ? ' / ' + item.purc_nofak : ''} - ${item.purc_date}`,
                        data: item
                    }))
                }),
                cache: true
            },
            minimumInputLength: 0
        }).on('select2:select', function (e) {
            const data = e.params.data.data;
            $('#invoice_info').val(data.purc_invoice);
            $('#purc_date_info').val(data.purc_date);
            
            // Load detail produk dari pembelian
            loadPembelianDetails(data.purcid);
        }).on('select2:clear', function() {
            $('#invoice_info, #purc_date_info').val('');
            showProductPlaceholder('Pilih pembelian asal dari supplier ini.');
        });

        // Event handlers untuk table actions
        attachEventHandlers();
        
        // Filter events
        $('#filter-btn').click(() => {
            start_loader();
            setTimeout(() => {
                forceReloadTable();
                setTimeout(() => end_loader(), 1000);
            }, 300);
        });

        $('#reset-btn').click(() => {
            start_loader();
            $('#tgl_awal').val('<?php echo date('Y-m-01') ?>');
            $('#tgl_akhir').val('<?php echo date('Y-m-d') ?>');
            $('#jenis_retur_filter').val('');
            $('#supplier_filter').val('').trigger('change');
            setTimeout(() => {
                forceReloadTable();
                setTimeout(() => end_loader(), 1000);
            }, 300);
        });

        $('#export-btn').click(function() {
            const tgl_awal = $('#tgl_awal').val();
            const tgl_akhir = $('#tgl_akhir').val();
            const jenis_retur = $('#jenis_retur_filter').val();
            const supplier_id = $('#supplier_filter').val();
            const url = baseUrl + `?f=export_excel&tgl_awal=${tgl_awal}&tgl_akhir=${tgl_akhir}&jenis_retur=${jenis_retur}&supplier_id=${supplier_id}`;
            window.open(url, '_blank');
        });

        // Focus management
        $('#returPembelianModal, #confirm_modal').on('hide.bs.modal', function () {
            setTimeout(() => {
                $('#btn_add_retur').focus();
            }, 10);
        });

    });

    function loadPembelianDetails(purcid) {
        start_loader();
        $.ajax({
            url: baseUrl + "?f=get_pembelian_details",
            method: "GET",
            data: { purc_id: purcid, retur_id: currentReturId || 0 },
            dataType: "json",
            success: function(resp) {
                if (resp.status === "success") {
                    $('#produk_retur_rows').empty();
                    if (resp.purchase) {
                        $('#invoice_info').val(resp.purchase.purc_invoice || '');
                        $('#purc_date_info').val(resp.purchase.purc_date || '');
                    }
                    
                    if (resp.details && resp.details.length > 0) {
                        resp.details.forEach((item) => {
                            addProductReturRow(item);
                        });
                    } else {
                        showProductPlaceholder('Tidak ada produk yang masih dapat diretur dari pembelian ini.');
                    }
                    
                    hitungTotalRetur();
                } else {
                    showMessage(resp.msg || 'Gagal memuat detail pembelian', 'danger', '#form-msg');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.msg
                    ? xhr.responseJSON.msg
                    : 'Gagal memuat detail pembelian';
                showMessage(message, 'danger', '#form-msg');
            },
            complete: function() {
                end_loader();
            }
        });
    }

    // [DataTables functions]
    /** ======================= [ LOAD DATA TABLE ] ======================= **/
    function load_data() {
        if ($.fn.DataTable.isDataTable('#table')) {
            returPembelianDT.clear().destroy();
            $('.dataTables_processing').remove();            
        }

        returPembelianDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            stateSave: true,
            stateDuration: -1,
            searchDelay: 500,
            stateSaveCallback: function(settings, data) {
                localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data));
            },
            stateLoadCallback: function(settings) {
                return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance) || '{}');
            },
            ajax: {
                url: baseUrl + "?f=get_data_retur",
                type: "POST",
                data: function(d) {
                    d.tgl_awal = $('#tgl_awal').val();
                    d.tgl_akhir = $('#tgl_akhir').val();
                    d.jenis_retur = $('#jenis_retur_filter').val();
                    d.supplier_id = $('#supplier_filter').val();
                },
                dataSrc: function(json) {
                    setTimeout(() => {
                        $('.dataTables_processing').fadeOut();
                    }, 100);
                    
                    if (json && json.data) {
                        return json.data;
                    } else {
                        console.error('Invalid response format:', json);
                        if (json && json.error) {
                            show_msg('Error: ' + json.error, 'danger');
                        }
                        return [];
                    }
                },
                error: function(xhr, status, thrown) {
                    console.error('AJAX Error:', status, thrown);
                    $('.dataTables_processing').hide();
                    
                    let errorMsg = 'Terjadi kesalahan koneksi ke server';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.status === 500) {
                        errorMsg = 'Internal Server Error - Silakan cek log server';
                    }
                    
                    show_msg(errorMsg, 'danger');
                    return [];
                }
            },

            columns: [
                { 
                    data: "no", 
                    className: "text-center fw-bold",
                    orderable: false,
                    searchable: false
                },
                { 
                    data: "retur_date", 
                    className: "text-center",
                    render: function(data, type, row) {
                        return escapeHtml(data || '');
                    }
                },
                {
                    data: null, 
                    className: "text-center",
                    render: function(data, type, row) {
                        return `
                            <div class="fw-bold">${escapeHtml(row.retur_no)}</div>
                            <small class="text-muted">${escapeHtml(row.purc_invoice || '')}</small>
                        `;
                    }
                },
                {
                    data: "jenis_retur",
                    className: "text-center",
                    render: function(data, type, row) {
                        let jenis = 'Lainnya';
                        let warna = 'secondary';
                        if (data == 1) {
                            jenis = 'Retur Produk';
                            warna = 'warning';
                        } else if (data == 2) {
                            jenis = 'Potong Tagihan';
                            warna = 'info';
                        } else if (data == 3) {
                            jenis = 'Ganti Produk';
                            warna = 'primary';
                        }
                        return `<span class="badge bg-${warna}">${jenis}</span>`;
                    }
                },
                {
                    data: null,
                    className: "text-start",
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex align-items-start">
                                <div class="symbol symbol-30px me-2">
                                    <i class="fa fa-building text-primary fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">${escapeHtml(row.supplier_name || '-')}</div>
                                    ${row.supplier_alamat ? `<div class="text-muted small">${escapeHtml(row.supplier_alamat)}</div>` : ''}
                                </div>
                            </div>
                        `;
                    }
                },
                { 
                    data: "total_retur", 
                    className: "text-end fw-bold", 
                    render: function(data, type, row) {
                        const value = parseFloat(data) || 0;
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                },
                {
                    data: "status",
                    className: "text-center",
                    render: function(data, type, row) {
                        let status = 'AKTIF';
                        let warna = 'success';
                        if (data === 'VOID') {
                            status = 'DIBATALKAN';
                            warna = 'danger';
                        } else if (data === 'DELETED') {
                            status = 'DIHAPUS';
                            warna = 'secondary';
                        } else if (data === 'PENDING') {
                            status = 'MENUNGGU';
                            warna = 'warning';
                        }
                        return `<span class="badge bg-${warna}">${status}</span>`;
                    }
                },
                { 
                    data: null, 
                    orderable: false, 
                    searchable: false,
                    className: "text-center", 
                    render: function(data, type, row) {
                        return actionButtons(row);
                    }
                }
            ],
            order: [[1, 'desc']],
            language: {
                processing: "<div class='spinner-border spinner-border-sm me-2'></div> Memproses...",
                lengthMenu: "Tampilkan _MENU_ data",
                zeroRecords: "Tidak ada data yang ditemukan",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            },
            responsive: true,
            scrollX: true,
            initComplete: function(settings, json) {
                console.log('DataTables initialization complete');
                $('.dataTables_processing').hide();
            },
            drawCallback: function(settings) {
                $('.dataTables_processing').hide();
                if (settings.json && settings.json.recordsTotal === 0) {
                    $(this).find('tbody').html(
                        '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data retur pembelian</td></tr>'
                    );
                }
            }
        });

        return returPembelianDT;
    }

    function reloadDataTable() {
        if (typeof returPembelianDT !== 'undefined' && $.fn.DataTable.isDataTable('#table')) {
            try {
                console.log('Reloading DataTable...');
                returPembelianDT.ajax.reload(function(json) {
                    console.log('DataTables reload completed successfully');
                    $('.dataTables_processing').hide();
                }, false);
            } catch (error) {
                console.error('Error reloading DataTables:', error);
                setTimeout(() => {
                    forceReloadTable();
                }, 300);
            }
        } else {
            console.warn('DataTables not initialized, loading...');
            load_data();
        }
    }

    function forceReloadTable() {
        try {
            console.log('Force reloading table...');
            start_loader();
            
            if ($.fn.DataTable.isDataTable('#table')) {
                try {
                    returPembelianDT.clear().destroy();
                } catch (e) {
                    console.warn('Error during destroy:', e);
                }
            }
            
            localStorage.removeItem('DataTables_table');
            sessionStorage.removeItem('DataTables_table');
            
            setTimeout(() => {
                load_data();
                attachEventHandlers();
                end_loader();
            }, 500);
            
        } catch (error) {
            console.error('Error in forceReloadTable:', error);
            end_loader();
        }
    }

    /** ======================= [ DOCUMENT READY - IMPROVED ] ======================= **/
    $(document).ready(function() {
        // Inisialisasi Select2 supplier filter
        $('#supplier_filter').select2({
            placeholder: 'Cari supplier...',
            allowClear: true,
            width: 'resolve',
            ajax: {
                url: '<?php echo base_url ?>classes/ReturPembelian.php?f=search_supplier',
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term || '' }),
                processResults: data => ({
                    results: data.map(item => ({
                        id: item.id,
                        text: `${item.nama}${item.alamat ? ' — ' + item.alamat : ''}`
                    }))
                }),
                cache: true
            },
            language: {
                noResults: () => 'Tidak ada supplier ditemukan',
                searching: () => 'Mencari...',
                inputTooShort: () => 'Ketik minimal 2 huruf'
            },
            minimumInputLength: 2
        });

        // Auto focus pada filter tanggal
        setTimeout(() => $('#tgl_awal').focus(), 100);
    });    


const loadingCSS = `
    .loading {
        position: relative;
        pointer-events: none;
    }
    .loading::after {
        content: "Memuat data...";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255,255,255,0.9);
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 1000;
    }
`;
// Inject CSS
$('head').append('<style>' + loadingCSS + '</style>');
</script>
