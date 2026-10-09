<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <!--begin::List Widget 8-->
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <!--begin::Header-->
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Data Barang</span>
                    </h3>
                    <div class="card-toolbar">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <!-- Filter Kategori -->
                            <div class="col-md-6 me-2">
                                <label for="filter_kategori" class="form-label fw-bold fs-7 mb-0">Filter Kategori</label>
                                <select id="filter_kategori" class="form-select fs-7">
                                    <option value="">Semua</option>
                                    <?php
                                    $nama_kategori = $db->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                                    while ($row = $nama_kategori->fetch_assoc()):
                                    ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama_kategori']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Filter Status -->
                            <div class="col-md-3 me-2">
                                <label for="filter_status" class="form-label fw-bold fs-7 mb-0">Filter Status</label>
                                <select id="filter_status" class="form-select fs-7">
                                    <option value="">Semua</option>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                            <!-- Filter PPN -->
                            <div class="col-md-3 me-2">
                                <label for="filter_ppn" class="form-label fw-bold fs-7 mb-0">Filter PPN</label>
                                <select id="filter_ppn" class="form-select fs-7">
                                    <option value="">Semua</option>
                                    <option value="1">PPN</option>
                                    <option value="0">Non PPN</option>
                                </select>
                            </div>
                            <!--begin::Menu item-->
                            <?php if ($_settings->set_userdata('user_type') < 3): ?>
                                <div class="form-group col-auto pt-6" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Tambah Barang Baru.">
                                    <a class="btn btn-icon btn-success btn-sm" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#add_modal" id="btn_add"><i class="bi bi-file-earmark-plus fs-3"></i></a>
                                </div>
                            <?php endif; ?>
                            <!--end::Menu item-->
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <!--end::Header-->
                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <!--begin::Navbar-->
                        <div class="d-flex overflow-auto h-40px">
                            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bolder flex-column">
                                <!--begin::Barang-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 active" href="<?php echo base_url ?>admin/?page=barang">Barang</a>
                                </li>
                                <!--end::Barang-->
                                <!--begin::Satuan-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 " href="<?php echo base_url ?>admin/?page=barang/satuan">Satuan</a>
                                </li>
                                <!--end::Satuan-->
                                <!--begin::Kategori-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 " href="<?php echo base_url ?>admin/?page=barang/kategori">Kategori</a>
                                </li>
                                <!--end::Kategori-->
                            </ul>
                        </div>
                        <!--end::Navbar-->
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th>Barcode</th>
                                        <th class="text-start" style="width: 25%;">Nama Produk</th>
                                        <th>Satuan</th>
                                        <th class="text-end">Stok</th>
                                        <th class="text-end">Harga Beli</th>
                                        <th class="text-end">New Modal</th>
                                        <th class="text-end">Harga Sales</th>
                                        <th class="text-end">Harga Kasir</th>
                                        <th>Status - PPn - Poin</th>
                                        <th class="text-center" style="width: 5%;">Aksi</th>
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
</div>
<!-- Modal Tambah Barang -->
<div class="modal fade" id="add_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="new-barang-frm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Barcode</label>
                        <input type="text" name="barcode" class="form-control" autocomplete="off" placeholder="Kode Barang" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required">Nama Barang</label>
                        <input type="text" name="nama_produk" class="form-control text-uppercase" autocomplete="off" placeholder="Nama Barang" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Kategori</label>
                        <select name="id_kategori" class="form-select" required id="add_id_kategori">
                            <option value="">Pilih Kategori</option>
                            <?php
                            $nama_kategori = $db->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                            while ($row = $nama_kategori->fetch_assoc()):
                            ?>
                                <option value="<?= $row['id'] ?>"><?= $row['nama_kategori'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Satuan</label>
                        <select name="id_satuan" class="form-select" required id="add_id_satuan">
                            <option value="">Pilih Satuan</option>
                            <?php
                            $nama_satuan = $db->query("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan ASC");
                            while ($row = $nama_satuan->fetch_assoc()):
                            ?>
                                <option value="<?= $row['id'] ?>"><?= $row['nama_satuan'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" class="form-control" min="0" value="0" step="0.0001">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Harga Beli</label>
                        <input type="number" name="harga_beli" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">New Modal</label>
                        <input type="number" name="harga_newmodal" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Sales</label>
                        <input type="number" name="harga_sales" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Kasir (P1/Level 1)</label>
                        <input type="number" name="harga_kasir" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P2/Level 2</label>
                        <input type="number" name="P2" class="form-control" min="0" value="0" step="1" placeholder="Qty bertingkat">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 2</label>
                        <input type="number" name="mqty2" class="form-control" min="0" value="0" step="0.0001" placeholder="Misal: 0.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P3/Level 3</label>
                        <input type="number" name="P3" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 3</label>
                        <input type="number" name="mqty3" class="form-control" min="0" value="0" step="0.0001" placeholder="Misal: 1.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P4/Level 4</label>
                        <input type="number" name="P4" class="form-control" min="0" value="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 4</label>
                        <input type="number" name="mqty4" class="form-control" min="0" value="0" step="0.0001" placeholder="Misal: 2.5">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="2" placeholder="Deskripsi Barang"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Gambar Produk</label>
                        <div class="product-image-uploader">
                            <img id="add_gambar_preview" class="product-image-preview" src="<?= base_url ?>uploads/no-image-available.png" alt="Pratinjau gambar produk">
                            <div class="flex-grow-1">
                                <input type="file" name="gambar_produk" class="form-control product-image-input" accept="image/jpeg,image/png,image/webp" data-preview="#add_gambar_preview">
                                <div class="form-text">Format JPG, PNG, atau WebP. Ukuran maksimal 3 MB.</div>
                                <button type="button" class="btn btn-sm btn-light-primary product-crop-again d-none mt-2">
                                    <i class="bi bi-crop me-1"></i>Atur ulang crop
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_ppn" value="1" id="is_ppn" checked>
                            <label class="form-check-label" for="is_ppn">
                                Kena PPN
                            </label>
                            <div class="form-text">Jika dicentang, harga transaksi otomatis menjadi harga jual + PPN per barang.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="status" value="1" id="status" checked>
                            <label class="form-check-label" for="status">
                                Aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_poin" value="1" id="is_poin" checked>
                            <label class="form-check-label" for="is_poin">
                                Dapat Poin
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Edit Barang -->
<div class="modal fade" id="edit_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="edit-barang-frm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id">
                    <div class="col-md-4">
                        <label class="form-label required">Barcode</label>
                        <input type="text" name="barcode" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required">Nama Barang</label>
                        <input type="text" name="nama_produk" class="form-control text-uppercase" autocomplete="off" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Kategori</label>
                        <select name="id_kategori" class="form-select" required id="edit_id_kategori">
                            <option value="">Pilih Kategori</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Satuan</label>
                        <select name="id_satuan" class="form-select" required id="edit_id_satuan">
                            <option value="">Pilih Satuan</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" class="form-control" min="0" step="0.0001">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Harga Beli</label>
                        <input type="number" name="harga_beli" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">New Modal</label>
                        <input type="number" name="harga_newmodal" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Sales</label>
                        <input type="number" name="harga_sales" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Kasir (P1/Level 1)</label>
                        <input type="number" name="harga_kasir" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P2/Level 2</label>
                        <input type="number" name="P2" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 2</label>
                        <input type="number" name="mqty2" class="form-control" min="0" step="0.0001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P3/Level 3</label>
                        <input type="number" name="P3" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 3</label>
                        <input type="number" name="mqty3" class="form-control" min="0" step="0.0001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Harga P4/Level 4</label>
                        <input type="number" name="P4" class="form-control" min="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Qty Level 4</label>
                        <input type="number" name="mqty4" class="form-control" min="0" step="0.0001">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Gambar Produk</label>
                        <div class="product-image-uploader">
                            <img id="edit_gambar_preview" class="product-image-preview" src="<?= base_url ?>uploads/no-image-available.png" alt="Pratinjau gambar produk">
                            <div class="flex-grow-1">
                                <input type="file" name="gambar_produk" class="form-control product-image-input" accept="image/jpeg,image/png,image/webp" data-preview="#edit_gambar_preview">
                                <div class="form-text">Kosongkan bila gambar lama tidak ingin diganti. Maksimal 3 MB.</div>
                                <button type="button" class="btn btn-sm btn-light-primary product-crop-again d-none mt-2">
                                    <i class="bi bi-crop me-1"></i>Atur ulang crop
                                </button>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="hapus_gambar" value="1" id="edit_hapus_gambar">
                                    <label class="form-check-label" for="edit_hapus_gambar">Hapus gambar saat ini</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_ppn" value="1" id="edit_is_ppn">
                            <label class="form-check-label" for="edit_is_ppn">
                                Kena PPN
                            </label>
                            <div class="form-text">Jika dicentang, harga transaksi otomatis menjadi harga jual + PPN per barang.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="status" value="1" id="edit_status">
                            <label class="form-check-label" for="edit_status">
                                Aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_poin" value="1" id="edit_is_poin">
                            <label class="form-check-label" for="edit_is_poin">
                                Dapat Poin
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal upload cepat gambar dari tabel barang -->
<div class="modal fade" id="product_image_modal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="quick-product-image-form" enctype="multipart/form-data">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="product_image_title">Ubah Gambar Produk</h5>
                        <div class="text-muted small mt-1">Upload gambar tanpa membuka seluruh form edit barang.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="quick_product_image_id">
                    <div id="quick_product_image_message" class="alert d-none mb-3" role="alert"></div>
                    <div class="product-image-uploader product-image-uploader--quick">
                        <img id="product_image_full" class="product-image-preview product-image-preview--quick" src="<?= base_url ?>uploads/no-image-available.png" alt="Pratinjau gambar produk">
                        <div class="flex-grow-1 min-w-0">
                            <label class="form-label required" for="quick_product_image_input">Pilih gambar baru</label>
                            <input type="file" id="quick_product_image_input" name="gambar_produk"
                                class="form-control product-image-input" accept="image/jpeg,image/png,image/webp"
                                data-preview="#product_image_full" required>
                            <div class="form-text">Format JPG, PNG, atau WebP. Ukuran maksimal 3 MB.</div>
                            <button type="button" class="btn btn-sm btn-light-primary product-crop-again d-none mt-2">
                                <i class="bi bi-crop me-1"></i>Atur ulang crop
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a id="product_image_original" class="btn btn-light-primary" href="#" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Lihat gambar saat ini
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-arrow-up me-1"></i>Upload Gambar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Editor crop mandiri agar dapat dibuka di atas modal tambah/edit -->
<div id="product_crop_editor" class="product-crop-overlay" role="dialog" aria-modal="true" aria-labelledby="product_crop_title" aria-hidden="true">
    <div class="product-crop-panel">
        <div class="product-crop-header">
            <div>
                <h5 id="product_crop_title" class="mb-1">Atur Crop Gambar</h5>
                <div class="text-muted small">Geser gambar dan atur zoom hingga bagian yang diinginkan memenuhi kotak.</div>
            </div>
            <button type="button" class="btn-close" id="product_crop_close" aria-label="Tutup"></button>
        </div>
        <div class="product-crop-body">
            <div class="product-crop-canvas-wrap">
                <canvas id="product_crop_canvas" width="900" height="900" aria-label="Area crop gambar"></canvas>
                <div class="product-crop-grid" aria-hidden="true"></div>
            </div>
            <div class="product-crop-controls">
                <label for="product_crop_zoom" class="form-label mb-1">Zoom</label>
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-zoom-out text-muted"></i>
                    <input type="range" id="product_crop_zoom" class="form-range flex-grow-1" min="1" max="3" step="0.01" value="1">
                    <i class="bi bi-zoom-in text-muted"></i>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-light" id="product_crop_rotate_left"><i class="bi bi-arrow-counterclockwise me-1"></i>Putar kiri</button>
                    <button type="button" class="btn btn-sm btn-light" id="product_crop_rotate_right"><i class="bi bi-arrow-clockwise me-1"></i>Putar kanan</button>
                    <button type="button" class="btn btn-sm btn-light" id="product_crop_reset"><i class="bi bi-arrow-repeat me-1"></i>Reset</button>
                </div>
            </div>
        </div>
        <div class="product-crop-footer">
            <button type="button" class="btn btn-light" id="product_crop_cancel">Batal</button>
            <button type="button" class="btn btn-primary" id="product_crop_apply"><i class="bi bi-check-lg me-1"></i>Terapkan Crop</button>
        </div>
    </div>
</div>
<style type="text/css">
    /* ⚠️ Ikon warning berkedip halus kuning */
    .warning-blink {
        color: #ffc107;
        /* Warna kuning Bootstrap */
        animation: blinkYellow 1.5s infinite;
    }

    @keyframes blinkYellow {

        0%,
        100% {
            opacity: 1;
            color: #ffc107;
            /* Kuning terang */
        }

        50% {
            opacity: 0.5;
            color: #ffeb3b;
            /* Kuning muda saat berkedip */
        }
    }

    .product-image-uploader {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
    }

    .product-image-preview,
    .product-table-thumb {
        object-fit: cover;
        border: 1px solid #e4e6ef;
        background: #fff;
    }

    .product-image-preview {
        width: 96px;
        height: 96px;
        border-radius: 10px;
    }

    .product-table-thumb {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border-radius: 8px;
    }

    .product-table-image-button {
        position: relative;
        display: inline-flex;
        flex: 0 0 48px;
        padding: 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
        cursor: pointer;
    }

    .product-table-image-edit-badge {
        position: absolute;
        right: -4px;
        bottom: -4px;
        display: inline-flex;
        width: 20px;
        height: 20px;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        border-radius: 50%;
        background: #009ef7;
        color: #fff;
        font-size: .62rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .2);
    }

    .product-table-image-button:hover .product-table-thumb,
    .product-table-image-button:focus-visible .product-table-thumb {
        border-color: #009ef7;
        box-shadow: 0 0 0 3px rgba(0, 158, 247, .16);
    }

    .product-image-full {
        display: block;
        width: auto;
        max-width: 100%;
        max-height: 70vh;
        margin: 0 auto;
        border-radius: 10px;
        object-fit: contain;
    }

    .product-image-preview--quick {
        width: 144px;
        height: 144px;
        flex: 0 0 144px;
    }

    .product-image-uploader--quick {
        align-items: flex-start;
    }

    .product-crop-overlay {
        position: fixed;
        inset: 0;
        z-index: 1080;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, .72);
    }

    .product-crop-overlay.is-open {
        display: flex;
    }

    .product-crop-panel {
        width: min(620px, 100%);
        max-height: calc(100vh - 32px);
        overflow-y: auto;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 24px 64px rgba(15, 23, 42, .3);
    }

    .product-crop-header,
    .product-crop-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
    }

    .product-crop-header {
        border-bottom: 1px solid #eff2f5;
    }

    .product-crop-footer {
        justify-content: flex-end;
        border-top: 1px solid #eff2f5;
    }

    .product-crop-body {
        padding: 20px;
    }

    .product-crop-canvas-wrap {
        position: relative;
        width: min(460px, 100%);
        margin: 0 auto;
        overflow: hidden;
        border-radius: 10px;
        background: #e2e8f0;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .12);
    }

    #product_crop_canvas {
        display: block;
        width: 100%;
        height: auto;
        cursor: grab;
        touch-action: none;
        user-select: none;
    }

    #product_crop_canvas.is-dragging {
        cursor: grabbing;
    }

    .product-crop-grid {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image:
            linear-gradient(to right, transparent 33.2%, rgba(255, 255, 255, .65) 33.33%, rgba(255, 255, 255, .65) 33.65%, transparent 33.75%, transparent 66.2%, rgba(255, 255, 255, .65) 66.33%, rgba(255, 255, 255, .65) 66.65%, transparent 66.75%),
            linear-gradient(to bottom, transparent 33.2%, rgba(255, 255, 255, .65) 33.33%, rgba(255, 255, 255, .65) 33.65%, transparent 33.75%, transparent 66.2%, rgba(255, 255, 255, .65) 66.33%, rgba(255, 255, 255, .65) 66.65%, transparent 66.75%);
        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .85);
    }

    .product-crop-controls {
        width: min(460px, 100%);
        margin: 16px auto 0;
    }

    @media (max-width: 575.98px) {
        .product-image-uploader--quick {
            align-items: stretch;
            flex-direction: column;
        }

        .product-image-preview--quick {
            width: 132px;
            height: 132px;
            flex-basis: 132px;
            align-self: center;
        }

        .product-crop-header,
        .product-crop-footer,
        .product-crop-body {
            padding: 14px;
        }
    }
</style>
<script>
    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    let membersDT;
    const baseUrl = "../classes/Master.php";

    function load_data() {
        membersDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            stateSave: true,
            autoWidth: false, // ⬅️ tambahkan ini
            ajax: {
                url: baseUrl + "?f=get_data_barang",
                type: "POST",
                data: function(d) {
                    // kirim semua filter ke server
                    d.filter_kategori = $('#filter_kategori').val();
                    d.filter_status = $('#filter_status').val();
                    d.filter_ppn = $('#filter_ppn').val();
                },
                dataSrc: function(json) {
                    if (!json.data) {
                        console.error("Format JSON tidak sesuai:", json);
                        show_msg("DataTables error: cek response JSON di console", "danger");
                        return [];
                    }
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.error("AJAX Error:", xhr.responseText);
                    show_msg("Terjadi kesalahan saat memuat data", "danger");
                }
            },
            columns: [{
                    data: "no",
                    className: "text-center fw-bold"
                },
                {
                    data: "barcode"
                },
                {
                    data: null,
                    className: "text-start",
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex align-items-center gap-3">
                                <button type="button" class="product-table-image-button quick-edit-product-image"
                                        data-product-id="${row.id}"
                                        data-image-src="${escapeHtml(row.gambar_url)}"
                                        data-image-name="${escapeHtml(row.nama_produk)}"
                                        aria-label="Ubah gambar ${escapeHtml(row.nama_produk)}" title="Klik untuk mengganti gambar">
                                    <img class="product-table-thumb" src="${escapeHtml(row.gambar_url)}" alt="Gambar ${escapeHtml(row.nama_produk)}" loading="lazy"
                                         onerror="this.src='<?= base_url ?>uploads/no-image-available.png'">
                                    <span class="product-table-image-edit-badge" aria-hidden="true"><i class="bi bi-camera"></i></span>
                                </button>
                                <div class="d-flex flex-column">
                                    <div class="fw-bold text-dark">${escapeHtml(row.nama_produk)}</div>
                                    <div class="text-muted small mt-1">${escapeHtml(row.nama_kategori || '-')}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                {
                    data: "nama_satuan"
                },
                {
                    data: "stok",
                    className: "text-end fw-bold",
                    render: function(data, type, row) {
                        const stokVal = SarinahNumbers.fromApi(data);
                        if (type !== 'display') return stokVal;

                        // 🧮 Tentukan format tampilan: bulat atau decimal
                        const stokFormatted = SarinahNumbers.formatQuantity(stokVal, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 4
                        });

                        // ⚙️ Logika tampilan stok
                        if (stokVal <= 0) {
                            // 🔴 stok kosong atau minus → teks merah + ikon warning kuning
                            return `
                                <span class="text-danger">
                                    ${stokFormatted}
                                    <i class="fa fa-exclamation-triangle warning-blink ms-1" 
                                       title="Stok kosong atau negatif"></i>
                                </span>`;
                        } else {
                            // ✅ stok normal
                            return `<span class="text-dark">${stokFormatted}</span>`;
                        }
                    }
                },
                {
                    data: "harga_beli",
                    className: "text-end fw-bold",
                    render: function(data) {
                        return data ? 'Rp ' + parseFloat(data).toLocaleString('id-ID') : '-';
                    }
                },
                {
                    data: "harga_newmodal",
                    className: "text-end fw-bold",
                    render: function(data) {
                        return data ? 'Rp ' + parseFloat(data).toLocaleString('id-ID') : '-';
                    }
                },
                {
                    data: "harga_sales",
                    className: "text-end fw-bold",
                    render: function(data) {
                        return data ? 'Rp ' + parseFloat(data).toLocaleString('id-ID') : '-';
                    }
                },
                {
                    data: "harga_kasir",
                    className: "text-end fw-bold",
                    render: function(data) {
                        return data ? 'Rp ' + parseFloat(data).toLocaleString('id-ID') : '-';
                    }
                },
                {
                    data: null,
                    className: "text-center",
                    render: function(data, type, row) {
                        const statusBadge = row.status == 1 ?
                            '<span class="badge bg-success d-block mb-1">Aktif</span>' :
                            '<span class="badge bg-danger d-block mb-1">Nonaktif</span>';

                        const ppnText = row.is_ppn == 1 ?
                            '<small class="text-primary me-2"><i class="fas fa-check"></i> PPN</small>' :
                            '<small class="text-danger me-2"><i class="fas fa-times"></i> Non PPN</small>';

                        const poinText = row.is_poin == 1 ?
                            '<small class="text-primary"><i class="fas fa-check"></i> Dapat Poin</small>' :
                            '<small class="text-danger"><i class="fas fa-times"></i> Non Poin</small>';

                        return `${statusBadge}${ppnText}${poinText}`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: "text-center",
                    render: (d, t, row) => `
                    <button class="btn btn-icon btn-light-primary btn-sm me-1 edit_data" data-id="${row.id}" title="Edit Data.">
                        <i class="fa fa-edit"></i>
                    </button>
                    <button class="btn btn-icon btn-light-danger btn-sm delete_data" data-id="${row.id}" data-name="${escapeHtml(row.nama_produk)}" title="Hapus atau Nonaktifkan Data.">
                        <i class="fa fa-trash"></i>
                    </button>
                    `
                }
            ],
            order: [
                [0, 'desc']
            ],
            initComplete: function() {
                $('#table td, #table th').addClass('py-2 px-3');
            },
            language: {
                oPaginate: {
                    sNext: '<i class="fa fa-angle-right"></i>',
                    sPrevious: '<i class="fa fa-angle-left"></i>',
                    sFirst: '<i class="fa fa-step-backward"></i>',
                    sLast: '<i class="fa fa-step-forward"></i>'
                }
            }
        });

        // 🔄 Event: reload DataTable ketika filter berubah
        $('#filter_kategori, #filter_status, #filter_ppn').on('change', function() {
            membersDT.ajax.reload();
        });
        // Event handler untuk edit modal
        $('#table').on('click', '.edit_data', function() {
            const id = $(this).data('id');
            $.ajax({
                url: "../classes/Master.php?f=get_single_barang",
                type: "POST",
                data: {
                    id
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status) {
                        // Isi form dengan data yang diterima
                        for (let k in resp.data) {
                            if (k === 'is_ppn' || k === 'status' || k === 'is_poin') {
                                $('#edit_modal').find(`[name="${k}"]`).prop('checked', resp.data[k] == 1);
                            } else if (k === 'gambar_produk' || k === 'gambar_url') {
                                continue;
                            } else {
                                $('#edit_modal').find(`[name="${k}"]`).val(formatFieldValue(k, resp.data[k]));
                            }
                        }

                        clearProductCropState($('#edit-barang-frm input[name="gambar_produk"]').get(0));
                        $('#edit_gambar_preview').attr('src', resp.data.gambar_url || '<?= base_url ?>uploads/no-image-available.png');
                        $('#edit_hapus_gambar').prop('checked', false);

                        // Load dropdown options dengan nilai yang dipilih
                        loadDropdownOptionsWithSelected('#edit_id_kategori', 'kategori', resp.data.id_kategori);
                        loadDropdownOptionsWithSelected('#edit_id_satuan', 'satuan', resp.data.id_satuan);
                        $('#edit_modal').modal('show');
                    } else {
                        alert("Data tidak ditemukan");
                    }
                },
                error: () => alert("Gagal fetch data")
            });
        });
        // Event handler untuk deleteData
        $('#table').on('click', '.delete_data', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            _conf(`Yakin hapus data <b>${escapeHtml(name)}</b>?<br><small class="text-muted">Barang yang sudah dipakai transaksi akan dinonaktifkan agar riwayat transaksi tetap aman.</small>`, "deleteData", [id]);
        });
    }

    // Function to load dropdown options - DIPERBAIKI
    function loadDropdownOptions(selector, type) {
        $.ajax({
            url: "../classes/Master.php?f=get_" + type,
            type: "GET",
            dataType: "json",
            success: function(data) {
                let options = '<option value="">Pilih ' +
                    (type === 'kategori' ? 'Kategori' : 'Satuan') + '</option>';

                if (data && data.length > 0) {
                    data.forEach(item => {
                        let value = item.id;
                        let text = item.nama_kategori || item.nama_satuan || item.kategori || item.satuan;
                        options += `<option value="${value}">${text}</option>`;
                    });
                }
                $(selector).html(options);
            },
            error: function(xhr, status, error) {
                console.error("Error loading " + type + ":", error);
                $(selector).html('<option value="">Error loading data</option>');
            }
        });
    }

    // Function to load dropdown options for edit modal with selected value - DIPERBAIKI
    function loadDropdownOptionsWithSelected(selector, type, selectedValue) {
        $.ajax({
            url: "../classes/Master.php?f=get_" + type,
            type: "GET",
            dataType: "json",
            success: function(data) {
                let options = '<option value="">Pilih ' +
                    (type === 'kategori' ? 'Kategori' : 'Satuan') + '</option>';

                if (data && data.length > 0) {
                    data.forEach(item => {
                        let value = item.id;
                        let text = item.nama_kategori || item.nama_satuan || item.kategori || item.satuan;
                        let selected = value == selectedValue ? 'selected' : '';
                        options += `<option value="${value}" ${selected}>${text}</option>`;
                    });
                }
                $(selector).html(options);
            },
            error: function(xhr, status, error) {
                console.error("Error loading " + type + ":", error);
                $(selector).html('<option value="">Error loading data</option>');
            }
        });
    }

    // Load dropdown options saat modal edit dibuka
    $('#edit_modal').on('show.bs.modal', function() {
        // Preload dropdown untuk modal edit
        loadDropdownOptions('#edit_id_kategori', 'kategori');
        loadDropdownOptions('#edit_id_satuan', 'satuan');
    });

    // Helper tampilkan pesan ke #msg
    function show_msg(message, type = "primary") {
        let _el = $('<div>')
            .hide()
            .addClass('alert alert-' + type + ' alert_msg')
            .text(message);
        $('#msg').append(_el);
        _el.show('slow');
        setTimeout(() => {
            _el.hide('slow').remove();
        }, 2500);
    }

    function parseFormQuantity(value) {
        const normalized = String(value ?? '').trim();
        return normalized.includes(',')
            ? SarinahNumbers.parseLocaleQuantity(normalized)
            : SarinahNumbers.fromApi(normalized);
    }

    function parseFormMoney(value) {
        return SarinahNumbers.parseMoney(value);
    }

    const wholeMoneyFields = ['harga_beli', 'harga_newmodal', 'harga_sales', 'harga_kasir', 'P2', 'P3', 'P4'];
    const wholeMoneyFieldSelector = wholeMoneyFields.map(field => `[name="${field}"]`).join(',');
    const quantityFields = ['stok', 'mqty2', 'mqty3', 'mqty4'];
    const quantityFieldSelector = quantityFields.map(field => `[name="${field}"]`).join(',');

    function formatWholeMoney(value) {
        return String(Math.round(parseFormMoney(value)));
    }

    function formatQuantity(value) {
        const rounded = Math.round(parseFormQuantity(value) * 10000) / 10000;
        return String(Object.is(rounded, -0) ? 0 : rounded);
    }

    function formatFieldValue(field, value) {
        if (wholeMoneyFields.includes(field)) return formatWholeMoney(value);
        if (quantityFields.includes(field)) return formatQuantity(value);
        return value;
    }

    function normalizeMoneyFields(form) {
        wholeMoneyFields.forEach(field => {
            const input = form.find(`[name="${field}"]`);
            if (input.length) input.val(formatWholeMoney(input.val()));
        });
    }

    function normalizeQuantityFields(form) {
        quantityFields.forEach(field => {
            const input = form.find(`[name="${field}"]`);
            if (input.length) input.val(formatQuantity(input.val()));
        });
    }

    $(document).on('blur', wholeMoneyFieldSelector, function() {
        $(this).val(formatWholeMoney($(this).val()));
    });

    $(document).on('blur', quantityFieldSelector, function() {
        $(this).val(formatQuantity($(this).val()));
    });

    function validateTieredPricingForm(form) {
        const tiers = [2, 3, 4].map(level => ({
            level,
            priceField: `P${level}`,
            qtyField: `mqty${level}`,
            price: parseFormMoney(form.find(`[name="P${level}"]`).val()),
            qty: parseFormQuantity(form.find(`[name="mqty${level}"]`).val())
        }));

        const activeTiers = [];
        for (const tier of tiers) {
            const isActive = tier.price > 0 || tier.qty > 0;
            if (!isActive) continue;

            if (tier.price <= 0) {
                return {
                    valid: false,
                    field: tier.priceField,
                    message: `Harga P${tier.level} wajib diisi jika Min Qty Level ${tier.level} diisi`
                };
            }

            if (tier.qty <= 0) {
                return {
                    valid: false,
                    field: tier.qtyField,
                    message: `Min Qty Level ${tier.level} wajib lebih dari 0 jika Harga P${tier.level} diisi`
                };
            }

            activeTiers.push(tier);
        }

        const seenQty = {};
        for (const tier of activeTiers) {
            if (seenQty[tier.qty]) {
                return {
                    valid: false,
                    field: tier.qtyField,
                    message: `Min Qty Level ${tier.level} tidak boleh sama dengan Level ${seenQty[tier.qty]} (Qty ${tier.qty})`
                };
            }

            seenQty[tier.qty] = tier.level;
        }

        let previousTier = null;
        for (const tier of activeTiers) {
            if (previousTier && tier.qty <= previousTier.qty) {
                return {
                    valid: false,
                    field: tier.qtyField,
                    message: `Min Qty Level ${tier.level} harus lebih besar dari Level ${previousTier.level}`
                };
            }

            previousTier = tier;
        }

        return {
            valid: true
        };
    }

    function focusFormField(form, fieldName) {
        const field = form.find(`[name="${fieldName}"]`);
        if (!field.length) return;

        field.trigger('focus');
        if (typeof field.get(0).select === 'function') {
            field.get(0).select();
        }
    }

    const defaultProductImage = '<?= base_url ?>uploads/no-image-available.png';
    const croppedProductImages = new WeakMap();
    const originalProductImages = new WeakMap();
    const croppedPreviewUrls = new WeakMap();
    const cropCanvas = document.getElementById('product_crop_canvas');
    const cropContext = cropCanvas.getContext('2d');
    const cropEditor = $('#product_crop_editor');
    const cropZoomInput = $('#product_crop_zoom');
    let activeCropInput = null;
    let activeCropImage = null;
    let activeCropFile = null;
    let cropObjectUrl = '';
    let cropPreviousPreview = '';
    let cropPreviousResult = null;
    let cropPreviousOriginal = null;
    let cropRotation = 0;
    let cropZoom = 1;
    let cropOffsetX = 0;
    let cropOffsetY = 0;
    let cropDragging = false;
    let cropPointerId = null;
    let cropLastPointer = null;
    let cropApplied = false;
    let bodyOverflowBeforeCrop = '';

    function setInputFile(input, file) {
        if (!input || !file || typeof DataTransfer === 'undefined') return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function clearProductCropState(input) {
        if (!input) return;

        const previewUrl = croppedPreviewUrls.get(input);
        if (previewUrl) URL.revokeObjectURL(previewUrl);

        croppedProductImages.delete(input);
        originalProductImages.delete(input);
        croppedPreviewUrls.delete(input);
        input.value = '';
        $(input).siblings('.product-crop-again').addClass('d-none');
    }

    function productFormData(form) {
        const formData = new FormData(form.get(0));
        const input = form.find('input[name="gambar_produk"]').get(0);
        const croppedFile = input ? croppedProductImages.get(input) : null;
        if (croppedFile) {
            formData.set('gambar_produk', croppedFile, croppedFile.name);
        }
        return formData;
    }

    function rotatedImageSize() {
        const swapSides = cropRotation % 180 !== 0;
        return {
            width: swapSides ? activeCropImage.naturalHeight : activeCropImage.naturalWidth,
            height: swapSides ? activeCropImage.naturalWidth : activeCropImage.naturalHeight
        };
    }

    function cropScale() {
        const size = rotatedImageSize();
        return Math.max(cropCanvas.width / size.width, cropCanvas.height / size.height) * cropZoom;
    }

    function clampCropOffsets() {
        if (!activeCropImage) return;
        const size = rotatedImageSize();
        const scale = cropScale();
        const maxX = Math.max(0, (size.width * scale - cropCanvas.width) / 2);
        const maxY = Math.max(0, (size.height * scale - cropCanvas.height) / 2);
        cropOffsetX = Math.max(-maxX, Math.min(maxX, cropOffsetX));
        cropOffsetY = Math.max(-maxY, Math.min(maxY, cropOffsetY));
    }

    function drawCropCanvas() {
        if (!activeCropImage) return;

        clampCropOffsets();
        cropContext.save();
        cropContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        cropContext.fillStyle = '#ffffff';
        cropContext.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
        cropContext.translate(cropCanvas.width / 2 + cropOffsetX, cropCanvas.height / 2 + cropOffsetY);
        cropContext.rotate(cropRotation * Math.PI / 180);

        const scale = cropScale();
        const width = activeCropImage.naturalWidth * scale;
        const height = activeCropImage.naturalHeight * scale;
        cropContext.drawImage(activeCropImage, -width / 2, -height / 2, width, height);
        cropContext.restore();
    }

    function resetCropTransform() {
        cropRotation = 0;
        cropZoom = 1;
        cropOffsetX = 0;
        cropOffsetY = 0;
        cropZoomInput.val('1');
        drawCropCanvas();
    }

    function closeCropEditor(restorePrevious = true) {
        if (!activeCropInput) return;
        const inputToFocus = activeCropInput;

        if (restorePrevious && !cropApplied) {
            const preview = $($(activeCropInput).data('preview'));
            preview.attr('src', cropPreviousPreview || defaultProductImage);

            if (cropPreviousResult) {
                croppedProductImages.set(activeCropInput, cropPreviousResult);
                if (cropPreviousOriginal) originalProductImages.set(activeCropInput, cropPreviousOriginal);
                setInputFile(activeCropInput, cropPreviousResult);
                $(activeCropInput).siblings('.product-crop-again').removeClass('d-none');
            } else {
                activeCropInput.value = '';
            }
        }

        if (cropObjectUrl) URL.revokeObjectURL(cropObjectUrl);
        cropObjectUrl = '';
        activeCropInput = null;
        activeCropImage = null;
        activeCropFile = null;
        cropDragging = false;
        cropPointerId = null;
        cropLastPointer = null;
        cropEditor.removeClass('is-open').attr('aria-hidden', 'true');
        document.body.style.overflow = bodyOverflowBeforeCrop;
        setTimeout(() => inputToFocus.focus(), 0);
    }

    function openCropEditor(input, file) {
        if (!input || !file) return;

        activeCropInput = input;
        activeCropFile = file;
        cropPreviousPreview = $($(input).data('preview')).attr('src') || defaultProductImage;
        cropPreviousResult = croppedProductImages.get(input) || null;
        cropPreviousOriginal = originalProductImages.get(input) || null;
        cropApplied = false;
        cropObjectUrl = URL.createObjectURL(file);
        activeCropImage = new Image();

        activeCropImage.onload = function() {
            resetCropTransform();
            bodyOverflowBeforeCrop = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            cropEditor.addClass('is-open').attr('aria-hidden', 'false');
            $('#product_crop_apply').trigger('focus');
        };
        activeCropImage.onerror = function() {
            URL.revokeObjectURL(cropObjectUrl);
            cropObjectUrl = '';
            activeCropInput.value = '';
            activeCropInput = null;
            activeCropImage = null;
            show_msg('Gambar tidak dapat dibaca. Silakan pilih file lain.', 'warning');
        };
        activeCropImage.src = cropObjectUrl;
    }

    $(document).on('click', '.quick-edit-product-image', function() {
        const form = $('#quick-product-image-form');
        const input = form.find('input[name="gambar_produk"]').get(0);
        clearProductCropState(input);
        clearQuickImageMessage();

        const productId = Number($(this).attr('data-product-id')) || 0;
        const imageUrl = $(this).attr('data-image-src') || defaultProductImage;
        const productName = $(this).attr('data-image-name') || 'Gambar Produk';
        $('#quick_product_image_id').val(productId);
        $('#product_image_title').text('Ubah Gambar - ' + productName);
        $('#product_image_full').attr({
            src: imageUrl,
            alt: 'Pratinjau gambar ' + productName
        });
        $('#product_image_original').attr('href', imageUrl);
        $('#product_image_modal').modal('show');
    });

    $('#product_image_full').on('error', function() {
        $(this).attr('src', defaultProductImage);
        $('#product_image_original').attr('href', defaultProductImage);
    });

    function clearQuickImageMessage() {
        $('#quick_product_image_message')
            .addClass('d-none')
            .removeClass('alert-success alert-danger alert-warning')
            .text('');
    }

    function showQuickImageMessage(message, type = 'danger') {
        $('#quick_product_image_message')
            .removeClass('d-none alert-success alert-danger alert-warning')
            .addClass('alert-' + type)
            .text(message);
    }

    $('#quick-product-image-form').on('submit', function(event) {
        event.preventDefault();
        const form = $(this);
        const input = form.find('input[name="gambar_produk"]').get(0);
        const file = input && input.files ? input.files[0] : null;
        const croppedFile = input ? croppedProductImages.get(input) : null;

        if (!file && !croppedFile) {
            showQuickImageMessage('Pilih gambar baru yang ingin diupload.', 'warning');
            input?.focus();
            return;
        }

        clearQuickImageMessage();
        const submitButton = form.find('button[type="submit"]');
        $.ajax({
            url: baseUrl + '?f=update_barang_image',
            type: 'POST',
            data: productFormData(form),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Mengupload...');
            },
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    clearProductCropState(input);
                    $('#product_image_modal').modal('hide');
                    reloadDataTable(false);
                    show_msg(resp.msg || 'Gambar produk berhasil diperbarui.', 'success');
                } else {
                    showQuickImageMessage(resp && resp.msg ? resp.msg : 'Gambar gagal diperbarui.');
                }
            },
            error: function(xhr) {
                console.error('Quick image upload failed:', xhr.responseText);
                showQuickImageMessage('Terjadi kesalahan saat mengupload gambar.');
            },
            complete: function() {
                submitButton.prop('disabled', false).html('<i class="bi bi-cloud-arrow-up me-1"></i>Upload Gambar');
            }
        });
    });

    $('#product_image_modal').on('hidden.bs.modal', function() {
        const form = $('#quick-product-image-form');
        clearProductCropState(form.find('input[name="gambar_produk"]').get(0));
        form.get(0).reset();
        clearQuickImageMessage();
        $('#quick_product_image_id').val('');
        $('#product_image_full').attr('src', defaultProductImage);
        $('#product_image_original').attr('href', defaultProductImage);
    });

    $(document).on('change', '.product-image-input', function() {
        const file = this.files && this.files[0];
        if (!file) return;

        const showImageValidation = message => {
            if (this.form && this.form.id === 'quick-product-image-form') {
                showQuickImageMessage(message, 'warning');
            } else {
                show_msg(message, 'warning');
            }
        };

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.value = '';
            showImageValidation('Gunakan gambar berformat JPG, PNG, atau WebP.');
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            this.value = '';
            showImageValidation('Ukuran gambar produk maksimal 3 MB.');
            return;
        }

        if (this.form && this.form.id === 'quick-product-image-form') clearQuickImageMessage();
        openCropEditor(this, file);
    });

    $(document).on('click', '.product-crop-again', function() {
        const input = $(this).siblings('.product-image-input').get(0);
        const originalFile = input ? originalProductImages.get(input) : null;
        if (input && originalFile) openCropEditor(input, originalFile);
    });

    cropZoomInput.on('input', function() {
        cropZoom = Number(this.value) || 1;
        drawCropCanvas();
    });

    $('#product_crop_rotate_left, #product_crop_rotate_right').on('click', function() {
        cropRotation = (cropRotation + (this.id.endsWith('left') ? -90 : 90) + 360) % 360;
        cropOffsetX = 0;
        cropOffsetY = 0;
        drawCropCanvas();
    });

    $('#product_crop_reset').on('click', resetCropTransform);
    $('#product_crop_close, #product_crop_cancel').on('click', () => closeCropEditor(true));

    cropCanvas.addEventListener('pointerdown', function(event) {
        if (!activeCropImage) return;
        cropDragging = true;
        cropPointerId = event.pointerId;
        cropLastPointer = { x: event.clientX, y: event.clientY };
        cropCanvas.setPointerCapture(event.pointerId);
        cropCanvas.classList.add('is-dragging');
    });

    cropCanvas.addEventListener('pointermove', function(event) {
        if (!cropDragging || event.pointerId !== cropPointerId) return;
        const rect = cropCanvas.getBoundingClientRect();
        cropOffsetX += (event.clientX - cropLastPointer.x) * cropCanvas.width / rect.width;
        cropOffsetY += (event.clientY - cropLastPointer.y) * cropCanvas.height / rect.height;
        cropLastPointer = { x: event.clientX, y: event.clientY };
        drawCropCanvas();
    });

    function stopCropDragging(event) {
        if (event.pointerId !== cropPointerId) return;
        cropDragging = false;
        cropPointerId = null;
        cropLastPointer = null;
        cropCanvas.classList.remove('is-dragging');
    }

    cropCanvas.addEventListener('pointerup', stopCropDragging);
    cropCanvas.addEventListener('pointercancel', stopCropDragging);

    cropCanvas.addEventListener('wheel', function(event) {
        if (!activeCropImage) return;
        event.preventDefault();
        cropZoom = Math.max(1, Math.min(3, cropZoom + (event.deltaY < 0 ? .08 : -.08)));
        cropZoomInput.val(cropZoom);
        drawCropCanvas();
    }, { passive: false });

    $('#product_crop_apply').on('click', function() {
        if (!activeCropInput || !activeCropImage) return;
        const button = $(this).prop('disabled', true).text('Memproses...');

        cropCanvas.toBlob(function(blob) {
            button.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Terapkan Crop');
            if (!blob || !activeCropInput) {
                show_msg('Crop gambar gagal diproses. Silakan coba lagi.', 'warning');
                return;
            }

            const input = activeCropInput;
            const originalName = (activeCropFile.name || 'gambar').replace(/\.[^.]+$/, '');
            const croppedFile = new File([blob], originalName + '_crop.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now()
            });

            const oldPreviewUrl = croppedPreviewUrls.get(input);
            if (oldPreviewUrl) URL.revokeObjectURL(oldPreviewUrl);
            const previewUrl = URL.createObjectURL(croppedFile);

            croppedProductImages.set(input, croppedFile);
            originalProductImages.set(input, activeCropFile);
            croppedPreviewUrls.set(input, previewUrl);
            setInputFile(input, croppedFile);
            $($(input).data('preview')).attr('src', previewUrl);
            $(input).siblings('.product-crop-again').removeClass('d-none');
            if (input.form && input.form.id === 'edit-barang-frm') {
                $('#edit_hapus_gambar').prop('checked', false);
            }

            cropApplied = true;
            closeCropEditor(false);
        }, 'image/jpeg', .9);
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && cropEditor.hasClass('is-open')) {
            event.preventDefault();
            closeCropEditor(true);
        }
    });

    $('#edit_hapus_gambar').on('change', function() {
        if (this.checked) {
            clearProductCropState($('#edit-barang-frm input[name="gambar_produk"]').get(0));
            $('#edit_gambar_preview').attr('src', defaultProductImage);
        }
    });

    // Tambah data barang
    $('#new-barang-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        normalizeMoneyFields(form);
        normalizeQuantityFields(form);
        const tierValidation = validateTieredPricingForm(form);
        if (!tierValidation.valid) {
            show_msg(tierValidation.message, "warning");
            focusFormField(form, tierValidation.field);
            return;
        }

        $.ajax({
            url: "../classes/Master.php?f=save_barang",
            type: "POST",
            data: productFormData(form),
            processData: false,
            contentType: false,
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Menyimpan..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    form.get(0).reset();
                    clearProductCropState(form.find('input[name="gambar_produk"]').get(0));
                    $('#add_gambar_preview').attr('src', defaultProductImage);
                    $('#add_modal').modal('hide');
                    reloadDataTable();
                    show_msg("Data berhasil ditambahkan", "success");
                } else if (resp && resp.msg) {
                    show_msg(resp.msg, "danger");
                } else {
                    show_msg("Data gagal disimpan. Silakan coba lagi.", "danger");
                }
            },
            error: (xhr, status, err) => {
                console.error(err);
                show_msg("Terjadi kesalahan. Silakan coba lagi.", "danger");
            },
            complete: () => form.find("button[type=submit]").prop("disabled", false).text("Simpan")
        });
    });

    // Edit data barang
    $('#edit-barang-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        normalizeMoneyFields(form);
        normalizeQuantityFields(form);
        const tierValidation = validateTieredPricingForm(form);
        if (!tierValidation.valid) {
            show_msg(tierValidation.message, "warning");
            focusFormField(form, tierValidation.field);
            return;
        }

        $.ajax({
            url: "../classes/Master.php?f=update_barang",
            type: "POST",
            data: productFormData(form),
            processData: false,
            contentType: false,
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Memperbarui..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    clearProductCropState(form.find('input[name="gambar_produk"]').get(0));
                    $('#edit_modal').modal('hide');
                    reloadDataTable();
                    show_msg("Data berhasil diperbaharui", "success");
                } else if (resp && resp.msg) {
                    show_msg(resp.msg, "danger");
                } else {
                    show_msg("Update gagal. Silakan coba lagi.", "danger");
                }
            },
            error: (xhr, status, err) => {
                console.error(err);
                show_msg("Terjadi kesalahan. Silakan coba lagi.", "danger");
            },
            complete: () => form.find("button[type=submit]").prop("disabled", false).text("Update")
        });
    });

    /**
     * Reload / redraw DataTable
     */
    function reloadDataTable(resetPaging = false) {
        if (membersDT && $.fn.dataTable.isDataTable('#table')) {
            membersDT.ajax.reload(null, resetPaging);
        }
    }

    function deleteData(id) {
        start_loader();
        $.ajax({
            url: baseUrl + "?f=delete_barang",
            method: "POST",
            data: {
                id: id
            },
            dataType: "json",
            error: function(err) {
                console.error("AJAX Error:", err);
                alert_toast("Terjadi kesalahan koneksi ke server.", "error");
                end_loader();
            },
            success: function(resp) {
                if (resp && resp.status) {
                    if (resp.status === "success") {
                        const alertType = resp.action === "deactivated" ? "alert-warning" : "alert-primary";
                        let _el = $('<div>')
                            .hide()
                            .addClass("alert " + alertType + " alert_msg")
                            .text(resp.msg || "Data berhasil dihapus");
                        $(".modal").modal("hide");
                        $("#msg").append(_el);
                        _el.show("slow");
                        reloadDataTable();
                        setTimeout(() => {
                            _el.hide("slow").remove();
                        }, 2500);
                    } else if (resp.status === "failed" && resp.msg) {
                        let _el = $('<div>')
                            .hide()
                            .addClass("alert alert-danger alert_msg form-group")
                            .text(resp.msg);
                        $("#msg").append(_el);
                        _el.show("slow");
                    } else {
                        alert_toast("Terjadi kesalahan. Silakan coba lagi.", "error");
                    }
                } else {
                    alert_toast("Respon server tidak valid.", "error");
                }
                end_loader();
            }
        });
    }

    // Paksa input nama produk jadi huruf besar sebelum submit
    $('#new-barang-frm, #edit-barang-frm').on('submit', function() {
        $(this).find('input[name=nama_produk]').each(function() {
            $(this).val($(this).val().toUpperCase());
        });
    });

    // Jalankan saat dokumen siap
    $(document).ready(function() {
        load_data();

        $('#add_modal').on('hide.bs.modal', function() {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        $('#edit_modal').on('hide.bs.modal', function() {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        $('#confirm_modal').on('hide.bs.modal', function() {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        // Untuk form tambah barang
        $('#new-barang-frm input[name=nama], #new-barang-frm textarea[name=deskripsi], #new-barang-frm input[name=nama_produk]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        // Untuk form edit barang
        $('#edit-barang-frm input[name=nama], #edit-barang-frm textarea[name=deskripsi], #edit-barang-frm input[name=nama_produk]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

    });

    function hanyaAngka(evt) {
        var charCode = (evt.which) ? evt.which : event.keyCode
        if (charCode > 31 && (charCode < 48 || charCode > 57))
            return false;
        return true;
    }
</script>
