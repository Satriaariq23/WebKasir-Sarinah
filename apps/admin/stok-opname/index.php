<style>
    #modalOpname .modal-content {
        max-height: calc(100vh - 3.5rem);
    }

    #modalOpname #form_opname {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
    }

    #modalOpname .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }

    #modalOpname .modal-footer {
        flex-shrink: 0;
        position: sticky;
        bottom: 0;
        z-index: 5;
        background: #fff;
        border-top: 1px solid #e4e6ef;
    }
</style>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-warning">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="bi bi-clipboard-check me-2"></i>Stok Opname</h3>
                    <button class="btn btn-primary btn-sm" id="btn_new_opname">
                        <i class="bi bi-plus-circle me-1"></i> Buat Stok Opname Baru
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
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select id="filter_status" class="form-select form-select-sm">
                                <option value="">Semua</option>
                                <option value="DRAFT">Draft</option>
                                <option value="POSTED">Posted</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-info btn-sm w-100" id="btn_filter">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="table_opname" class="table table-bordered table-hover table-sm" style="width:100%">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" style="width:5%">No</th>
                                    <th>Tanggal</th>
                                    <th>No. Dokumen</th>
                                    <th>Keterangan</th>
                                    <th class="text-center">Jumlah Item</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width:15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="opname_rows">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Stok Opname -->
<div class="modal fade" id="modalOpname" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Form Stok Opname</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_opname">
                <div class="modal-body">
                    <input type="hidden" name="opname_id" id="opname_id">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">Tanggal Opname</label>
                            <input type="date" name="opname_date" id="opname_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. Dokumen</label>
                            <input type="text" name="opname_no" id="opname_no" class="form-control" placeholder="Auto" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter Kategori</label>
                            <select id="filter_kategori_opname" class="form-select">
                                <option value="">Semua Kategori</option>
                                <?php
                                $stmt = $db->prepare("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()):
                                ?>
                                    <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['nama_kategori']) ?></option>
                                <?php endwhile;
                                $stmt->close();
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Catatan stok opname..."></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Cari Nama Barang / Barcode</label>
                        <div class="input-group">
                            <input type="text" id="search_produk_opname" class="form-control" placeholder="Ketik minimal 2 huruf nama barang atau barcode">
                            <button type="button" class="btn btn-info" id="btn_search_product">
                                <i class="bi bi-search me-1"></i> Cari Barang
                            </button>
                        </div>
                        <small class="text-muted">Barang tidak dimuat otomatis. Cari barang terlebih dahulu, lalu tambahkan ke detail stok opname.</small>
                    </div>

                    <div class="table-responsive mb-3" id="search_product_wrapper" style="display:none; max-height: 180px; overflow-y: auto;">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th style="width:15%">Barcode</th>
                                    <th>Nama Produk</th>
                                    <th class="text-center" style="width:12%">Stok</th>
                                    <th class="text-center" style="width:12%">Satuan</th>
                                    <th class="text-center" style="width:10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="search_product_rows"></tbody>
                        </table>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3">Detail Stok Opname</h6>

                    <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                        <table class="table table-bordered table-sm" id="table_detail_opname">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th>Barcode</th>
                                    <th>Nama Produk</th>
                                    <th class="text-center" style="width:10%">Stok Sistem</th>
                                    <th class="text-center" style="width:12%">Stok Fisik</th>
                                    <th class="text-center" style="width:10%">Selisih</th>
                                    <th style="width:15%">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="detail_opname_rows">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Cari nama barang lalu tambahkan ke detail stok opname</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="btn_save_opname">
                        <i class="bi bi-save me-1"></i> Simpan & Update Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail View -->
<div class="modal fade" id="modalDetailOpname" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detail Stok Opname</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail_opname_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        loadOpnameList();

        $('#btn_filter').click(loadOpnameList);

        $('#btn_new_opname').click(function() {
            resetFormOpname();
            $('#modalOpname').modal('show');
        });

        $('#btn_search_product').click(searchProductsForOpname);
        $('#search_produk_opname').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchProductsForOpname();
            }
        });

        $('#form_opname').submit(function(e) {
            e.preventDefault();
            saveOpname();
        });
    });

    function loadOpnameList() {
        const tglAwal = $('#filter_tgl_awal').val();
        const tglAkhir = $('#filter_tgl_akhir').val();
        const status = $('#filter_status').val();

        $('#opname_rows').html('<tr><td colspan="7" class="text-center"><i class="bi bi-hourglass-split"></i> Memuat...</td></tr>');

        $.ajax({
            url: '../classes/Stok.php?f=get_opname_list',
            type: 'POST',
            data: {
                tgl_awal: tglAwal,
                tgl_akhir: tglAkhir,
                status: status
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    if (res.data.length === 0) {
                        html = '<tr><td colspan="7" class="text-center text-muted">Tidak ada data stok opname</td></tr>';
                    } else {
                        res.data.forEach((row, i) => {
                            const statusBadge = row.status === 'POSTED' ?
                                '<span class="badge bg-success">Posted</span>' :
                                '<span class="badge bg-warning">Draft</span>';

                            let btns = `<button class="btn btn-info btn-xs me-1" onclick="viewOpname(${row.id})" title="Lihat"><i class="bi bi-eye"></i></button>`;
                            if (row.status === 'DRAFT') {
                                btns += `<button class="btn btn-warning btn-xs me-1" onclick="editOpname(${row.id})" title="Edit"><i class="bi bi-pencil"></i></button>`;
                                btns += `<button class="btn btn-success btn-xs me-1" onclick="postOpname(${row.id})" title="Posting"><i class="bi bi-check-circle"></i></button>`;
                                btns += `<button class="btn btn-danger btn-xs" onclick="deleteOpname(${row.id})" title="Hapus"><i class="bi bi-trash"></i></button>`;
                            }

                            html += `<tr>
                            <td class="text-center">${i + 1}</td>
                            <td>${row.opname_date}</td>
                            <td>${row.opname_no || '-'}</td>
                            <td>${row.keterangan || '-'}</td>
                            <td class="text-center">${row.total_items || 0}</td>
                            <td class="text-center">${statusBadge}</td>
                            <td class="text-center">${btns}</td>
                        </tr>`;
                        });
                    }
                    $('#opname_rows').html(html);
                } else {
                    $('#opname_rows').html('<tr><td colspan="7" class="text-center text-danger">Gagal memuat data</td></tr>');
                }
            }
        });
    }

    function resetFormOpname() {
        $('#form_opname')[0].reset();
        $('#opname_id').val('');
        $('#opname_date').val('<?= date("Y-m-d") ?>');
        $('#search_produk_opname').val('');
        $('#search_product_wrapper').hide();
        $('#search_product_rows').empty();
        $('#detail_opname_rows').html('<tr><td colspan="7" class="text-center text-muted">Cari nama barang lalu tambahkan ke detail stok opname</td></tr>');
        opnameItemIndex = 0;
    }

    let opnameItemIndex = 0;

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return $('<div>').text(text).html();
    }

    function normalizeOpnameQuantity(value) {
        const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
        return Object.is(rounded, -0) ? 0 : rounded;
    }

    function opnameQuantityInput(value) {
        return normalizeOpnameQuantity(value).toFixed(4).replace(/\.?0+$/, '') || '0';
    }

    function formatOpnameQuantity(value) {
        return SarinahNumbers.formatQuantity(normalizeOpnameQuantity(value), {
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        });
    }

    function searchProductsForOpname() {
        const kategoriId = $('#filter_kategori_opname').val();
        const keyword = $('#search_produk_opname').val().trim();

        if (keyword.length < 2) {
            alert_toast('Ketik minimal 2 huruf nama barang atau barcode', 'warning');
            return;
        }

        $('#search_product_wrapper').show();
        $('#search_product_rows').html('<tr><td colspan="5" class="text-center"><i class="bi bi-hourglass-split"></i> Mencari barang...</td></tr>');

        $.ajax({
            url: '../classes/Stok.php?f=get_products_for_opname',
            type: 'POST',
            data: {
                kategori_id: kategoriId,
                q: keyword,
                limit: 10
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach((p, i) => {
                        const encoded = encodeURIComponent(JSON.stringify(p)).replace(/'/g, '%27');
                        html += `<tr>
                            <td>${escapeHtml(p.barcode || '-')}</td>
                            <td>${escapeHtml(p.nama_produk)}</td>
                            <td class="text-center">${formatOpnameQuantity(p.stok)}</td>
                            <td class="text-center">${escapeHtml(p.nama_satuan || '-')}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-success btn-xs" onclick="addProductToOpname('${encoded}')">
                                    <i class="bi bi-plus-circle"></i>
                                </button>
                            </td>
                        </tr>`;
                    });

                    if (res.data.length === 0) {
                        html = '<tr><td colspan="5" class="text-center text-muted">Barang tidak ditemukan</td></tr>';
                    }

                    $('#search_product_rows').html(html);
                } else {
                    $('#search_product_rows').html('<tr><td colspan="5" class="text-center text-danger">Gagal mencari barang</td></tr>');
                }
            }
        });
    }

    function addProductToOpname(encodedProduct) {
        const p = JSON.parse(decodeURIComponent(encodedProduct));

        if ($(`#detail_opname_rows input[name$="[produk_id]"][value="${p.id}"]`).length > 0) {
            alert_toast('Barang sudah ada di detail opname', 'warning');
            return;
        }

        if ($('#detail_opname_rows tr td[colspan]').length) {
            $('#detail_opname_rows').empty();
        }

        const i = opnameItemIndex++;
        $('#detail_opname_rows').append(buildOpnameRow(p, i));
    }

    function buildOpnameRow(p, i, stokFisik, ket) {
        const stokSistem = normalizeOpnameQuantity(p.stok ?? p.stok_sistem ?? 0);
        const fisik = stokFisik === undefined
            ? stokSistem
            : normalizeOpnameQuantity(stokFisik);
        const selisih = normalizeOpnameQuantity(fisik - stokSistem);
        const selisihClass = selisih > 0 ? 'text-success' : (selisih < 0 ? 'text-danger' : '');

        return `<tr>
            <td class="text-center">${$('#detail_opname_rows tr').not(':has(td[colspan])').length + 1}</td>
            <td>
                <input type="hidden" name="items[${i}][produk_id]" value="${p.id || p.produk_id}">
                ${escapeHtml(p.barcode || '-')}
            </td>
            <td>${escapeHtml(p.nama_produk)}</td>
            <td class="text-center stok-sistem">${formatOpnameQuantity(stokSistem)}</td>
            <td class="text-center">
                <input type="number" name="items[${i}][stok_fisik]" 
                    class="form-control form-control-sm text-center stok-fisik" 
                    value="${opnameQuantityInput(fisik)}" 
                    data-sistem="${opnameQuantityInput(stokSistem)}"
                    onchange="hitungSelisih(this)" min="0" step="0.0001">
            </td>
            <td class="text-center selisih ${selisihClass}">${formatOpnameQuantity(selisih)}</td>
            <td>
                <div class="d-flex gap-1">
                    <input type="text" name="items[${i}][keterangan]" 
                        class="form-control form-control-sm" value="${escapeHtml(ket || '')}" placeholder="Ket...">
                    <button type="button" class="btn btn-danger btn-xs" onclick="removeOpnameRow(this)" title="Hapus barang">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }

    function removeOpnameRow(btn) {
        $(btn).closest('tr').remove();
        renumberOpnameRows();
        if ($('#detail_opname_rows tr').length === 0) {
            $('#detail_opname_rows').html('<tr><td colspan="7" class="text-center text-muted">Cari nama barang lalu tambahkan ke detail stok opname</td></tr>');
        }
    }

    function renumberOpnameRows() {
        $('#detail_opname_rows tr').not(':has(td[colspan])').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    function hitungSelisih(el) {
        const $row = $(el).closest('tr');
        const sistem = normalizeOpnameQuantity($(el).attr('data-sistem'));
        const fisik = normalizeOpnameQuantity($(el).val());
        const selisih = normalizeOpnameQuantity(fisik - sistem);

        const $selisih = $row.find('.selisih');
        $selisih.text(formatOpnameQuantity(selisih));

        if (selisih > 0) {
            $selisih.removeClass('text-danger').addClass('text-success');
        } else if (selisih < 0) {
            $selisih.removeClass('text-success').addClass('text-danger');
        } else {
            $selisih.removeClass('text-success text-danger');
        }
    }

    function saveOpname() {
        if ($('#detail_opname_rows tr td[colspan]').length || $('#detail_opname_rows input[name$="[produk_id]"]').length === 0) {
            alert_toast('Tambahkan minimal 1 barang ke detail stok opname', 'warning');
            return;
        }

        $('#detail_opname_rows .stok-fisik').each(function() {
            $(this).val(opnameQuantityInput($(this).val()));
        });

        const formData = $('#form_opname').serialize() + '&update_stock=1';
        const $btn = $('#btn_save_opname');
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');

        $.ajax({
            url: '../classes/Stok.php?f=save_opname',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    alert_toast(res.msg || 'Berhasil disimpan', 'success');
                    $('#modalOpname').modal('hide');
                    loadOpnameList();
                } else {
                    alert_toast(res.msg || 'Gagal menyimpan', 'error');
                }
            },
            error: function(xhr) {
                console.error('Save opname error:', xhr.responseText);
                alert_toast('Gagal menyimpan stok opname. Cek koneksi atau log server.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    function viewOpname(id) {
        $.get('../classes/Stok.php?f=get_opname_detail&id=' + id, function(res) {
            if (res.status === 'success') {
                let html = `
                <div class="row mb-3">
                    <div class="col-6"><strong>No. Dokumen:</strong> ${res.header.opname_no || '-'}</div>
                    <div class="col-6"><strong>Tanggal:</strong> ${res.header.opname_date}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-12"><strong>Keterangan:</strong> ${res.header.keterangan || '-'}</div>
                </div>
                <hr>
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Stok Sistem</th>
                            <th class="text-center">Stok Fisik</th>
                            <th class="text-center">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>`;

                res.detail.forEach(d => {
                    const stokSistem = normalizeOpnameQuantity(d.stok_sistem);
                    const stokFisik = normalizeOpnameQuantity(d.stok_fisik);
                    const selisih = normalizeOpnameQuantity(stokFisik - stokSistem);
                    const selisihClass = selisih > 0 ? 'text-success' : (selisih < 0 ? 'text-danger' : '');
                    html += `<tr>
                    <td>${d.nama_produk}</td>
                    <td class="text-center">${formatOpnameQuantity(stokSistem)}</td>
                    <td class="text-center">${formatOpnameQuantity(stokFisik)}</td>
                    <td class="text-center ${selisihClass}">${formatOpnameQuantity(selisih)}</td>
                </tr>`;
                });

                html += '</tbody></table>';
                $('#detail_opname_content').html(html);
                $('#modalDetailOpname').modal('show');
            }
        }, 'json');
    }

    function editOpname(id) {
        $.get('../classes/Stok.php?f=get_opname_detail&id=' + id, function(res) {
            if (res.status === 'success') {
                resetFormOpname();
                $('#opname_id').val(res.header.id);
                $('#opname_date').val(res.header.opname_date);
                $('#opname_no').val(res.header.opname_no);
                $('#keterangan').val(res.header.keterangan);

                $('#detail_opname_rows').empty();
                res.detail.forEach((d) => {
                    const i = opnameItemIndex++;
                    $('#detail_opname_rows').append(buildOpnameRow(d, i, d.stok_fisik, d.keterangan));
                });
                renumberOpnameRows();
                $('#modalOpname').modal('show');
            }
        }, 'json');
    }

    function postOpname(id) {
        Swal.fire({
            title: 'Posting Stok Opname?',
            text: 'Stok akan diupdate sesuai hasil opname. Aksi ini tidak dapat dibatalkan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Posting',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('../classes/Stok.php?f=post_opname', {
                    id: id
                }, function(res) {
                    if (res.status === 'success') {
                        alert_toast(res.msg, 'success');
                        loadOpnameList();
                    } else {
                        alert_toast(res.msg, 'error');
                    }
                }, 'json');
            }
        });
    }

    function deleteOpname(id) {
        Swal.fire({
            title: 'Hapus Stok Opname?',
            text: 'Data akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('../classes/Stok.php?f=delete_opname', {
                    id: id
                }, function(res) {
                    if (res.status === 'success') {
                        alert_toast(res.msg, 'success');
                        loadOpnameList();
                    } else {
                        alert_toast(res.msg, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>
