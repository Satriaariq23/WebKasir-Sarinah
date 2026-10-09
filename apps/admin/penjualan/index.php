<?php
$currentLevelId = current_user_level();
$canCreatePenjualan = in_array($currentLevelId, [1, 3, 4], true);
$editSaleId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$currentUserId = (int)($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
$salesmen = [];
$ppnRateSetting = $_settings->info('ppn_rate');
if ($ppnRateSetting === false || $ppnRateSetting === '' || !is_numeric($ppnRateSetting)) {
    $ppnRateSetting = 11;
}
$ppnRateDecimal = max(0, min(100, (float)$ppnRateSetting)) / 100;

if (isset($conn) && $conn instanceof mysqli) {
    $salesSql = "SELECT id, firstname, username FROM users WHERE level_id = 3 AND status = 1";
    if ($currentLevelId === 3) {
        $salesSql .= " AND id = " . $currentUserId;
    }
    $salesSql .= " ORDER BY firstname ASC";

    if ($salesQry = $conn->query($salesSql)) {
        while ($row = $salesQry->fetch_assoc()) {
            $salesmen[] = $row;
        }
        $salesQry->free();
    }
}
?>
<!-- Modal Full Screen untuk Input Penjualan -->
<div class="modal fade" id="penjualanModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="penjualanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="penjualanModalLabel">
                    <i class="bi bi-cart-check me-2"></i>Form Penjualan Sales
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="penjualan-form">
                    <input type="hidden" name="saleid" value="">

                    <!-- Header Form -->
                    <div class="row mb-4">
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label required">Tanggal Faktur</label>
                            <input type="date" class="form-control" name="sale_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label required">Jenis Transaksi</label>
                            <select name="bayar_id" id="bayar_id" class="form-select" required>
                                <option value="1">Tunai</option>
                                <option value="2">Kredit</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label required">Kanal Penjualan</label>
                            <select name="sale_channel" id="sale_channel" class="form-select" required>
                                <option value="sales">Sales</option>
                                <option value="online">Sales Online</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Jatuh Tempo</label>
                            <div class="input-group">
                                <input type="number" name="sale_jt_days" id="sale_jt_days" class="form-control" min="1" max="3650" value="30" disabled aria-label="Tempo hari">
                                <span class="input-group-text">hari</span>
                                <input type="date" name="sale_jt" id="sale_jt" class="form-control" disabled aria-label="Tanggal jatuh tempo">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">No. Invoice (Auto)</label>
                            <input type="text" class="form-control" name="sale_invoice" readonly placeholder="Auto Generate">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label required">Sales</label>
                            <select name="sales_id" id="sales_id" class="form-select" style="width: 100%;" required>
                                <option value="">Pilih sales...</option>
                                <?php foreach ($salesmen as $salesman): ?>
                                    <option value="<?= (int)$salesman['id'] ?>" <?= $currentLevelId === 3 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($salesman['firstname'] ?: $salesman['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Pelanggan</label>
                            <select name="cust_id" id="cust_id" class="form-select" style="width: 100%;" required></select>
                        </div>
                        <div class="col-md-4">
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
                                    <th style="width: 8%">Stok</th>
                                    <th style="width: 8%">Qty</th>
                                    <th style="width: 12%">Harga</th>
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
                                    <td><input type="text" id="sale_disc" name="sale_disc" class="form-control text-end" value="0"></td>
                                    <td></td>
                                </tr>
                                <tr class="d-none">
                                    <td colspan="6"><input type="hidden" readonly id="sale_ppn" name="sale_ppn" value="0"></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Grand Total (Rp)</td>
                                    <td><input type="text" readonly id="grand_total" name="sale_total" class="form-control text-end fw-bold" value="0"></td>
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
                <button type="button" class="btn btn-success" id="submit-penjualan">
                    <i class="bi bi-save"></i> Simpan Penjualan
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
                        <span class="text-dark fw-bolder">Penjualan</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Daftar / Riwayat Penjualan</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Input Faktur Penjualan Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if ($canCreatePenjualan): ?>
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
                                <label class="form-label">Pelanggan</label>
                                <select id="pelanggan_id" name="pelanggan_id" class="form-select" style="width: 100%;"></select>
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
                                    <th class="text-center">Sales</th>
                                    <th class="text-center">Jenis Transaksi</th>
                                    <th class="text-center">Pelanggan</th>
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
    const baseUrl = "../classes/Penjualan.php";
    const currentLevelId = <?php echo (int)$currentLevelId ?>;
    const currentUserId = <?php echo (int)$currentUserId ?>;
    const requestedEditSaleId = <?php echo (int)$editSaleId ?>;
    const autoOpenPenjualanForm = currentLevelId === 3 && requestedEditSaleId <= 0;

    // Helper aman untuk HTML
    const escapeHtml = text => $('<div>').text(text || '').html();

    let salesDT;
    let currentSaleId = null;
    let minusPassword = '';

    /** ======================= [ MODAL FUNCTIONS ] ======================= **/
    function openPenjualanModal(saleid = null) {
        currentSaleId = saleid;

        // Reset form terlebih dahulu
        resetForm();

        if (saleid) {
            $('#penjualanModalLabel').html('<i class="bi bi-cart-check me-2"></i>Edit Penjualan');
            // Tampilkan loading state
            $('#penjualanModal .modal-body').addClass('loading');
            loadSaleData(saleid);
        } else {
            $('#penjualanModalLabel').html('<i class="bi bi-cart-check me-2"></i>Tambah Penjualan Baru');
            generateAutoInvoice();
            // Langsung tampilkan modal untuk tambah baru
            $('#penjualanModal').modal('show');
        }
    }
    // function openPenjualanModal(saleid = null) {
    //     currentSaleId = saleid;
    //     // Reset form terlebih dahulu
    //     resetForm();

    //     if (saleid) {
    //         loadSaleData(saleid);
    //         $('#penjualanModalLabel').html('<i class="bi bi-cart-check me-2"></i>Edit Penjualan');
    //     } else {
    //         $('#penjualanModalLabel').html('<i class="bi bi-cart-check me-2"></i>Tambah Penjualan Baru');
    //         generateAutoInvoice();
    //     }

    //     $('#penjualanModal').modal('show');
    // }

    /** ======================= [ IMPROVED RESET FORM ] ======================= **/
    function syncDueDateFromDays() {
        if ($('#bayar_id').val() !== '2') return;

        const saleDateValue = $('[name="sale_date"]').val();
        const days = parseInt($('#sale_jt_days').val() || '0', 10);
        if (!saleDateValue || !Number.isFinite(days) || days <= 0) {
            $('#sale_jt').val('');
            return;
        }

        const dueDate = new Date(saleDateValue + 'T00:00:00');
        dueDate.setDate(dueDate.getDate() + days);
        const year = dueDate.getFullYear();
        const month = String(dueDate.getMonth() + 1).padStart(2, '0');
        const day = String(dueDate.getDate()).padStart(2, '0');
        $('#sale_jt').val(`${year}-${month}-${day}`);
    }

    function resetForm() {
        console.log('🔄 Resetting form...');

        // Reset form values
        $('#penjualan-form')[0].reset();

        // Clear dynamic rows
        $('#produk_rows').empty();

        // Reset values
        $('#sale_disc').val('0');
        $('#sale_ppn').val('0');
        $('#grand_total').val('0');
        $('[name="saleid"]').val('');
        minusPassword = '';

        // Reset Select2 elements
        $('#cust_id').val(null).trigger('change');
        $('#sales_id').val(currentLevelId === 3 ? String(currentUserId) : '').trigger('change');

        // Clear messages
        $('#form-msg').empty();

        // Reset jenis transaksi
        $('#bayar_id').val('1').trigger('change');
        $('#sale_channel').val('sales');
        $('#sale_jt_days').prop('disabled', true).val('30');
        $('#sale_jt').prop('disabled', true).val('');

        // Set default date
        $('[name="sale_date"]').val('<?= date('Y-m-d') ?>');

        // Tambah baris pertama untuk form baru
        if (!currentSaleId) {
            addProductRow();
        }

        console.log('✅ Form reset completed');
    }

    function generateAutoInvoice() {
        // Use fetch with modern async/await instead of synchronous AJAX
        fetch(baseUrl + "?f=generate_invoice")
            .then(response => response.json())
            .then(resp => {
                if (resp.status === 'success') {
                    $('[name="sale_invoice"]').val(resp.invoice);
                    console.log('✅ Invoice generated from server:', resp.invoice);
                } else {
                    console.error('❌ Failed to generate invoice:', resp.msg);
                    throw new Error(resp.msg);
                }
            })
            .catch(error => {
                console.error('❌ Error generating invoice:', error);
                // Fallback to client-side generation with correct format
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                const invoice = `INV-${year}${month}${day}-${random}`;
                $('[name="sale_invoice"]').val(invoice);
                console.log('✅ Invoice generated from fallback:', invoice);
            });
    }

    /** ======================= [ LOAD SALE DATA - IMPROVED ] ======================= **/
    function loadSaleData(saleid) {
        console.log('📥 Loading sale data for ID:', saleid);
        start_loader();

        // Clear existing data first
        resetForm();

        $.ajax({
            url: baseUrl + "?f=get_penjualan_by_id",
            method: "GET",
            data: {
                id: saleid
            },
            dataType: "json",
            success: function(resp) {
                console.log('📦 Sale data response:', resp);

                if (resp.status === "success") {
                    const header = resp.header;
                    const details = resp.details;

                    console.log('🔄 Setting header data:', header);

                    // ===== SET HEADER DATA =====
                    $('[name="saleid"]').val(header.saleid || '');
                    $('[name="sale_date"]').val(header.sale_date ? header.sale_date.split(' ')[0] : '<?= date('Y-m-d') ?>');
                    $('[name="sale_invoice"]').val(header.sale_invoice || '');
                    $('[name="sale_nofak"]').val(header.sale_nofak || '');
                    $('[name="catatan"]').val(header.catatan || '');
                    $('#sales_id').val(header.user_id || '').trigger('change');
                    $('#sale_channel').val(header.sale_channel || 'sales');
                    // ✅ FIXED: Set nilai RAW numerik, bukan formatted
                    $('#sale_disc').val(parseRupiah(header.sale_disc || 0));
                    $('#sale_ppn').val(parseRupiah(header.sale_ppn || 0));

                    // ===== SET PELANGGAN =====
                    if (header.cust_id) {
                        // Clear existing selection first
                        $('#cust_id').empty();

                        // Add new option and trigger change
                        const newOption = new Option(
                            header.pelanggan_nama || 'Pelanggan',
                            header.cust_id,
                            true,
                            true
                        );
                        $('#cust_id').append(newOption).trigger('change');

                        // Trigger change event untuk Select2
                        setTimeout(() => {
                            $('#cust_id').val(header.cust_id).trigger('change.select2');
                        }, 100);
                    }

                    // ===== SET JENIS TRANSAKSI =====
                    $('#bayar_id').val(header.bayar_id || '1').trigger('change');

                    // Set jatuh tempo jika kredit
                    if (header.bayar_id == 2 && header.sale_jt) {
                        $('#sale_jt').val(header.sale_jt.split(' ')[0]);
                        $('#sale_jt').prop('disabled', false);
                        const saleDate = new Date((header.sale_date || '').split(' ')[0] + 'T00:00:00');
                        const dueDate = new Date(header.sale_jt.split(' ')[0] + 'T00:00:00');
                        const diffDays = Math.max(1, Math.round((dueDate - saleDate) / 86400000));
                        $('#sale_jt_days').prop('disabled', false).val(diffDays);
                    } else {
                        $('#sale_jt_days').prop('disabled', true).val('30');
                        $('#sale_jt').prop('disabled', true).val('');
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
                        console.log('✅ Sale data loaded successfully');

                        // Tampilkan modal setelah data selesai dimuat
                        $('#penjualanModal').modal('show');
                    }, 500);

                } else {
                    console.error('❌ Failed to load sale data:', resp.msg);
                    showMessage('Gagal memuat data penjualan: ' + (resp.msg || 'Unknown error'), 'danger', '#form-msg');
                    // Tetap tampilkan modal meski gagal load data
                    $('#penjualanModal').modal('show');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error loading sale data:', error);
                console.log('XHR:', xhr);
                showMessage('Gagal memuat data penjualan: ' + error, 'danger', '#form-msg');
                // Tetap tampilkan modal meski error
                $('#penjualanModal').modal('show');
            },
            complete: function() {
                end_loader();
                $('#penjualanModal .modal-body').removeClass('loading');
            }
        });
    }

    /** ======================= [ IMPROVED ADD PRODUCT ROW ] ======================= **/
    function addProductRow(item = null) {
        const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 9);
        const row = `
        <tr id="${rowId}">
            <td>
                <select class="form-select form-select-sm produk-select" name="produk_id[]" required></select>
                <input type="hidden" class="is_ppn" name="is_ppn[]" value="0">
                <input type="hidden" class="ppn" name="ppn[]" value="0">
                <input type="hidden" class="harga_newmodal" name="harga_newmodal[]" value="0">
                <input type="hidden" class="satuan_id" name="satuan_id[]" value="0">
                <input type="hidden" class="isi" name="isi[]" value="1">
                <input type="hidden" class="sale_capital" name="sale_capital[]" value="0">
            </td>
            <td><input type="text" class="form-control form-control-sm text-center stok" readonly value="0"></td>
            <td><input type="number" step="0.0001" class="form-control form-control-sm text-center qty" name="qty[]" value="1"></td>
            <td><input type="text" class="form-control form-control-sm text-end harga" name="harga[]" value="0"></td>
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

                // Set other values
                tr.find('.stok').val(formatSaleQuantity(item.stok ?? 0));
                tr.find('.harga_newmodal').val(item.harga_modal || item.sale_capital || 0);
                tr.find('.sale_capital').val(item.sale_capital || 0);
                // ✅ FIXED: Set nilai RAW numerik, bukan formatted
                const hargaRaw = parseRupiah(item.sale_harga || 0);
                tr.find('.harga').val(hargaRaw);
                tr.find('.qty').val(saleQuantityInput(item.sale_qty ?? 1));
                tr.find('.is_ppn').val(item.is_ppn || 0);
                tr.find('.satuan_id').val(item.satuan_id || 0);
                tr.find('.isi').val(item.isi || 1);
                // ✅ FIXED: Set PPN dengan nilai raw
                const ppnRaw = parseRupiah(item.ppn_item || 0);
                tr.find('.ppn').val(ppnRaw);

                // Hitung subtotal dan grand total
                hitungSubtotal(tr);
                hitungGrandTotal(); // Pastikan PPN dihitung ulang!

            }, 300); // Delay untuk memastikan Select2 sudah terinisialisasi
        }
    }

    /** ======================= [ IMPROVED SELECT2 INIT ] ======================= **/
    function initSelect2Produk(selector) {
        $(selector).find('.produk-select').select2({
            placeholder: "Cari produk...",
            allowClear: true,
            dropdownParent: $('#penjualanModal'),
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
            // ✅ FIXED: Set nilai RAW numerik, bukan formatted
            tr.find('.harga').val(data.harga_sales || 0);
            tr.find('.stok').val(formatSaleQuantity(data.stok ?? 0));
            tr.find('.is_ppn').val(data.is_ppn || 0);
            tr.find('.harga_newmodal').val(data.harga_modal || 0);
            tr.find('.sale_capital').val(data.harga_modal || 0);
            hitungSubtotal(tr);
            hitungGrandTotal();
        });
    }

    /** ======================= [ CALCULATION FUNCTIONS ] ======================= **/
    // ✅ FIXED: Fungsi yang lebih aman menangani semua format input
    const formatRupiah = value => SarinahNumbers.formatMoney(value);
    const parseRupiah = value => SarinahNumbers.parseMoney(value);
    const normalizeSaleQuantity = value => {
        const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
        return Object.is(rounded, -0) ? 0 : rounded;
    };
    const saleQuantityFromInput = value => normalizeSaleQuantity(value);
    const saleQuantityInput = value => normalizeSaleQuantity(value)
        .toFixed(4)
        .replace(/\.?0+$/, '') || '0';
    const formatSaleQuantity = value => SarinahNumbers.formatQuantity(
        normalizeSaleQuantity(value),
        { minimumFractionDigits: 0, maximumFractionDigits: 4 }
    );
    const PPN_RATE = <?= json_encode($ppnRateDecimal) ?>;
    const ppnFromGrossAmount = (grossAmount, isPpn) => {
        const gross = parseFloat(grossAmount || 0);
        return Number(isPpn || 0) === 1 ? gross - (gross / (1 + PPN_RATE)) : 0;
    };
    const normalizeDiscount = (amount, discount) => {
        const numericAmount = Number(amount || 0);
        const numericDiscount = Math.max(0, Number(discount || 0));
        return Math.min(numericDiscount, Math.abs(numericAmount));
    };
    const applySignedDiscount = (amount, discount) => {
        const numericAmount = Number(amount || 0);
        const appliedDiscount = normalizeDiscount(numericAmount, discount);
        return numericAmount < 0
            ? numericAmount + appliedDiscount
            : numericAmount - appliedDiscount;
    };

    /** ======================= [ IMPROVED CALCULATION ] ======================= **/
    function hitungSubtotal(tr) {
        const qty = saleQuantityFromInput(tr.find('.qty').val());
        const harga = parseRupiah(tr.find('.harga').val());
        const subtotal = qty * harga;
        // ✅ FIXED: Set nilai RAW numerik, bukan formatted
        tr.find('.subtotal').val(subtotal);
    }

    function hitungGrandTotal() {
        let total = 0;
        let totalPPN = 0;
        let grandTotal = 0;
        console.log('=== HITUNG GRAND TOTAL DIMULAI ===');

        // Hitung total dari semua subtotal
        $('#produk_rows tr').each(function() {
            const subtotal = parseRupiah($(this).find('.subtotal').val());
            total += subtotal;
        });

        // Hitung diskon
        const requestedDisc = Math.max(0, parseRupiah($('#sale_disc').val()));
        const disc = normalizeDiscount(total, requestedDisc);
        if (disc !== requestedDisc) {
            $('#sale_disc').val(disc);
        }
        const discountedTotal = applySignedDiscount(total, disc);
        const invoiceAdjustment = discountedTotal - total;

        console.log('Total Subtotal:', total, '| Diskon:', disc);

        // Hitung PPN per item (dengan proporsi diskon) dan Grand Total
        $('#produk_rows tr').each(function() {
            const tr = $(this);
            const subtotal = parseRupiah(tr.find('.subtotal').val());
            const isPPN = parseInt(tr.find('.is_ppn').val()) || 0;
            const produkId = tr.find('[name="produk_id[]"]').val();

            // Hitung proporsi diskon untuk item ini
            const proporsi = total !== 0 ? subtotal / total : 0;
            const subtotalAfterDisc = subtotal + (invoiceAdjustment * proporsi);

            console.log(`Item ${produkId}: Subtotal=${subtotal}, IsPPN=${isPPN}, AfterDisc=${subtotalAfterDisc}`);

            if (isPPN === 1) {
                // PPN sudah termasuk di harga barang, hanya dicatat komponennya.
                const ppnItem = ppnFromGrossAmount(subtotalAfterDisc, isPPN);
                totalPPN += ppnItem;
                // ✅ FIXED: Set nilai RAW numerik, bukan formatted
                tr.find('.ppn').val(Math.round(ppnItem));
                console.log(`  ➜ PPN Calculated: ${ppnItem} -> Stored: ${Math.round(ppnItem)}`);

                // Grand total tidak ditambah PPN lagi.
                grandTotal += subtotalAfterDisc;
            } else {
                // ✅ FIXED: Set nilai RAW numerik, bukan formatted
                tr.find('.ppn').val(0);
                console.log(`  ➜ No PPN (isPPN=0)`);

                // Tambah ke grand total TANPA PPN
                grandTotal += subtotalAfterDisc;
            }
        });

        // ✅ FIXED: Set nilai RAW numerik ke input, bukan formatted
        $('#sale_ppn').val(Math.round(totalPPN));
        $('#grand_total').val(Math.round(grandTotal));

        console.log(`Final: Total PPN=${Math.round(totalPPN)}, Grand Total=${Math.round(grandTotal)}`);
        console.log('=== HITUNG GRAND TOTAL SELESAI ===\n');
    }

    function validasiHarga(tr) {
        const hargaInput = tr.find('.harga');
        const harga = parseRupiah(hargaInput.val());
        const hargaModal = parseFloat(tr.find('.harga_newmodal').val()) || 0;

        if (harga < hargaModal && hargaModal > 0) {
            hargaInput.addClass('shake-input');

            Swal.fire({
                icon: "warning",
                title: "Harga Terlalu Rendah!",
                html: `Harga jual <b>${formatRupiah(harga)}</b> lebih rendah dari modal <b>${formatRupiah(hargaModal)}</b>`,
                timer: 2500,
                showConfirmButton: false,
                didClose: () => {
                    setTimeout(() => {
                        hargaInput.focus().select();
                        hargaInput.removeClass('shake-input');
                    }, 150);
                },
            });

            // ✅ FIXED: Set nilai RAW numerik, bukan formatted
            hargaInput.val(parseInt(hargaModal));
            return false;
        }
        return true;
    }

    /** ======================= [ FORM VALIDATION & SUBMISSION ] ======================= **/
    function hasMinusItems() {
        let found = false;
        $('#produk_rows tr').each(function() {
            const qty = saleQuantityFromInput($(this).find('.qty').val());
            if (qty < 0) {
                found = true;
                return false;
            }
        });
        return found;
    }

    function requiresMinusPassword() {
        return hasMinusItems() || parseRupiah($('#grand_total').val() || '0') < 0;
    }

    function requestMinusPassword() {
        if (minusPassword) return true;

        const password = window.prompt('Masukkan password Owner/Admin/Supervisor untuk quantity minus');
        if (!password) {
            showMessage('Password wajib diisi untuk menyimpan penjualan sales dengan quantity minus', 'warning', '#form-msg');
            return false;
        }

        minusPassword = password;
        return true;
    }

    function validateForm() {
        const salesId = $('#sales_id').val();
        const custId = $('#cust_id').val();
        const saleDate = $('[name="sale_date"]').val();
        const saleChannel = $('#sale_channel').val();

        if (!['sales', 'online'].includes(saleChannel)) {
            showMessage('Pilih kanal Sales atau Sales Online', 'danger', '#form-msg');
            return false;
        }

        if (!salesId) {
            showMessage('Pilih sales terlebih dahulu', 'danger', '#form-msg');
            $('#sales_id').focus();
            return false;
        }

        if (!custId) {
            showMessage('Pilih pelanggan terlebih dahulu', 'danger', '#form-msg');
            $('#cust_id').focus();
            return false;
        }

        if (!saleDate) {
            showMessage('Tanggal faktur harus diisi', 'danger', '#form-msg');
            $('[name="sale_date"]').focus();
            return false;
        }

        let hasValidProducts = false;
        $('#produk_rows tr').each(function() {
            const produkId = $(this).find('.produk-select').val();
            const qtyInput = $(this).find('.qty');
            const qty = saleQuantityFromInput(qtyInput.val());
            const harga = parseRupiah($(this).find('.harga').val());

            qtyInput.val(saleQuantityInput(qty));

            if (produkId && qty !== 0 && harga > 0) {
                hasValidProducts = true;
            }
        });

        if (!hasValidProducts) {
            showMessage('Tambahkan minimal satu produk dengan quantity dan harga yang valid', 'danger', '#form-msg');
            return false;
        }

        const grandTotal = parseRupiah($('#grand_total').val());
        if (grandTotal === 0) {
            showMessage('Total penjualan tidak boleh 0', 'danger', '#form-msg');
            return false;
        }

        if (requiresMinusPassword() && !requestMinusPassword()) {
            return false;
        }

        return true;
    }

    function submitForm() {
        if (!validateForm()) return;

        start_loader();
        const formData = new FormData($('#penjualan-form')[0]);
        formData.set('minus_password', requiresMinusPassword() ? minusPassword : '');

        $.ajax({
            url: baseUrl + "?f=" + (currentSaleId ? 'update_penjualan' : 'save_penjualan'),
            method: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                end_loader();
                console.log('✅ Response received:', resp);
                if (resp.status === 'success') {
                    showMessage(resp.msg, 'success', '#form-msg');
                    setTimeout(() => {
                        $('#penjualanModal').modal('hide');
                        forceReloadTable();
                    }, 1500);
                } else {
                    showMessage(resp.msg || 'Gagal menyimpan data', 'danger', '#form-msg');
                }
            },
            error: function(xhr, status, error) {
                end_loader();
                console.error('❌ AJAX Error:', status, error);
                console.log('Response Text:', xhr.responseText);
                console.log('Status Code:', xhr.status);
                let errorMsg = 'Terjadi kesalahan koneksi ke server: ' + error;
                try {
                    const errorResp = JSON.parse(xhr.responseText);
                    if (errorResp && errorResp.msg) {
                        errorMsg = errorResp.msg;
                    }
                } catch (e) {
                    console.error('Failed to parse error response:', e);
                }
                showMessage(errorMsg, 'danger', '#form-msg');
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
        const saleid = row.saleid;
        const invoice = escapeHtml(row.sale_invoice || '');
        const status = row.sale_status || 'ACTIVE';

        let buttons = `
            <div class="btn-group" role="group" aria-label="Aksi">
                <button type="button" class="btn btn-icon btn-sm print_data me-1" 
                    data-id="${saleid}" data-invoice="${invoice}" 
                    title="Lihat / Print Invoice">
                    <i class="bi bi-file-pdf text-primary"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm print_dropshipper me-1"
                    data-id="${saleid}" data-invoice="${invoice}"
                    title="Cetak Dropshipper">
                    <i class="bi bi-truck text-success"></i>
                </button>
        `;

        if (row.can_edit) {
            buttons += `
                <button type="button" class="btn btn-icon btn-sm edit_data me-1" 
                    data-id="${saleid}" data-invoice="${invoice}" title="Edit Data">
                    <i class="bi bi-pencil"></i>
                </button>
            `;
        }

        if ([1, 4].includes(currentLevelId) && status !== 'DELETED') {
            buttons += `
                <button type="button" class="btn btn-icon btn-sm delete_data"
                    data-id="${saleid}" data-invoice="${invoice}" title="Hapus Riwayat Transaksi">
                    <i class="bi bi-trash text-danger"></i>
                </button>
            `;
        }

        buttons += `</div>`;
        return buttons;
    }

    /** ======================= [ DELETE FUNCTION ] ======================= **/
    function deleteData(saleid) {
        start_loader();
        console.log('Starting DELETE for saleid:', saleid);

        $.ajax({
            url: baseUrl + "?f=delete_penjualan",
            method: "POST",
            data: {
                saleid: saleid
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
            .off('click', '.print_dropshipper')
            .off('click', '.edit_data')
            .off('click', '.delete_data')
            .on('click', '.print_data', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const invoice = $(this).data('invoice');

                if (!id || id <= 0) {
                    console.error('ID tidak valid untuk print:', id);
                    Swal.fire('Error', 'ID transaksi tidak valid', 'error');
                    return;
                }

                // Single smart endpoint that handles both KSR and INV
                const url = '<?php echo base_url ?>admin?page=penjualan/print_invoice&id=' + encodeURIComponent(id);
                console.log('Opening print URL (auto-detect):', url, 'Invoice:', invoice);
                const printWindow = window.open(url, '_blank');
                if (!printWindow) {
                    Swal.fire('Error', 'Popup diblokir oleh browser. Silakan izinkan popup dan coba lagi.', 'error');
                }
            })
            .on('click', '.print_dropshipper', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (!id || id <= 0) {
                    Swal.fire('Error', 'ID transaksi tidak valid', 'error');
                    return;
                }
                const url = '<?php echo base_url ?>admin/penjualan/print_dropshipper.php?id=' + encodeURIComponent(id);
                const printWindow = window.open(url, '_blank');
                if (!printWindow) {
                    Swal.fire('Error', 'Popup diblokir oleh browser. Silakan izinkan popup dan coba lagi.', 'error');
                }
            })
            .on('click', '.edit_data', function() {
                const id = $(this).data('id');
                if (!id) {
                    Swal.fire("Error", "ID transaksi tidak valid", "error");
                    return;
                }
                openPenjualanModal(id);
            })
            .on('click', '.delete_data', function() {
                const saleid = $(this).data('id');
                const invoice = $(this).data('invoice');
                if (!saleid) {
                    Swal.fire("Error", "ID transaksi tidak valid", "error");
                    return;
                }
                _conf(`Yakin ingin menghapus transaksi <b>${escapeHtml(invoice)}</b>?`, "deleteData", [saleid]);
            });
    }

    $(document).ready(function() {
        // Initialize DataTables
        load_data();

        // Modal events
        $('#btn_add').click(() => openPenjualanModal());

        if (requestedEditSaleId > 0) {
            setTimeout(() => openPenjualanModal(requestedEditSaleId), 500);
        } else if (autoOpenPenjualanForm) {
            setTimeout(() => openPenjualanModal(), 300);
        }

        $('#submit-penjualan').click(submitForm);

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

        $(document).on('blur', '.qty', function() {
            const tr = $(this).closest('tr');
            $(this).val(saleQuantityInput(saleQuantityFromInput($(this).val())));
            hitungSubtotal(tr);
            hitungGrandTotal();
        });

        $(document).on('blur', '.harga', function() {
            const tr = $(this).closest('tr');
            const val = parseRupiah($(this).val());
            // ✅ FIXED: Set nilai RAW numerik, bukan formatted
            $(this).val(val);
            validasiHarga(tr);
            hitungSubtotal(tr);
            hitungGrandTotal();
        });

        $(document).on('input', '#sale_disc', function() {
            const val = parseRupiah($(this).val());
            // ✅ FIXED: Set nilai RAW numerik, bukan formatted
            $(this).val(val);
            hitungGrandTotal();
        });

        $('#bayar_id').change(function() {
            const jenis = $(this).val();
            if (jenis === '2') {
                $('#sale_jt_days').prop('disabled', false);
                $('#sale_jt').prop('disabled', false);
                syncDueDateFromDays();
            } else {
                $('#sale_jt_days').prop('disabled', true).val('30');
                $('#sale_jt').prop('disabled', true).val('');
            }
        });

        $('#sale_jt_days, [name="sale_date"]').on('input change', syncDueDateFromDays);
        $('#sale_jt').on('change', function() {
            if ($('#bayar_id').val() !== '2') return;
            const saleDate = new Date($('[name="sale_date"]').val() + 'T00:00:00');
            const dueDate = new Date($(this).val() + 'T00:00:00');
            const days = Math.round((dueDate - saleDate) / 86400000);
            if (Number.isFinite(days) && days > 0) $('#sale_jt_days').val(days);
        });

        $('#sales_id').select2({
            placeholder: 'Pilih sales...',
            allowClear: currentLevelId !== 3,
            dropdownParent: $('#penjualanModal'),
            minimumResultsForSearch: 0,
            width: '100%'
        });

        // Initialize Select2 untuk pelanggan di modal
        $('#cust_id').select2({
            placeholder: 'Cari nama pelanggan...',
            allowClear: true,
            dropdownParent: $('#penjualanModal'),
            ajax: {
                url: baseUrl + "?f=search_pelanggan",
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
            $('#tgl_awal').val('<?php echo date('Y-m-01') ?>');
            $('#tgl_akhir').val('<?php echo date('Y-m-d') ?>');
            $('#jenis').val('');
            $('#pelanggan_id').val('').trigger('change');
            setTimeout(() => {
                forceReloadTable();
                setTimeout(() => end_loader(), 1000);
            }, 300);
        });

        $('#export-btn').click(function() {
            const tgl_awal = $('#tgl_awal').val();
            const tgl_akhir = $('#tgl_akhir').val();
            const jenis = $('#jenis').val();
            const pelanggan_id = $('#pelanggan_id').val();
            const url = baseUrl + `?f=export_excel&tgl_awal=${tgl_awal}&tgl_akhir=${tgl_akhir}&jenis=${jenis}&pelanggan_id=${pelanggan_id}`;
            window.open(url, '_blank');
        });

        // Focus management
        $('#penjualanModal, #confirm_modal').on('hide.bs.modal', function() {
            setTimeout(() => {
                $('#btn_add').focus();
            }, 10);
        });

    });

    // [DataTables functions - tetap sama]
    /** ======================= [ LOAD DATA TABLE ] ======================= **/
    function load_data() {
        if ($.fn.DataTable.isDataTable('#table')) {
            salesDT.clear().destroy();
            $('.dataTables_processing').remove();
        }

        salesDT = $('#table').DataTable({
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
                url: baseUrl + "?f=get_data_penjualan",
                type: "POST",
                data: function(d) {
                    // Extend default DataTables parameters with custom filters
                    d.tgl_awal = $('#tgl_awal').val();
                    d.tgl_akhir = $('#tgl_akhir').val();
                    d.jenis = $('#jenis').val();
                    d.pelanggan_id = $('#pelanggan_id').val();
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
                    data: "sale_date",
                    className: "text-center",
                    render: function(data, type, row) {
                        return escapeHtml(data || '');
                    }
                },
                {
                    data: null,
                    className: "text-center",
                    render: function(data, type, row) {
                        const channelLabel = row.sale_channel === 'online' ? 'Sales Online' : 'Sales';
                        const channelColor = row.sale_channel === 'online' ? 'info' : 'primary';
                        return `
                            <div class="fw-bold">${escapeHtml(row.sale_invoice)}</div>
                            <small class="text-muted">${escapeHtml(row.sale_nofak || '')}</small>
                            <div><span class="badge bg-light-${channelColor} text-${channelColor}">${channelLabel}</span></div>
                        `;
                    }
                },
                {
                    data: "sales_name",
                    className: "text-center",
                    render: function(data, type, row) {
                        return `<span class="fw-bold">${escapeHtml(data || '-')}</span>`;
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
                                    <i class="fa fa-user text-primary fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">${escapeHtml(row.pelanggan_name || '-')}</div>
                                    ${row.pelanggan_alamat ? `<div class="text-muted small">${escapeHtml(row.pelanggan_alamat)}</div>` : ''}
                                </div>
                            </div>
                        `;
                    }
                },
                {
                    data: "sale_total",
                    className: "text-end fw-bold",
                    render: function(data, type, row) {
                        const value = parseFloat(data) || 0;
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                },
                {
                    data: "sale_status",
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
                        '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data penjualan</td></tr>'
                    );
                }
            }
        });

        return salesDT;
    }

    function reloadDataTable() {
        if (typeof salesDT !== 'undefined' && $.fn.DataTable.isDataTable('#table')) {
            try {
                console.log('Reloading DataTable...');
                salesDT.ajax.reload(function(json) {
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
                    salesDT.clear().destroy();
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

        // Inisialisasi Select2 pelanggan
        $('#pelanggan_id').select2({
            placeholder: 'Cari pelanggan...',
            allowClear: true,
            width: 'resolve',
            ajax: {
                url: '<?php echo base_url ?>classes/Penjualan.php?f=search_pelanggan',
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
                noResults: () => 'Tidak ada pelanggan ditemukan',
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
