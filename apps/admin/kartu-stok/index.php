<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-card-list me-2"></i>Kartu Stok Barang</h3>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4 g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Pilih Produk <span class="text-danger">*</span></label>
                            <select id="filter_produk" class="form-select form-control" style="width:100%"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Awal</label>
                            <input type="date" id="filter_tgl_awal" class="form-control" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" id="filter_tgl_akhir" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button class="btn btn-primary flex-grow-1" id="btn_filter">
                                <i class="bi bi-search me-1"></i>Tampilkan
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button class="btn btn-success flex-grow-1" id="btn_print" disabled>
                                <i class="bi bi-printer me-1"></i>Cetak
                            </button>
                        </div>
                    </div>

                    <!-- Product Info Card -->
                    <div class="card mb-3 border-primary" id="info_produk" style="display:none;">
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Kode Barang:</strong><br>
                                    <span id="info_barcode" class="text-muted">-</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Nama Barang:</strong><br>
                                    <span id="info_nama" class="text-muted">-</span>
                                </div>
                                <div class="col-md-2">
                                    <strong>Satuan:</strong><br>
                                    <span id="info_satuan" class="text-muted">-</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Stok Saat Ini:</strong><br>
                                    <span id="info_stok" class="fw-bold text-dark">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Card Table -->
                    <div class="table-responsive">
                        <table id="table_kartu_stok" class="table table-bordered table-sm" style="width:100%">
                            <thead class="bg-light table-primary">
                                <tr>
                                    <th class="text-center" style="width:5%">No</th>
                                    <th style="width:12%">Tanggal</th>
                                    <th style="width:15%">No. Dokumen</th>
                                    <th>Keterangan</th>
                                    <th class="text-end" style="width:10%">Masuk</th>
                                    <th class="text-end" style="width:10%">Keluar</th>
                                    <th class="text-end" style="width:10%">Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="kartu_stok_rows">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-info-circle me-2"></i>Pilih produk dan klik "Tampilkan" untuk melihat kartu stok
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize Select2 for product dropdown
        $('#filter_produk').select2({
            placeholder: '-- Pilih Produk --',
            allowClear: true,
            ajax: {
                url: '../classes/Stok.php?f=get_products_list',
                dataType: 'json',
                type: 'POST',
                delay: 250,
                data: params => ({
                    q: params.term || '',
                    page: params.page || 1
                }),
                processResults: data => ({
                    results: data.results || data.data || []
                }),
                cache: true
            },
            minimumInputLength: 2,
            language: {
                inputTooShort: () => 'Ketik minimal 2 huruf',
                searching: () => 'Mencari produk...',
                noResults: () => 'Produk tidak ditemukan'
            }
        });

        // Filter button
        $('#btn_filter').click(function() {
            loadKartuStok();
        });

        // Print button
        $('#btn_print').click(function() {
            printKartuStok();
        });

        // Allow Enter key to load
        $('#filter_produk').on('select2:select', function() {
            loadKartuStok();
        });
    });

    function loadKartuStok() {
        const produkId = $('#filter_produk').val();
        if (!produkId) {
            Swal.fire({
                title: 'Peringatan',
                text: 'Pilih produk terlebih dahulu',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const tglAwal = $('#filter_tgl_awal').val();
        const tglAkhir = $('#filter_tgl_akhir').val();

        $('#kartu_stok_rows').html(`
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-primary me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    Memuat data...
                </td>
            </tr>
        `);

        $.ajax({
            url: '../classes/Stok.php?f=get_kartu_stok',
            type: 'POST',
            data: {
                produk_id: produkId,
                tgl_awal: tglAwal,
                tgl_akhir: tglAkhir
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    // Display product info
                    $('#info_produk').show();
                    $('#info_barcode').text(res.produk.barcode || '-');
                    $('#info_nama').text(res.produk.nama_produk || '-');
                    $('#info_satuan').text(res.produk.nama_satuan || '-');
                    $('#info_stok').text(parseFloat(res.produk.stok || 0).toLocaleString('id-ID'));

                    // Build stock card table
                    let html = '';
                    let saldo = parseFloat(res.saldo_awal || 0);
                    let no = 1;

                    // Opening balance row
                    html += `<tr class="table-light">
                        <td class="text-center">-</td>
                        <td>${tglAwal}</td>
                        <td>-</td>
                        <td><em class="text-muted">Saldo Awal</em></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-bold">${saldo.toLocaleString('id-ID')}</td>
                    </tr>`;

                    // Transaction rows
                    res.data.forEach(row => {
                        const masuk = parseFloat(row.masuk || 0);
                        const keluar = parseFloat(row.keluar || 0);
                        saldo = saldo + masuk - keluar;

                        html += `<tr>
                            <td class="text-center">${no++}</td>
                            <td>${row.tanggal}</td>
                            <td>${row.no_dokumen || '-'}</td>
                            <td>${row.keterangan || '-'}</td>
                            <td class="text-end ${masuk > 0 ? 'text-success fw-bold' : ''}">${masuk > 0 ? masuk.toLocaleString('id-ID') : '-'}</td>
                            <td class="text-end ${keluar > 0 ? 'text-danger fw-bold' : ''}">${keluar > 0 ? keluar.toLocaleString('id-ID') : '-'}</td>
                            <td class="text-end fw-bold">${saldo.toLocaleString('id-ID')}</td>
                        </tr>`;
                    });

                    // No transactions message
                    if (res.data.length === 0) {
                        html += '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada transaksi pada periode ini</td></tr>';
                    }

                    $('#kartu_stok_rows').html(html);
                    $('#btn_print').prop('disabled', false);

                } else {
                    Swal.fire({
                        title: 'Error',
                        text: res.msg || 'Gagal memuat data',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                Swal.fire({
                    title: 'Error',
                    text: 'Terjadi kesalahan server. Cek console untuk detail.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function printKartuStok() {
        const produkId = $('#filter_produk').val();
        if (!produkId) {
            Swal.fire({
                title: 'Peringatan',
                text: 'Pilih produk terlebih dahulu',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
        const tglAwal = $('#filter_tgl_awal').val();
        const tglAkhir = $('#filter_tgl_akhir').val();
        window.open(`../classes/Stok.php?f=print_kartu_stok&produk_id=${produkId}&tgl_awal=${tglAwal}&tgl_akhir=${tglAkhir}`, '_blank');
    }
</script>
