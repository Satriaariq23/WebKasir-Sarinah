<!-- Modal Full Screen untuk Input Pembelian -->
<div class="modal fade" id="pembelianModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="pembelianModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="pembelianModalLabel">
                    <i class="bi bi-cart-plus me-2"></i>Form Pembelian Barang
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="pembelian-form">
                    <input type="hidden" name="purcid" value="">

                    <!-- Header Form -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label required">Tanggal Faktur</label>
                            <input type="date" class="form-control" name="purc_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Jenis Transaksi</label>
                            <select name="bayar_id" id="bayar_id" class="form-select" required>
                                <option value="1">Tunai</option>
                                <option value="2">Kredit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jatuh Tempo</label>
                            <input type="date" name="purc_jt" id="purc_jt" class="form-control" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">No. Invoice (Auto)</label>
                            <input type="text" class="form-control" name="purc_invoice" readonly placeholder="Auto Generate">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">No. Faktur Supplier</label>
                            <input type="text" class="form-control" name="purc_nofak" placeholder="Opsional">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Faktur Supplier</label>
                            <input type="date" class="form-control" name="purc_datefak">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select" style="width: 100%;" required></select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="catatan" class="form-control" placeholder="Opsional">
                        </div>
                    </div>

                    <hr>

                    <!-- Detail Produk -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold">Detail Produk</h5>
                        <button type="button" class="btn btn-primary btn-sm" id="add_row">
                            <i class="fa fa-plus"></i> Tambah Produk
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle" id="produk_table">
                            <thead class="bg-light fw-bold text-center">
                                <tr>
                                    <th style="width: 30%">Produk</th>
                                    <th style="width: 8%" title="Stok produk saat ini setelah seluruh pembelian, penjualan, dan retur">Stok Saat Ini</th>
                                    <th style="width: 8%">Qty</th>
                                    <th style="width: 12%">Harga Beli</th>
                                    <th style="width: 12%">Subtotal</th>
                                    <th style="width: 6%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="produk_rows">
                                <!-- baris produk dinamis -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Diskon Faktur (Rp)</td>
                                    <td><input type="text" id="purc_disc" name="purc_disc" class="form-control text-end" value="0"></td>
                                    <td></td>
                                </tr>
                                <tr class="d-none">
                                    <td colspan="6"><input type="hidden" readonly id="purc_ppn" name="purc_ppn" value="0"></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Grand Total Pembelian (Rp)</td>
                                    <td><input type="text" readonly id="grand_total" name="purc_total" class="form-control text-end fw-bold" value="0"></td>
                                    <td></td>
                                </tr>
                                <tr id="purchase-return-summary" class="d-none">
                                    <td colspan="4" class="text-end fw-bold text-warning">Total Retur Aktif (Rp)</td>
                                    <td><input type="text" readonly id="purc_retur_total" class="form-control text-end fw-bold text-warning" value="0"></td>
                                    <td></td>
                                </tr>
                                <tr id="purchase-net-summary" class="d-none">
                                    <td colspan="4" class="text-end fw-bold text-success">Nilai Bersih Setelah Retur (Rp)</td>
                                    <td><input type="text" readonly id="purc_net_total" class="form-control text-end fw-bold text-success" value="0"></td>
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
                <button type="button" class="btn btn-success" id="submit-pembelian">
                    <i class="bi bi-save"></i> Simpan Pembelian
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
                        <span class="text-dark fw-bolder">Pembelian</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Daftar / Riwayat Pembelian</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Input Faktur Pembelian Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if ($_settings->userdata('level_id') < 2): ?>
                                <div class="form-group col-auto pt-5">
                                    <button class="btn btn-icon btn-success btn-sm" id="btn_add">
                                        <i class="bi bi-file-earmark-plus fs-3"></i>
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
                                <input type="date" class="form-control" id="tgl_awal" value="<?php echo date('Y-01-01') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tgl_akhir" value="<?php echo date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis Transaksi</label>
                                <select id="jenis" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="1">Tunai</option>
                                    <option value="2">Kredit</option>
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
                                    <th class="text-center">Tanggal</th>
                                    <th class="text-center">No. Invoice/No. Faktur</th>
                                    <th class="text-center">Jenis Transaksi</th>
                                    <th class="text-center">Supplier</th>
                                    <th class="text-center">Grand Total</th>
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
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
    }

    .table-container {
        position: relative;
        min-height: 200px;
    }

    /* Efek getar untuk validasi harga */
    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        20%,
        60% {
            transform: translateX(-6px);
        }

        40%,
        80% {
            transform: translateX(6px);
        }
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
    const baseUrl = "../classes/Pembelian.php";

    // Helper aman untuk HTML
    const escapeHtml = text => $('<div>').text(text || '').html();
    const normalizePembelianStatus = status => String(status ?? '1').toUpperCase();
    const isPembelianActive = status => ['1', 'ACTIVE'].includes(normalizePembelianStatus(status));
    const isPembelianDeleted = status => ['0', 'DELETED'].includes(normalizePembelianStatus(status));
    const isPembelianVoid = status => ['VOID', 'CANCELED', 'CANCELLED'].includes(normalizePembelianStatus(status));

    let pembelianDT;
    let currentPurcId = null;
    let activePurchaseReturnTotal = 0;

    /** ======================= [ MODAL FUNCTIONS ] ======================= **/
    function openPembelianModal(purcid = null) {
        currentPurcId = purcid;

        // Reset form terlebih dahulu
        resetForm();

        if (purcid) {
            $('#pembelianModalLabel').html('<i class="bi bi-cart-plus me-2"></i>Edit Pembelian');
            // Tampilkan loading state
            $('#pembelianModal .modal-body').addClass('loading');
            loadPurcData(purcid);
        } else {
            $('#pembelianModalLabel').html('<i class="bi bi-cart-plus me-2"></i>Tambah Pembelian Baru');
            generateAutoInvoice();
            // Langsung tampilkan modal untuk tambah baru
            $('#pembelianModal').modal('show');
        }
    }

    function resetForm() {
        console.log('🔄 Resetting form...');

        // Reset form values
        $('#pembelian-form')[0].reset();

        // Clear dynamic rows
        $('#produk_rows').empty();

        // Reset values
        $('#purc_disc').val('0');
        $('#purc_ppn').val('0');
        $('#grand_total').val('0');
        activePurchaseReturnTotal = 0;
        $('#purc_retur_total, #purc_net_total').val('0');
        $('#purchase-return-summary, #purchase-net-summary').addClass('d-none');
        $('[name="purcid"]').val('');

        // Reset Select2 elements
        $('#supplier_id').val(null).trigger('change');

        // Clear messages
        $('#form-msg').empty();

        // Reset jenis transaksi
        $('#bayar_id').val('1').trigger('change');
        $('#purc_jt').prop('disabled', true).val('');

        // Set default date
        $('[name="purc_date"]').val('<?= date('Y-m-d') ?>');

        // Tambah baris pertama untuk form baru
        if (!currentPurcId) {
            addProductRow();
        }

        console.log('✅ Form reset completed');
    }

    function generateAutoInvoice() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const time = String(now.getTime()).slice(-4);
        const invoice = `PURC-${year}${month}${day}-${time}`;
        $('[name="purc_invoice"]').val(invoice);
    }

    function loadPurcData(purcid) {
        console.log('📥 Loading pembelian data for ID:', purcid);
        start_loader();

        // Clear existing data first
        resetForm();

        $.ajax({
            url: baseUrl + "?f=get_pembelian_by_id",
            method: "GET",
            data: {
                id: purcid
            },
            dataType: "json",
            success: function(resp) {
                console.log('📦 Pembelian data response:', resp);

                if (resp.status === "success") {
                    const header = resp.header;
                    const details = resp.details;

                    console.log('🔄 Setting header data:', header);

                    // ===== SET HEADER DATA =====
                    $('[name="purcid"]').val(header.purcid || '');
                    $('[name="purc_date"]').val(header.purc_date ? header.purc_date.split(' ')[0] : '<?= date('Y-m-d') ?>');
                    $('[name="purc_invoice"]').val(header.purc_invoice || '');
                    $('[name="purc_nofak"]').val(header.purc_nofak || '');
                    $('[name="purc_datefak"]').val(header.purc_datefak ? header.purc_datefak.split(' ')[0] : '');
                    $('[name="catatan"]').val(header.catatan || '');
                    $('#purc_disc').val(formatRupiah(header.purc_disc || 0));
                    $('#purc_ppn').val(Math.round(parseFloat(header.purc_ppn || 0)));
                    activePurchaseReturnTotal = SarinahNumbers.fromApi(header.retur_total_active || 0);
                    $('#purc_retur_total').val(formatRupiah(activePurchaseReturnTotal));
                    $('#purchase-return-summary, #purchase-net-summary')
                        .toggleClass('d-none', activePurchaseReturnTotal <= 0);

                    // ===== SET SUPPLIER =====
                    if (header.supplier_id) {
                        // Clear existing selection first
                        $('#supplier_id').empty();

                        // Add new option and trigger change
                        const newOption = new Option(
                            header.supplier_nama || 'Supplier',
                            header.supplier_id,
                            true,
                            true
                        );
                        $('#supplier_id').append(newOption).trigger('change');

                        // Trigger change event untuk Select2
                        setTimeout(() => {
                            $('#supplier_id').val(header.supplier_id).trigger('change.select2');
                        }, 100);
                    }

                    // ===== SET JENIS TRANSAKSI =====
                    $('#bayar_id').val(header.bayar_id || '1').trigger('change');

                    // Set jatuh tempo jika kredit
                    if (header.bayar_id == 2 && header.purc_jt) {
                        $('#purc_jt').val(header.purc_jt.split(' ')[0]);
                        $('#purc_jt').prop('disabled', false);
                    } else {
                        $('#purc_jt').prop('disabled', true).val('');
                    }

                    // ===== SET DETAIL PRODUK =====
                    console.log('🔄 Setting product details:', details);
                    $('#produk_rows').empty();

                    if (details && details.length > 0) {
                        details.forEach((item, index) => {
                            console.log(`📝 Adding product row ${index + 1}:`, item);
                            addProductRow(item);
                        });
                    } else {
                        console.warn('⚠️ No product details found');
                        addProductRow(); // Tambah baris kosong sebagai fallback
                    }

                    // ===== HITUNG ULANG TOTAL =====
                    setTimeout(() => {
                        hitungGrandTotal();
                        console.log('✅ Pembelian data loaded successfully');

                        // Tampilkan modal setelah data selesai dimuat
                        $('#pembelianModal').modal('show');
                    }, 500);

                } else {
                    console.error('❌ Failed to load pembelian data:', resp.msg);
                    showMessage('Gagal memuat data pembelian: ' + (resp.msg || 'Unknown error'), 'danger', '#form-msg');
                    // Tetap tampilkan modal meski gagal load data
                    $('#pembelianModal').modal('show');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error loading pembelian data:', error);
                console.log('XHR:', xhr);
                showMessage('Gagal memuat data pembelian: ' + error, 'danger', '#form-msg');
                // Tetap tampilkan modal meski error
                $('#pembelianModal').modal('show');
            },
            complete: function() {
                end_loader();
                $('#pembelianModal .modal-body').removeClass('loading');
            }
        });
    }

    // function addProductRow(item = null) {
    //     const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 9);
    //     const row = `
    //     <tr id="${rowId}">
    //         <td>
    //             <select class="form-select form-select-sm produk-select" name="produk_id[]" required></select>
    //             <input type="hidden" class="is_ppn" name="is_ppn[]" value="0">
    //             <input type="hidden" class="harga_beli" name="harga_beli[]" value="0">
    //             <input type="hidden" class="satuan_id" name="satuan_id[]" value="0">
    //             <input type="hidden" class="isi" name="isi[]" value="1">
    //         </td>
    //         <td><input type="text" class="form-control form-control-sm text-center stok" readonly value="0"></td>
    //         <td><input type="number" step="1" min="1" class="form-control form-control-sm text-center qty" name="purc_qty[]" value="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57"></td>
    //         <td><input type="text" class="form-control form-control-sm text-end harga" name="purc_harga[]" value="0"></td>
    //         <td><input type="text" class="form-control form-control-sm text-end ppn" name="ppn[]" readonly value="0"></td>
    //         <td><input type="text" class="form-control form-control-sm text-end subtotal" readonly value="0"></td>
    //         <td class="text-center">
    //             <button type="button" class="btn btn-sm btn-danger del_row">
    //                 <i class="fa fa-trash"></i>
    //             </button>
    //         </td>
    //     </tr>`;

    //     $('#produk_rows').append(row);

    //     // Initialize Select2 untuk produk
    //     initSelect2Produk('#' + rowId);

    //     // Jika ada data item, set values setelah Select2 siap
    //     if (item) {
    //         setTimeout(() => {
    //             const tr = $('#' + rowId);
    //             console.log('🔄 Setting product row data:', item);

    //             // Set produk selection
    //             if (item.produk_id && item.nama_produk) {
    //                 tr.find('.produk-select').append(
    //                     new Option(item.nama_produk, item.produk_id, true, true)
    //                 ).trigger('change');
    //             }

    //             // Set other values
    //             tr.find('.stok').val(item.stok || 0);
    //             tr.find('.harga_beli').val(item.harga_beli || 0);
    //             tr.find('.harga').val(formatRupiah(item.purc_harga || 0));
    //             tr.find('.qty').val(item.purc_qty || 1);
    //             tr.find('.is_ppn').val(item.is_ppn || 0);
    //             tr.find('.satuan_id').val(item.satuan_id || 0);
    //             tr.find('.isi').val(item.isi || 1);
    //             tr.find('.ppn').val(formatRupiah(item.ppn || 0));

    //             // Hitung subtotal
    //             hitungSubtotal(tr);

    //         }, 300); // Delay untuk memastikan Select2 sudah terinisialisasi
    //     }
    // }

    function addProductRow(item = null) {
        const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 9);
        const row = `
    <tr id="${rowId}">
        <td>
            <select class="form-select form-select-sm produk-select" name="produk_id[]" required></select>
            <input type="hidden" class="is_ppn" name="is_ppn[]" value="0">
            <input type="hidden" class="ppn" name="ppn[]" value="0">
            <input type="hidden" class="harga_beli" name="harga_beli[]" value="0">
            <input type="hidden" class="satuan_id" name="satuan_id[]" value="0">
            <input type="hidden" class="isi" name="isi[]" value="1">
        </td>
        <td><input type="text" class="form-control form-control-sm text-center stok" readonly value="0"></td>
        <td>
            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-center qty" 
                   name="purc_qty[]" value="1">
        </td>
        <td><input type="text" class="form-control form-control-sm text-end harga" name="purc_harga[]" value="0"></td>
        <td><input type="text" class="form-control form-control-sm text-end subtotal" readonly value="0"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger del_row">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    </tr>`;

        $('#produk_rows').append(row);

        // Initialize Select2 untuk produk
        initSelect2Produk('#' + rowId);

        // Jika ada data item, set values setelah Select2 siap
        if (item) {
            setTimeout(() => {
                const tr = $('#' + rowId);
                console.log('🔄 Setting product row data:', item);

                // Set produk selection
                if (item.produk_id && item.nama_produk) {
                    tr.find('.produk-select').append(
                        new Option(item.nama_produk, item.produk_id, true, true)
                    ).trigger('change');
                }

                tr.find('.stok').val(formatPurchaseQuantity(item.stok ?? 0));
                tr.find('.harga_beli').val(item.harga_beli || 0);
                tr.find('.harga').val(formatRupiah(item.purc_harga || 0));
                tr.find('.qty').val(purchaseQuantityInput(item.purc_qty ?? 1));

                tr.find('.is_ppn').val(item.is_ppn || 0);
                tr.find('.satuan_id').val(item.satuan_id || 0);
                tr.find('.isi').val(item.isi || 1);
                tr.find('.ppn').val(Math.round(parseFloat(item.ppn || 0)));

                // Hitung subtotal
                hitungSubtotal(tr);

            }, 300);
        }
    }

    // Normalisasi nilai quantity saat pengguna selesai mengisi.
    $(document).on('blur', '.qty', function() {
        const qty = Math.max(0.0001, purchaseQuantityFromInput($(this).val()));
        $(this).val(purchaseQuantityInput(qty));

        const tr = $(this).closest('tr');
        hitungSubtotal(tr);
        hitungGrandTotal();
    });

    // Perbaiki fungsi hitungSubtotal untuk handle desimal
    function hitungSubtotal(tr) {
        const qty = purchaseQuantityFromInput(tr.find('.qty').val());
        const harga = parseRupiah(tr.find('.harga').val());
        const subtotal = qty * harga;
        tr.find('.subtotal').val(formatRupiah(subtotal));
    }

    function initSelect2Produk(selector) {
        $(selector).find('.produk-select').select2({
            placeholder: "Cari produk...",
            allowClear: true,
            dropdownParent: $('#pembelianModal'),
            ajax: {
                url: baseUrl + "?f=search_produk",
                dataType: "json",
                delay: 250,
                data: (params) => ({
                    term: params.term || ""
                }),
                processResults: (data) => ({
                    results: Array.isArray(data) ? data : []
                }),
                cache: true
            },
            templateResult: (item) => {
                if (!item.id) return item.text;
                const ppn = item.is_ppn == 1 ? `<span class='badge bg-success ms-2'>PPN</span>` : "";
                return $(`<div>
                    <div class="fw-bold">${escapeHtml(item.nama_produk)}${ppn}</div>
                    <small class="text-muted">${escapeHtml(item.barcode || "")} ${item.satuan ? " - " + escapeHtml(item.satuan) : ""}</small>
                    </div>`);
            },
            templateSelection: (item) => item.nama_produk || item.text,
            minimumInputLength: 0
        }).on('select2:select', function(e) {
            const data = e.params.data;
            const tr = $(this).closest('tr');
            tr.find('.harga').val(formatRupiah(data.harga_beli || 0));
            tr.find('.stok').val(formatPurchaseQuantity(data.stok ?? 0));
            tr.find('.is_ppn').val(data.is_ppn || 0);
            tr.find('.harga_beli').val(data.harga_beli || 0);
            hitungSubtotal(tr);
            hitungGrandTotal();
        });
    }

    /** ======================= [ CALCULATION FUNCTIONS ] ======================= **/
    const formatRupiah = value => SarinahNumbers.formatMoney(value);
    const parseRupiah = value => SarinahNumbers.parseMoney(value);

    const normalizePurchaseQuantity = value => {
        const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
        return Object.is(rounded, -0) ? 0 : rounded;
    };
    const purchaseQuantityFromInput = value => normalizePurchaseQuantity(value);
    const purchaseQuantityInput = value => normalizePurchaseQuantity(value)
        .toFixed(4)
        .replace(/\.?0+$/, '') || '0';
    const formatPurchaseQuantity = value => SarinahNumbers.formatQuantity(
        normalizePurchaseQuantity(value),
        { minimumFractionDigits: 0, maximumFractionDigits: 4 }
    );
    <?php
    $pembelianPpnRate = $_settings->info('ppn_rate');
    if ($pembelianPpnRate === false || $pembelianPpnRate === '' || !is_numeric($pembelianPpnRate)) {
        $pembelianPpnRate = 11;
    }
    $pembelianPpnRateDecimal = max(0, min(100, (float)$pembelianPpnRate)) / 100;
    ?>
    const PPN_RATE = <?= json_encode($pembelianPpnRateDecimal) ?>;

    // function hitungSubtotal(tr) {
    //     const qty = parseFloat(tr.find('.qty').val()) || 0;
    //     const harga = parseRupiah(tr.find('.harga').val());
    //     const subtotal = qty * harga;
    //     tr.find('.subtotal').val(formatRupiah(subtotal));
    // }

    function hitungGrandTotal() {
        let total = 0;
        let totalPPN = 0;

        // Hitung total dari semua subtotal
        $('#produk_rows tr').each(function() {
            const subtotal = parseRupiah($(this).find('.subtotal').val());
            total += subtotal;
        });

        // Hitung diskon
        const disc = parseRupiah($('#purc_disc').val());
        const totalAfterDisc = Math.max(total - disc, 0);

        // PPN dihitung otomatis dari ceklis PPN di master barang, tidak ditampilkan di form.
        $('#produk_rows tr').each(function() {
            const tr = $(this);
            const subtotal = parseRupiah(tr.find('.subtotal').val());
            const isPPN = parseInt(tr.find('.is_ppn').val()) || 0;

            if (isPPN === 1) {
                const ppnItem = Math.round(subtotal * PPN_RATE);
                totalPPN += ppnItem;
                tr.find('.ppn').val(ppnItem);
            } else {
                tr.find('.ppn').val(0);
            }
        });

        const grandTotal = totalAfterDisc + totalPPN;

        $('#purc_ppn').val(Math.round(totalPPN));
        $('#grand_total').val(formatRupiah(grandTotal));

        // Nilai invoice asli tetap dipertahankan sebagai jejak pembelian.
        // Ringkasan ini menampilkan nilai bersih barang setelah seluruh retur aktif.
        const netTotal = Math.max(0, grandTotal - activePurchaseReturnTotal);
        $('#purc_net_total').val(formatRupiah(netTotal));
    }

    function validasiHarga(tr) {
        const hargaInput = tr.find('.harga');
        const harga = parseRupiah(hargaInput.val());
        const hargaBeli = parseFloat(tr.find('.harga_beli').val()) || 0;

        if (harga < hargaBeli && hargaBeli > 0) {
            hargaInput.addClass('shake-input');

            Swal.fire({
                icon: "warning",
                title: "Harga Terlalu Rendah!",
                html: `Harga beli <b>${formatRupiah(harga)}</b> lebih rendah dari harga standar <b>${formatRupiah(hargaBeli)}</b>`,
                timer: 2500,
                showConfirmButton: false,
                didClose: () => {
                    setTimeout(() => {
                        hargaInput.focus().select();
                        hargaInput.removeClass('shake-input');
                    }, 150);
                },
            });

            hargaInput.val(formatRupiah(hargaBeli));
            return false;
        }
        return true;
    }

    /** ======================= [ FORM VALIDATION & SUBMISSION ] ======================= **/
    function validateForm() {
        const supplierId = $('#supplier_id').val();
        const purcDate = $('[name="purc_date"]').val();

        if (!supplierId) {
            showMessage('Pilih supplier terlebih dahulu', 'danger', '#form-msg');
            $('#supplier_id').focus();
            return false;
        }

        if (!purcDate) {
            showMessage('Tanggal faktur harus diisi', 'danger', '#form-msg');
            $('[name="purc_date"]').focus();
            return false;
        }

        let hasValidProducts = false;
        $('#produk_rows tr').each(function() {
            const produkId = $(this).find('.produk-select').val();
            const qtyInput = $(this).find('.qty');
            const qty = purchaseQuantityFromInput(qtyInput.val());
            const harga = parseRupiah($(this).find('.harga').val());

            qtyInput.val(purchaseQuantityInput(qty));

            if (produkId && qty > 0 && harga > 0) {
                hasValidProducts = true;
            }
        });

        if (!hasValidProducts) {
            showMessage('Tambahkan minimal satu produk dengan quantity dan harga yang valid', 'danger', '#form-msg');
            return false;
        }

        const grandTotal = parseRupiah($('#grand_total').val());
        if (grandTotal <= 0) {
            showMessage('Total pembelian harus lebih dari 0', 'danger', '#form-msg');
            return false;
        }

        return true;
    }

    function submitForm() {
        if (!validateForm()) return;

        start_loader();
        const formData = new FormData($('#pembelian-form')[0]);

        $.ajax({
            url: baseUrl + "?f=" + (currentPurcId ? 'update_pembelian' : 'save_pembelian'),
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
                        $('#pembelianModal').modal('hide');
                        forceReloadTable();
                    }, 1500);
                } else {
                    showMessage(resp.msg || 'Gagal menyimpan data', 'danger', '#form-msg');
                }
            },
            error: function(xhr, status, error) {
                end_loader();
                console.error('AJAX Error:', error);
                showMessage('Terjadi kesalahan koneksi ke server: ' + error, 'danger', '#form-msg');
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
        const purcid = row.purcid;
        const invoice = escapeHtml(row.purc_invoice || '');
        const status = row.purc_status;

        let buttons = `
            <div class="btn-group" role="group">
                <button class="btn btn-icon btn-light-info btn-sm me-1 print_data" 
                    data-id="${purcid}" data-invoice="${invoice}" title="Lihat / Print Invoice">
                    <i class="fa fa-print"></i>
                </button>
        `;

        <?php if ($_settings->userdata('level_id') < 2): ?>
            if (isPembelianActive(status)) {
                buttons += `
                <button class="btn btn-icon btn-light-primary btn-sm me-1 edit_data" 
                    data-id="${purcid}" data-invoice="${invoice}" title="Edit Data">
                    <i class="fa fa-edit"></i>
                </button>
            `;
            }

            if (!isPembelianDeleted(status)) {
                buttons += `
                <button class="btn btn-icon btn-light-danger btn-sm me-1 delete_data"
                    data-id="${purcid}" data-invoice="${invoice}" title="Hapus Transaksi">
                    <i class="fa fa-trash"></i>
                </button>
            `;
            }
        <?php endif; ?>

        buttons += `</div>`;
        return buttons;
    }

    /** ======================= [ DELETE FUNCTION ] ======================= **/
    function deleteData(purcid) {
        start_loader();
        console.log('Starting DELETE for purcid:', purcid);

        $.ajax({
            url: baseUrl + "?f=delete_pembelian",
            method: "POST",
            data: {
                purcid: purcid
            },
            dataType: "json",
            timeout: 30000,
            success: function(resp) {
                console.log('DELETE Response:', resp);

                if (resp && resp.status === "success") {
                    show_msg("Transaksi berhasil dihapus", "success");
                    $('#confirm_modal').modal('hide');
                    setTimeout(() => {
                        forceReloadTable();
                    }, 300);

                } else {
                    const msg = resp?.msg || "Gagal menghapus transaksi.";
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
            .off('click', '.print_data')
            .off('click', '.edit_data')
            .off('click', '.delete_data')
            .on('click', '.print_data', function() {
                const id = $(this).data('id');
                if (!id) {
                    console.error('ID tidak valid untuk print');
                    return;
                }
                window.open('<?php echo base_url ?>admin/pembelian/pembelian_print.php?id=' + encodeURIComponent(id), '_blank');
            })
            .on('click', '.edit_data', function() {
                const id = $(this).data('id');
                if (!id) {
                    Swal.fire("Error", "ID transaksi tidak valid", "error");
                    return;
                }
                openPembelianModal(id);
            })
            .on('click', '.delete_data', function() {
                const purcid = $(this).data('id');
                const invoice = $(this).data('invoice');
                if (!purcid) {
                    Swal.fire("Error", "ID transaksi tidak valid", "error");
                    return;
                }
                _conf(`Yakin ingin menghapus transaksi <b>${escapeHtml(invoice)}</b>?`, "deleteData", [purcid]);
            });
    }

    $(document).ready(function() {
        // Initialize DataTables
        load_data();

        // Modal events
        $('#btn_add').click(() => openPembelianModal());

        $('#submit-pembelian').click(submitForm);

        $('#add_row').click(() => addProductRow());

        $(document).on('click', '.del_row', function() {
            if ($('#produk_rows tr').length > 1) {
                $(this).closest('tr').remove();
                hitungGrandTotal();
            } else {
                showMessage('Minimal harus ada satu produk', 'warning', '#form-msg');
            }
        });

        $(document).on('input', '.qty, .harga', function() {
            const tr = $(this).closest('tr');
            hitungSubtotal(tr);
            hitungGrandTotal();
        });

        $(document).on('blur', '.harga', function() {
            const tr = $(this).closest('tr');
            const val = parseRupiah($(this).val());
            $(this).val(formatRupiah(val));
            validasiHarga(tr);
            hitungSubtotal(tr);
            hitungGrandTotal();
        });

        $(document).on('input', '#purc_disc', function() {
            const val = parseRupiah($(this).val());
            $(this).val(formatRupiah(val));
            hitungGrandTotal();
        });

        $('#bayar_id').change(function() {
            const jenis = $(this).val();
            if (jenis === '2') {
                $('#purc_jt').prop('disabled', false);
                // Set jatuh tempo default 30 hari dari sekarang
                const jtDate = new Date();
                jtDate.setDate(jtDate.getDate() + 30);
                $('#purc_jt').val(jtDate.toISOString().split('T')[0]);
            } else {
                $('#purc_jt').prop('disabled', true).val('');
            }
        });

        // Initialize Select2 untuk supplier di modal
        $('#supplier_id').select2({
            placeholder: 'Cari nama supplier...',
            allowClear: true,
            dropdownParent: $('#pembelianModal'),
            ajax: {
                url: baseUrl + "?f=search_supplier",
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term || ''
                }),
                processResults: data => ({
                    results: data.map(item => ({
                        id: item.id,
                        text: `${item.nama}${item.alamat ? ' — ' + item.alamat : ''}`
                    }))
                }),
                cache: true
            },
            minimumInputLength: 2
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
            $('#tgl_awal').val('<?php echo date('Y-01-01') ?>');
            $('#tgl_akhir').val('<?php echo date('Y-m-d') ?>');
            $('#jenis').val('');
            $('#supplier_filter').val('').trigger('change');
            setTimeout(() => {
                forceReloadTable();
                setTimeout(() => end_loader(), 1000);
            }, 300);
        });

        $('#export-btn').click(function() {
            const tgl_awal = $('#tgl_awal').val();
            const tgl_akhir = $('#tgl_akhir').val();
            const jenis = $('#jenis').val();
            const supplier_id = $('#supplier_filter').val();
            const url = baseUrl + `?f=export_excel&tgl_awal=${tgl_awal}&tgl_akhir=${tgl_akhir}&jenis=${jenis}&supplier_id=${supplier_id}`;
            window.open(url, '_blank');
        });

        // Focus management
        $('#pembelianModal, #confirm_modal').on('hide.bs.modal', function() {
            setTimeout(() => {
                $('#btn_add').focus();
            }, 10);
        });

    });

    // [DataTables functions - tetap sama]
    /** ======================= [ LOAD DATA TABLE ] ======================= **/
    function load_data() {
        if ($.fn.DataTable.isDataTable('#table')) {
            pembelianDT.clear().destroy();
            $('.dataTables_processing').remove();
        }

        pembelianDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            // stateSave: true,
            // stateDuration: -1,
            // searchDelay: 500,
            stateSaveCallback: function(settings, data) {
                localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data));
            },
            stateLoadCallback: function(settings) {
                return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance) || '{}');
            },
            ajax: {
                url: baseUrl + "?f=get_data_pembelian",
                type: "POST",
                data: function(d) {
                    // Extend default DataTables parameters with custom filters
                    d.tgl_awal = $('#tgl_awal').val();
                    d.tgl_akhir = $('#tgl_akhir').val();
                    d.jenis = $('#jenis').val();
                    d.supplier_id = $('#supplier_filter').val();
                    return d;
                },
                dataSrc: function(json) {
                    setTimeout(() => {
                        $('.dataTables_processing').fadeOut();
                    }, 100);

                    if (json && json.data) {
                        return json.data;
                    } else {
                        console.error('Invalid response format:', json);
                        // Tampilkan pesan error ke user
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

            columns: [{
                    data: "no",
                    className: "text-center fw-bold",
                    orderable: false,
                    searchable: false
                },
                {
                    data: "purc_date",
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
                            <div class="fw-bold">${escapeHtml(row.purc_invoice)}</div>
                            <small class="text-muted">${escapeHtml(row.purc_nofak || '')}</small>
                        `;
                    }
                },
                {
                    data: "bayar_id",
                    className: "text-center",
                    render: function(data, type, row) {
                        let jenis = 'Lainnya';
                        let warna = 'secondary';
                        if (data == 1) {
                            jenis = 'Tunai';
                            warna = 'success';
                        } else if (data == 2) {
                            jenis = 'Kredit';
                            warna = 'warning';
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
                    data: "purc_total",
                    className: "text-end fw-bold",
                    render: function(data, type, row) {
                        const value = parseFloat(data) || 0;
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                },
                {
                    data: "purc_status",
                    className: "text-center",
                    render: function(data, type, row) {
                        let status = 'AKTIF';
                        let warna = 'success';
                        if (isPembelianDeleted(data)) {
                            status = 'DIHAPUS';
                            warna = 'secondary';
                        } else if (isPembelianVoid(data)) {
                            status = 'DIBATALKAN';
                            warna = 'danger';
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
            order: [
                [1, 'desc']
            ],
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
                        '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data pembelian</td></tr>'
                    );
                }
            }
        });

        return pembelianDT;
    }

    function reloadDataTable() {
        if (typeof pembelianDT !== 'undefined' && $.fn.DataTable.isDataTable('#table')) {
            try {
                console.log('Reloading DataTable...');
                pembelianDT.ajax.reload(function(json) {
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
                    pembelianDT.clear().destroy();
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
        // Initialize DataTables
        load_data();

        // Attach event handlers after table is loaded
        setTimeout(() => {
            attachEventHandlers();
        }, 1000);

        // Inisialisasi Select2 supplier filter
        $('#supplier_filter').select2({
            placeholder: 'Cari supplier...',
            allowClear: true,
            width: 'resolve',
            ajax: {
                url: '<?php echo base_url ?>classes/Pembelian.php?f=search_supplier',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term || ''
                }),
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
