<?php

// Alur ini sudah digantikan menu Retur Pembelian yang menautkan supplier,
// invoice asal, detail pembelian, dan mutasi stok dalam satu transaksi.
$canonicalReturPembelianUrl = (defined('base_url') ? base_url : '/sarinah-apps/apps/')
    . 'admin/?page=retur-pembelian';
if (!headers_sent()) {
    header('Location: ' . $canonicalReturPembelianUrl);
} else {
    echo '<script>window.location.replace(' . json_encode($canonicalReturPembelianUrl) . ');</script>';
}
return;

/**
 * ==========================================================
 *  FILE: admin/pembelian/retur_pembelian.php
 *  DESCRIPTION: Form Retur Pembelian
 *  AUTHOR: System
 * ==========================================================
 */
?>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Retur Pembelian</h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-primary btn-sm" id="btn_add_retur">
                            <i class="bi bi-plus-circle"></i> Tambah Retur
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Awal</label>
                            <input type="date" class="form-control" id="tgl_awal">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="tgl_akhir">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" id="filter_supplier">
                                <option value="">Semua Supplier</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-success" id="btn_filter">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <button type="button" class="btn btn-secondary" id="btn_reset">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="table_retur" class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th>No. Retur</th>
                                    <th>Tanggal</th>
                                    <th>No. Faktur</th>
                                    <th>Supplier</th>
                                    <th>Total Retur</th>
                                    <th>Status</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Retur -->
<div class="modal fade" id="modal_retur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_title">Tambah Retur Pembelian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form_retur">
                    <input type="hidden" id="returid" name="returid">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">No. Retur</label>
                                <input type="text" class="form-control" id="retur_invoice" name="retur_invoice" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Tanggal Retur</label>
                                <input type="date" class="form-control" id="retur_date" name="retur_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Supplier</label>
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">-- Pilih Supplier --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No. Faktur Pembelian</label>
                                <input type="text" class="form-control" id="retur_nofak" name="retur_nofak">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Faktur</label>
                                <input type="date" class="form-control" id="retur_datefak" name="retur_datefak">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control" id="catatan" name="catatan" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Product Selection -->
                    <div class="mb-3">
                        <label class="form-label">Pilih Produk</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search_product" placeholder="Cari produk (kode/nama)...">
                            <button type="button" class="btn btn-primary" id="btn_search_product">
                                <i class="bi bi-search"></i> Cari
                            </button>
                        </div>
                    </div>

                    <!-- Cart Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="cart_table">
                            <thead class="table-secondary">
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Kode</th>
                                    <th>Nama Produk</th>
                                    <th width="10%">Qty</th>
                                    <th width="10%">Satuan</th>
                                    <th width="12%">Harga</th>
                                    <th width="12%">Subtotal</th>
                                    <th width="5%">PPN</th>
                                    <th width="8%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="cart_body">
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Belum ada produk dipilih</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="text-end"><strong>Total</strong></td>
                                    <td><strong id="total_display">Rp0</strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn_save_retur">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Search Product -->
<div class="modal fade" id="modal_search_product" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered" id="product_list_table">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Stok</th>
                                <th>Harga Beli</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="product_list_body">
                            <!-- Products will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let cart = [];
        let editMode = false;
        let editId = null;

        // Set default dates
        $('#tgl_awal').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
        $('#tgl_akhir').val(moment().format('YYYY-MM-DD'));
        $('#retur_date').val(moment().format('YYYY-MM-DD'));
        $('#retur_datefak').val(moment().format('YYYY-MM-DD'));

        // Load initial data
        loadSuppliers();
        loadReturData();

        // Load Suppliers
        function loadSuppliers() {
            $.ajax({
                url: _base_url_ + 'classes/Master.php?f=get_suppliers',
                method: 'GET',
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        let options = '<option value="">Semua Supplier</option>';
                        let options2 = '<option value="">-- Pilih Supplier --</option>';

                        resp.data.forEach(function(item) {
                            options += `<option value="${item.id}">${item.nama}</option>`;
                            options2 += `<option value="${item.id}">${item.nama}</option>`;
                        });

                        $('#filter_supplier').html(options);
                        $('#supplier_id').html(options2);
                    }
                }
            });
        }

        // Load Retur Data
        function loadReturData() {
            $.ajax({
                url: _base_url_ + 'classes/Master.php?f=get_retur_pembelian',
                method: 'POST',
                data: {
                    tgl_awal: $('#tgl_awal').val(),
                    tgl_akhir: $('#tgl_akhir').val(),
                    supplier_id: $('#filter_supplier').val()
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        renderReturTable(resp.data);
                    } else {
                        $('#table_retur tbody').html('<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>');
                    }
                },
                error: function() {
                    $('#table_retur tbody').html('<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>');
                }
            });
        }

        // Render Retur Table
        function renderReturTable(data) {
            let html = '';
            if (data.length > 0) {
                data.forEach(function(row, index) {
                    let statusBadge = row.status == 1 ? '<span class="badge bg-success">Lunas</span>' : '<span class="badge bg-warning">Belum Lunas</span>';

                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${row.retur_invoice}</td>
                        <td>${formatDate(row.retur_date)}</td>
                        <td>${row.retur_nofak || '-'}</td>
                        <td>${row.supplier_name}</td>
                        <td class="text-end">Rp${formatRupiah(row.retur_total)}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info btn_view" data-id="${row.returid}" title="Detail">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning btn_edit" data-id="${row.returid}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn_delete" data-id="${row.returid}" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                });
            } else {
                html = '<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>';
            }
            $('#table_retur tbody').html(html);
        }

        // Format functions
        function formatDate(dateStr) {
            return moment(dateStr).format('DD-MM-YYYY');
        }

        function formatRupiah(angka) {
            return SarinahNumbers.formatMoney(angka);
        }

        // Button Add Retur
        $('#btn_add_retur').click(function() {
            editMode = false;
            editId = null;
            cart = [];
            $('#form_retur')[0].reset();
            $('#modal_title').text('Tambah Retur Pembelian');
            $('#returid').val('');
            $('#retur_date').val(moment().format('YYYY-MM-DD'));
            generateReturInvoice();
            renderCart();
            $('#modal_retur').modal('show');
        });

        // Generate Retur Invoice
        function generateReturInvoice() {
            $.ajax({
                url: _base_url_ + 'classes/Master.php?f=generate_retur_invoice',
                method: 'GET',
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        $('#retur_invoice').val(resp.invoice);
                    }
                }
            });
        }

        // Search Product Button
        $('#btn_search_product').click(function() {
            searchProducts();
        });

        $('#search_product').keypress(function(e) {
            if (e.which == 13) {
                e.preventDefault();
                searchProducts();
            }
        });

        // Search Products
        function searchProducts() {
            let keyword = $('#search_product').val();

            $.ajax({
                url: _base_url_ + 'classes/Master.php?f=search_products',
                method: 'POST',
                data: {
                    keyword: keyword
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        renderProductList(resp.data);
                        $('#modal_search_product').modal('show');
                    } else {
                        alert('Produk tidak ditemukan');
                    }
                }
            });
        }

        // Render Product List
        function renderProductList(products) {
            let html = '';
            products.forEach(function(p) {
                html += `
                <tr>
                    <td>${p.barcode}</td>
                    <td>${p.nama_produk}</td>
                    <td class="text-center">${p.stok}</td>
                    <td class="text-end">Rp${formatRupiah(p.harga_beli)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary btn_select_product" 
                            data-id="${p.id}"
                            data-barcode="${p.barcode}"
                            data-nama="${p.nama_produk}"
                            data-harga="${p.harga_beli}"
                            data-satuan="${p.nama_satuan}"
                            data-ppn="${p.is_ppn}">
                            <i class="bi bi-check"></i> Pilih
                        </button>
                    </td>
                </tr>
            `;
            });
            $('#product_list_body').html(html);
        }

        // Select Product
        $(document).on('click', '.btn_select_product', function() {
            let product = {
                id: $(this).data('id'),
                barcode: $(this).data('barcode'),
                nama: $(this).data('nama'),
                harga: parseFloat($(this).data('harga')),
                satuan: $(this).data('satuan'),
                is_ppn: $(this).data('ppn'),
                qty: 1
            };

            // Check if product already in cart
            let existing = cart.find(item => item.id === product.id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push(product);
            }

            renderCart();
            $('#modal_search_product').modal('hide');
            $('#search_product').val('');
        });

        // Render Cart
        function renderCart() {
            let html = '';
            let total = 0;

            if (cart.length > 0) {
                cart.forEach(function(item, index) {
                    let subtotal = item.qty * item.harga;
                    total += subtotal;
                    let ppnIcon = item.is_ppn == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '';

                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${item.barcode}</td>
                        <td>${item.nama}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm cart-qty" 
                                data-index="${index}" value="${item.qty}" min="1" step="0.01">
                        </td>
                        <td>${item.satuan}</td>
                        <td class="text-end">Rp${formatRupiah(item.harga)}</td>
                        <td class="text-end">Rp${formatRupiah(subtotal)}</td>
                        <td class="text-center">${ppnIcon}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-danger btn_remove_cart" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                });
            } else {
                html = '<tr><td colspan="9" class="text-center text-muted">Belum ada produk dipilih</td></tr>';
            }

            $('#cart_body').html(html);
            $('#total_display').text('Rp' + formatRupiah(total));
        }

        // Update Cart Qty
        $(document).on('change', '.cart-qty', function() {
            let index = $(this).data('index');
            let qty = parseFloat($(this).val()) || 1;
            cart[index].qty = qty;
            renderCart();
        });

        // Remove from Cart
        $(document).on('click', '.btn_remove_cart', function() {
            let index = $(this).data('index');
            cart.splice(index, 1);
            renderCart();
        });

        // Save Retur
        $('#btn_save_retur').click(function() {
            if (!$('#supplier_id').val()) {
                alert('Supplier harus dipilih');
                return;
            }

            if (cart.length === 0) {
                alert('Belum ada produk yang dipilih');
                return;
            }

            let data = {
                returid: $('#returid').val(),
                retur_invoice: $('#retur_invoice').val(),
                retur_date: $('#retur_date').val(),
                supplier_id: $('#supplier_id').val(),
                retur_nofak: $('#retur_nofak').val(),
                retur_datefak: $('#retur_datefak').val(),
                catatan: $('#catatan').val(),
                items: cart
            };

            $.ajax({
                url: _base_url_ + 'classes/Master.php?f=save_retur_pembelian',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                dataType: 'json',
                beforeSend: function() {
                    $('#btn_save_retur').prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Menyimpan...');
                },
                success: function(resp) {
                    if (resp.status === 'success') {
                        alert('Retur pembelian berhasil disimpan');
                        $('#modal_retur').modal('hide');
                        loadReturData();
                    } else {
                        alert('Error: ' + resp.msg);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat menyimpan data');
                },
                complete: function() {
                    $('#btn_save_retur').prop('disabled', false).html('<i class="bi bi-save"></i> Simpan');
                }
            });
        });

        // Filter & Reset
        $('#btn_filter').click(function() {
            loadReturData();
        });

        $('#btn_reset').click(function() {
            $('#tgl_awal').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
            $('#tgl_akhir').val(moment().format('YYYY-MM-DD'));
            $('#filter_supplier').val('');
            loadReturData();
        });

        // Delete Retur
        $(document).on('click', '.btn_delete', function() {
            let id = $(this).data('id');

            if (confirm('Apakah Anda yakin ingin menghapus retur ini?')) {
                $.ajax({
                    url: _base_url_ + 'classes/Master.php?f=delete_retur_pembelian',
                    method: 'POST',
                    data: {
                        returid: id
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            alert('Retur berhasil dihapus');
                            loadReturData();
                        } else {
                            alert('Error: ' + resp.msg);
                        }
                    }
                });
            }
        });
    });
</script>

<style>
    .required::after {
        content: " *";
        color: red;
    }
</style>
