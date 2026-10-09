<?php
$returPpnRate = $_settings->info('ppn_rate');
if ($returPpnRate === false || $returPpnRate === '' || !is_numeric($returPpnRate)) {
    $returPpnRate = 11;
}
$returPpnRateDecimal = max(0, min(100, (float)$returPpnRate)) / 100;
?>
<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="bi bi-arrow-return-right me-2"></i>Retur Penjualan</h3>
                    <button class="btn btn-primary btn-sm" id="btn_add_retur">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Retur
                    </button>
                </div>
                <div class="card-body">
                    <!-- Filter -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Awal</label>
                            <input type="date" id="filter_tgl_awal" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" id="filter_tgl_akhir" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pelanggan</label>
                            <select id="filter_pelanggan" class="form-select form-select-sm" style="width:100%"></select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-info btn-sm w-100" id="btn_filter">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="table_retur" class="table table-bordered table-hover table-sm" style="width:100%">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" style="width:5%">No</th>
                                    <th>Tanggal</th>
                                    <th>No. Retur</th>
                                    <th>Pelanggan</th>
                                    <th>No. Penjualan</th>
                                    <th class="text-end">Total Retur</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width:12%">Aksi</th>
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

<!-- Modal Form Retur -->
<div class="modal fade" id="modalRetur" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-return-right me-2"></i>Form Retur Penjualan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_retur">
                <div class="modal-body">
                    <input type="hidden" name="returid" id="returid">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">Tanggal Retur</label>
                            <input type="date" name="retur_date" id="retur_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Transaksi Penjualan</label>
                            <select name="saleid" id="saleid" class="form-select" style="width:100%" required></select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Pelanggan</label>
                            <input type="text" id="info_pelanggan" class="form-control" readonly>
                            <input type="hidden" name="cust_id" id="cust_id">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. Invoice Penjualan</label>
                            <input type="text" id="info_invoice" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Penjualan</label>
                            <input type="text" id="info_tanggal" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" id="catatan" class="form-control" rows="2" placeholder="Keterangan alasan retur..."></textarea>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3">Detail Produk</h6>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="table_detail">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:30%">Produk</th>
                                    <th class="text-center" style="width:10%">Qty Jual</th>
                                    <th class="text-center" style="width:10%">Sudah Retur</th>
                                    <th class="text-center" style="width:10%">Sisa</th>
                                    <th class="text-center" style="width:10%">Qty Retur</th>
                                    <th class="text-end" style="width:12%">Harga</th>
                                    <th class="text-end" style="width:12%">Subtotal</th>
                                    <th class="text-center" style="width:6%">Kondisi</th>
                                </tr>
                            </thead>
                            <tbody id="detail_rows">
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Pilih transaksi penjualan terlebih dahulu</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">Total Retur</td>
                                    <td class="text-end fw-bold" id="total_display">Rp 0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <input type="hidden" name="retur_total" id="retur_total" value="0">
                    <input type="hidden" name="retur_ppn" id="retur_ppn" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn_save">
                        <i class="bi bi-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail View -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detail Retur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail_content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    const baseUrl = "../classes/ReturPenjualan.php";
    const RETUR_PPN_RATE = <?= json_encode($returPpnRateDecimal) ?>;
    let dataTable;

    $(document).ready(function() {
        initDataTable();
        initSelect2();

        // Filter button
        $('#btn_filter').click(function() {
            dataTable.ajax.reload();
        });

        // Add button
        $('#btn_add_retur').click(function() {
            resetForm();
            $('#modalRetur').modal('show');
        });

        // Sales selection change
        $('#saleid').on('select2:select', function(e) {
            loadSaleDetail(e.params.data.id);
        });

        // Form submit
        $('#form_retur').submit(function(e) {
            e.preventDefault();
            saveRetur();
        });
    });

    function initDataTable() {
        dataTable = $('#table_retur').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + "?f=get_data_retur",
                type: "POST",
                data: function(d) {
                    d.tgl_awal = $('#filter_tgl_awal').val();
                    d.tgl_akhir = $('#filter_tgl_akhir').val();
                    d.cust_id = $('#filter_pelanggan').val() || 0;
                }
            },
            columns: [{
                    data: 'no',
                    className: 'text-center'
                },
                {
                    data: 'retur_date'
                },
                {
                    data: 'retur_invoice'
                },
                {
                    data: 'nama_pelanggan'
                },
                {
                    data: 'sale_invoice'
                },
                {
                    data: 'retur_total',
                    className: 'text-end',
                    render: v => formatRupiah(v)
                },
                {
                    data: 'status_badge',
                    className: 'text-center'
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        let btns = `<button class="btn btn-info btn-xs me-1" onclick="viewDetail(${row.returid})" title="Lihat"><i class="bi bi-eye"></i></button>`;
                        if (row.post == 0) {
                            btns += `<button class="btn btn-warning btn-xs me-1" onclick="editRetur(${row.returid})" title="Edit"><i class="bi bi-pencil"></i></button>`;
                            btns += `<button class="btn btn-success btn-xs me-1" onclick="postRetur(${row.returid})" title="Posting"><i class="bi bi-check-circle"></i></button>`;
                            btns += `<button class="btn btn-danger btn-xs" onclick="deleteRetur(${row.returid})" title="Hapus"><i class="bi bi-trash"></i></button>`;
                        }
                        return btns;
                    }
                }
            ],
            order: [
                [0, 'desc']
            ],
            language: {
                processing: "Memproses...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(difilter dari _MAX_ total data)",
                zeroRecords: "Data tidak ditemukan",
                emptyTable: "Tidak ada data retur",
                paginate: {
                    first: "Pertama",
                    previous: "Sebelumnya",
                    next: "Berikutnya",
                    last: "Terakhir"
                }
            }
        });
    }

    function initSelect2() {
        // Filter pelanggan
        $('#filter_pelanggan').select2({
            placeholder: '-- Semua Pelanggan --',
            allowClear: true,
            ajax: {
                url: '../classes/Master.php?f=get_pelanggan_list',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term,
                    page: params.page || 1
                }),
                processResults: data => data
            }
        });

        // Penjualan select
        $('#saleid').select2({
            dropdownParent: $('#modalRetur'),
            placeholder: '-- Pilih Transaksi Penjualan --',
            allowClear: true,
            ajax: {
                url: baseUrl + '?f=get_penjualan_list',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term,
                    page: params.page || 1
                }),
                processResults: data => data
            }
        });
    }

    function resetForm() {
        $('#form_retur')[0].reset();
        $('#returid').val('');
        $('#saleid').val(null).trigger('change');
        $('#cust_id').val('');
        $('#info_pelanggan, #info_invoice, #info_tanggal').val('');
        $('#detail_rows').html('<tr><td colspan="8" class="text-center text-muted">Pilih transaksi penjualan terlebih dahulu</td></tr>');
        $('#total_display').text('Rp 0');
        $('#retur_total').val(0);
    }

    function loadSaleDetail(saleId) {
        $.get(baseUrl + '?f=get_penjualan_detail&sale_id=' + saleId, function(res) {
            if (res.status === 'success') {
                const h = res.header;
                $('#cust_id').val(h.cust_id || 0);
                $('#info_pelanggan').val(h.nama_pelanggan || 'CASH');
                $('#info_invoice').val(h.sale_invoice);
                $('#info_tanggal').val(h.sale_date);

                let html = '';
                res.detail.forEach((d, i) => {
                    html += `
                <tr>
                    <td>
                        <input type="hidden" name="products[${i}][produk_id]" value="${d.produk_id}">
                        <input type="hidden" name="products[${i}][sale_capital]" value="${d.sale_capital || 0}">
                        <input type="hidden" name="products[${i}][sale_purchase_cost]" value="${d.sale_purchase_cost || d.harga_beli || 0}">
                        <input type="hidden" name="products[${i}][sale_harga]" value="${d.sale_harga}">
                        <input type="hidden" name="products[${i}][sale_pdisc]" value="${d.sale_pdisc || 0}">
                        <input type="hidden" name="products[${i}][ppn]" value="${d.is_ppn || 0}">
                        <input type="hidden" name="products[${i}][isi]" value="${d.isi || 1}">
                        ${d.nama_produk}<br><small class="text-muted">${d.barcode || ''}</small>
                    </td>
                    <td class="text-center">${formatReturQuantity(d.sale_qty)}</td>
                    <td class="text-center">${formatReturQuantity(d.qty_sudah_retur || 0)}</td>
                    <td class="text-center">${formatReturQuantity(d.qty_sisa)}</td>
                    <td class="text-center">
                        <input type="number" name="products[${i}][retur_qty]" 
                            class="form-control form-control-sm text-center retur-qty" 
                            min="0" max="${returQuantityInput(d.qty_sisa)}" step="0.0001" value="0" 
                            data-harga="${d.sale_harga}" 
                            data-ppn="${d.is_ppn || 0}"
                            onchange="hitungSubtotal(this)">
                    </td>
                    <td class="text-end">${formatRupiah(d.sale_harga)}</td>
                    <td class="text-end subtotal">Rp 0</td>
                    <td class="text-center">
                        <select name="products[${i}][retur_kondisi]" class="form-select form-select-sm">
                            <option value="1">Baik</option>
                            <option value="2">Rusak</option>
                        </select>
                    </td>
                </tr>`;
                });
                $('#detail_rows').html(html);
                hitungTotal();
            } else {
                alert_toast(res.msg || 'Gagal memuat data', 'error');
            }
        }, 'json');
    }

    function hitungSubtotal(el) {
        const $row = $(el).closest('tr');
        const qty = returQuantityFromInput($(el).val());
        const harga = parseFloat($(el).data('harga')) || 0;
        const subtotal = qty * harga;
        $row.find('.subtotal').text(formatRupiah(subtotal));
        hitungTotal();
    }

    function hitungTotal() {
        let total = 0;
        let ppn = 0;
        $('.retur-qty').each(function() {
            const qty = returQuantityFromInput($(this).val());
            const harga = parseFloat($(this).data('harga')) || 0;
            const lineTotal = qty * harga;
            total += lineTotal;
            if (Number($(this).data('ppn') || 0) === 1 && RETUR_PPN_RATE > 0) {
                ppn += lineTotal - (lineTotal / (1 + RETUR_PPN_RATE));
            }
        });
        $('#total_display').text(formatRupiah(total));
        $('#retur_total').val(total);
        $('#retur_ppn').val(Math.round(ppn));
    }

    // ✅ FIXED: Fungsi yang lebih aman menangani semua format input
    function formatRupiah(num) {
        return SarinahNumbers.formatMoney(num, { prefix: true });
    }

    function parseRupiah(r) {
        return SarinahNumbers.parseMoney(r);
    }

    function normalizeReturQuantity(value) {
        const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
        return Object.is(rounded, -0) ? 0 : rounded;
    }

    function returQuantityFromInput(value) {
        return normalizeReturQuantity(value);
    }

    function returQuantityInput(value) {
        return normalizeReturQuantity(value).toFixed(4).replace(/\.?0+$/, '') || '0';
    }

    function formatReturQuantity(value) {
        return SarinahNumbers.formatQuantity(normalizeReturQuantity(value), {
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        });
    }

    function saveRetur() {
        // Validate at least one qty
        let hasQty = false;
        let invalidQty = false;
        $('.retur-qty').each(function() {
            const qty = returQuantityFromInput($(this).val());
            const maxQty = normalizeReturQuantity($(this).attr('max'));
            if (qty < 0 || qty > maxQty) {
                invalidQty = true;
                return false;
            }
            $(this).val(returQuantityInput(qty));
            if (qty > 0) hasQty = true;
        });
        if (invalidQty) {
            alert_toast('Qty retur harus antara 0 dan qty sisa penjualan', 'warning');
            return;
        }
        if (!hasQty) {
            alert_toast('Minimal harus ada 1 produk dengan qty retur > 0', 'warning');
            return;
        }

        $.ajax({
            url: baseUrl + '?f=save_retur',
            type: 'POST',
            data: $('#form_retur').serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    alert_toast(res.msg, 'success');
                    $('#modalRetur').modal('hide');
                    dataTable.ajax.reload();
                } else {
                    alert_toast(res.msg || 'Gagal menyimpan', 'error');
                }
            },
            error: function() {
                alert_toast('Terjadi kesalahan server', 'error');
            }
        });
    }

    function viewDetail(id) {
        $.get(baseUrl + '?f=get_retur_detail&returid=' + id, function(res) {
            if (res.status === 'success') {
                let html = `
                <div class="row mb-3">
                    <div class="col-6"><strong>No. Retur:</strong> ${res.header.retur_invoice}</div>
                    <div class="col-6"><strong>Tanggal:</strong> ${res.header.retur_date}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-6"><strong>Pelanggan:</strong> ${res.header.nama_pelanggan || 'CASH'}</div>
                    <div class="col-6"><strong>No. Penjualan:</strong> ${res.header.sale_invoice || '-'}</div>
                </div>
                <hr>
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>`;
                res.detail.forEach(d => {
                    const kondisi = d.retur_kondisi == 1 ? '<span class="badge bg-success">Baik</span>' : '<span class="badge bg-danger">Rusak</span>';
                    html += `
                    <tr>
                        <td>${d.nama_produk}</td>
                        <td class="text-center">${formatReturQuantity(d.retur_qty)}</td>
                        <td class="text-end">${formatRupiah(d.sale_harga)}</td>
                        <td class="text-end">${formatRupiah(d.retur_qty * d.sale_harga)}</td>
                        <td class="text-center">${kondisi}</td>
                    </tr>`;
                });
                html += `</tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-end fw-bold">Total Retur</td>
                    <td class="text-end fw-bold">${formatRupiah(res.header.retur_total)}</td><td></td></tr>
                </tfoot></table>`;
                $('#detail_content').html(html);
                $('#modalDetail').modal('show');
            }
        }, 'json');
    }

    function editRetur(id) {
        $.get(baseUrl + '?f=get_retur_detail&returid=' + id, function(res) {
            if (res.status === 'success') {
                resetForm();
                $('#returid').val(res.header.returid);
                $('#retur_date').val(res.header.retur_date);
                $('#cust_id').val(res.header.cust_id);
                $('#info_pelanggan').val(res.header.nama_pelanggan || 'CASH');
                $('#info_invoice').val(res.header.sale_invoice);

                // Set saleid with Select2
                const opt = new Option(res.header.sale_invoice + ' - ' + (res.header.nama_pelanggan || 'CASH'), res.header.saleid, true, true);
                $('#saleid').append(opt).trigger('change');

                // Load original sale detail then set qty
                $.get(baseUrl + '?f=get_penjualan_detail&sale_id=' + res.header.saleid, function(saleRes) {
                    if (saleRes.status === 'success') {
                        loadSaleDetailWithRetur(saleRes.detail, res.detail);
                    }
                }, 'json');

                $('#modalRetur').modal('show');
            }
        }, 'json');
    }

    function loadSaleDetailWithRetur(saleDetail, returDetail) {
        const returMap = {};
        returDetail.forEach(r => {
            returMap[r.produk_id] = r;
        });

        let html = '';
        saleDetail.forEach((d, i) => {
            const reKondisi = returMap[d.produk_id]?.retur_kondisi || 1;
            const reQty = normalizeReturQuantity(returMap[d.produk_id]?.retur_qty || 0);
            html += `
        <tr>
            <td>
                <input type="hidden" name="products[${i}][produk_id]" value="${d.produk_id}">
                <input type="hidden" name="products[${i}][sale_capital]" value="${d.sale_capital || 0}">
                <input type="hidden" name="products[${i}][sale_purchase_cost]" value="${d.sale_purchase_cost || d.harga_beli || 0}">
                <input type="hidden" name="products[${i}][sale_harga]" value="${d.sale_harga}">
                <input type="hidden" name="products[${i}][sale_pdisc]" value="${d.sale_pdisc || 0}">
                <input type="hidden" name="products[${i}][ppn]" value="${d.is_ppn || 0}">
                <input type="hidden" name="products[${i}][isi]" value="${d.isi || 1}">
                ${d.nama_produk}<br><small class="text-muted">${d.barcode || ''}</small>
            </td>
            <td class="text-center">${formatReturQuantity(d.sale_qty)}</td>
            <td class="text-center">${formatReturQuantity(d.qty_sudah_retur || 0)}</td>
            <td class="text-center">${formatReturQuantity(d.qty_sisa)}</td>
            <td class="text-center">
                <input type="number" name="products[${i}][retur_qty]" 
                    class="form-control form-control-sm text-center retur-qty" 
                    min="0" max="${returQuantityInput(d.qty_sisa)}" step="0.0001" value="${returQuantityInput(reQty)}" 
                    data-harga="${d.sale_harga}" 
                    data-ppn="${d.is_ppn || 0}"
                    onchange="hitungSubtotal(this)">
            </td>
            <td class="text-end">${formatRupiah(d.sale_harga)}</td>
            <td class="text-end subtotal">${formatRupiah(reQty * d.sale_harga)}</td>
            <td class="text-center">
                <select name="products[${i}][retur_kondisi]" class="form-select form-select-sm">
                    <option value="1" ${reKondisi == 1 ? 'selected' : ''}>Baik</option>
                    <option value="2" ${reKondisi == 2 ? 'selected' : ''}>Rusak</option>
                </select>
            </td>
        </tr>`;
        });
        $('#detail_rows').html(html);
        hitungTotal();
    }

    function postRetur(id) {
        Swal.fire({
            title: 'Posting Retur?',
            text: 'Retur yang sudah diposting tidak dapat diedit atau dihapus. Stok akan dikembalikan untuk barang kondisi baik.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Posting',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.post(baseUrl + '?f=post_retur', {
                    returid: id
                }, function(res) {
                    if (res.status === 'success') {
                        alert_toast(res.msg, 'success');
                        dataTable.ajax.reload();
                    } else {
                        alert_toast(res.msg, 'error');
                    }
                }, 'json');
            }
        });
    }

    function deleteRetur(id) {
        Swal.fire({
            title: 'Hapus Retur?',
            text: 'Data retur akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.post(baseUrl + '?f=delete_retur', {
                    returid: id
                }, function(res) {
                    if (res.status === 'success') {
                        alert_toast(res.msg, 'success');
                        dataTable.ajax.reload();
                    } else {
                        alert_toast(res.msg, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>
