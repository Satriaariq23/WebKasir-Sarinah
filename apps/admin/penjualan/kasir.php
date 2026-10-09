<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/kasir.php
 *  DESCRIPTION: Kasir (Point of Sale) Interface
 *  AUTHOR: System
 * ==========================================================
 */
$editKasirSaleId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$kasirPpnRate = $_settings->info('ppn_rate');
if ($kasirPpnRate === false || $kasirPpnRate === '' || !is_numeric($kasirPpnRate)) {
    $kasirPpnRate = 11;
}
$kasirPpnRateDecimal = max(0, min(100, (float)$kasirPpnRate)) / 100;
?>

<div class="container-fluid cashier-page" id="container-wrapper">
    <div class="cashier-card card">
        <form id="kasir-form" method="post">
            <input type="hidden" name="saleid" id="saleid" value="">
            <input type="hidden" name="user_id" id="user_id" value="<?= $_settings->userdata('id') ?>">

            <div class="cashier-content">
                <!-- DISPLAY KASIR - Grand Total -->
                <section class="cashier-total-panel" aria-labelledby="cashier-total-label">
                    <div class="cashier-total-label" id="cashier-total-label">TOTAL BELANJA</div>
                    <div class="cashier-total-value" id="grand_total_display" aria-live="polite">Rp 0</div>
                    <div class="cashier-total-hint">
                        <i class="bi bi-upc-scan" aria-hidden="true"></i>
                        Scan barcode atau ketik nama produk
                    </div>
                </section>

                <!-- Transaction and customer information -->
                <section class="cashier-details-grid" aria-label="Informasi transaksi">
                    <div class="cashier-field">
                        <label class="cashier-label" for="sale_date">
                            <span>Tanggal &amp; Waktu</span><kbd class="cashier-shortcut-key">F3</kbd>
                        </label>
                        <input type="datetime-local" name="sale_date" id="sale_date"
                            class="form-control form-control-sm"
                            value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="cashier-field">
                        <label class="cashier-label" for="cashier_username">Kasir</label>
                        <input type="text" class="form-control form-control-sm" id="cashier_username"
                            value="<?= $_settings->userdata('username') ?>" readonly>
                    </div>

                    <div class="cashier-field">
                        <label class="cashier-label" for="sale_invoice">No. Invoice</label>
                        <input type="text" class="form-control form-control-sm"
                            name="sale_invoice" id="sale_invoice"
                            value="KSR-<?= date('YmdHis') ?>" readonly>
                    </div>

                    <div class="cashier-field">
                        <label class="cashier-label" for="cust_id">
                            <span>Member</span><kbd class="cashier-shortcut-key">F4</kbd>
                        </label>
                        <select name="cust_id" id="cust_id" class="form-control form-control-sm">
                            <option value="0" selected>Member Umum</option>
                        </select>
                    </div>

                    <div class="cashier-field">
                        <label class="cashier-label" for="catatan">
                            <span>Catatan</span><kbd class="cashier-shortcut-key">F6</kbd>
                        </label>
                        <input type="text" name="catatan" id="catatan"
                            class="form-control form-control-sm" placeholder="Opsional">
                    </div>

                </section>

                <section class="cashier-product-browser" aria-labelledby="cashier-product-browser-title">
                    <div class="cashier-product-browser-head">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="cashier-section-title mb-0" id="cashier-product-browser-title">
                                    <i class="bi bi-grid" aria-hidden="true"></i>
                                    Katalog Produk <kbd class="cashier-shortcut-key">/</kbd>
                                </div>
                                <span class="cashier-count-badge" id="catalog_product_count">0 Produk</span>
                            </div>
                            <div class="cashier-product-browser-hint">Klik kartu produk untuk langsung menambahkan 1 item</div>
                        </div>
                        <div class="cashier-gallery-search">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" id="product_gallery_search" class="form-control form-control-sm" placeholder="Cari nama atau barcode..." autocomplete="off">
                        </div>
                    </div>
                    <div id="product_gallery" class="cashier-product-grid" aria-live="polite">
                        <div class="cashier-product-loading">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            Memuat produk...
                        </div>
                    </div>
                </section>

                <!-- Product Entry -->
                <details class="cashier-entry" id="manual_product_entry">
                    <summary class="cashier-entry-summary" id="cashier-entry-title">
                        <span><i class="bi bi-keyboard" aria-hidden="true"></i> Input Produk Manual</span>
                        <small>Opsional <kbd class="cashier-shortcut-key">F2</kbd></small>
                    </summary>
                    <div class="cashier-entry-grid">
                        <div class="cashier-field cashier-entry-product">
                            <label class="cashier-label" for="cr_produk">
                                <span>Nama Produk</span><kbd class="cashier-shortcut-key">F2</kbd>
                            </label>
                            <input class="form-control form-control-sm" type="text" id="cr_produk">
                        </div>
                        <div class="cashier-field">
                            <label class="cashier-label" for="sale_qty">
                                <span>Quantity</span><kbd class="cashier-shortcut-key">Alt+J</kbd>
                            </label>
                            <input type="text" value="1"
                                class="form-control form-control-sm cashier-quantity-input" id="sale_qty"
                                inputmode="decimal" autocomplete="off" data-max-decimals="4"
                                aria-label="Quantity produk">
                        </div>
                        <div class="cashier-field">
                            <label class="cashier-label" for="satuan">Satuan</label>
                            <input type="text" class="form-control form-control-sm"
                                id="satuan" placeholder="" disabled>
                            <input type="hidden" id="satuan_id">
                        </div>
                        <div class="cashier-field">
                            <label class="cashier-label" for="sale_harga">Harga</label>
                            <input type="text" class="form-control form-control-sm text-end"
                                id="sale_harga" value="0" readonly tabindex="-1"
                                aria-readonly="true" title="Harga mengikuti master barang">
                        </div>
                        <div class="cashier-field cashier-entry-action">
                            <span class="cashier-label" aria-hidden="true">
                                <span>Aksi</span><kbd class="cashier-shortcut-key">Enter</kbd>
                            </span>
                            <button type="button" class="btn btn-success" id="add_to_list">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                <span>Tambah</span>
                            </button>
                        </div>
                    </div>
                </details>

                <!-- Product List Table -->
                <section class="cashier-table-card" aria-label="Daftar produk transaksi">
                    <div class="cashier-cart-head">
                        <div class="cashier-section-title mb-0">
                            <i class="bi bi-cart3" aria-hidden="true"></i>
                            Keranjang Belanja
                            <kbd class="cashier-shortcut-key">Alt+Q</kbd>
                            <kbd class="cashier-shortcut-key">Alt+I</kbd>
                        </div>
                        <span class="cashier-count-badge" id="cart_item_count">0 Item</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table cashier-table" id="list">
                            <colgroup>
                                <col style="width: 4%;">
                                <col style="width: 25%;">
                                <col style="width: 13%;">
                                <col style="width: 7%;">
                                <col style="width: 9%;">
                                <col style="width: 12%;">
                                <col style="width: 10%;">
                                <col style="width: 13%;">
                                <col style="width: 7%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="text-center" scope="col">#</th>
                                    <th class="text-start" scope="col">Produk</th>
                                    <th class="text-start" scope="col">Barcode</th>
                                    <th class="text-center" scope="col">Stok</th>
                                    <th class="text-center" scope="col">Qty</th>
                                    <th class="text-end" scope="col">Harga</th>
                                    <th class="text-end" scope="col">Diskon</th>
                                    <th class="text-end" scope="col">Subtotal</th>
                                    <th class="text-center" scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="product_rows">
                                <tr class="cashier-empty-row">
                                    <td colspan="9">
                                        <div class="cashier-empty-state">
                                            <i class="bi bi-cart3" aria-hidden="true"></i>
                                            <span>Belum ada produk dalam transaksi</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="cashier-summary-row">
                                    <th class="text-end" colspan="7" scope="row">Sub Total</th>
                                    <th class="text-end" colspan="2" id="sub_total_display">0</th>
                                </tr>
                                <tr class="cashier-summary-row cashier-summary-row--discount">
                                    <th class="text-end" colspan="7" scope="row">
                                        Diskon <kbd class="cashier-shortcut-key">Alt+S</kbd>
                                    </th>
                                    <th class="text-end" colspan="2">
                                        <input name="sale_disc" id="sale_disc"
                                            class="form-control form-control-sm text-end cashier-invoice-discount cashier-money-input"
                                            type="text" value="0" inputmode="numeric" pattern="[0-9.]*"
                                            autocomplete="off" spellcheck="false" aria-label="Diskon transaksi">
                                    </th>
                                </tr>
                                <tr class="cashier-summary-row cashier-summary-row--grand">
                                    <th class="text-end" colspan="7" scope="row">GRAND TOTAL</th>
                                    <th class="text-end" colspan="2">
                                        <span id="grand_total_text">0</span>
                                        <input type="hidden" name="sale_total" id="sale_total" value="0">
                                        <input type="hidden" name="sale_ppn" id="sale_ppn" value="0">
                                    </th>
                                </tr>
                                <tr class="cashier-summary-row cashier-summary-row--change">
                                    <th class="text-end" colspan="7" scope="row">KEMBALIAN</th>
                                    <th class="text-end" colspan="2">
                                        <span id="change_amount_display">0</span>
                                        <input type="hidden" name="change_amount" id="change_amount" value="0">
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <details id="kasir_shortcut_help" class="kasir-shortcut-help" aria-label="Daftar shortcut keyboard kasir">
                        <summary class="shortcut-title">
                            <i class="bi bi-keyboard" aria-hidden="true"></i>
                            <span>Shortcut Keyboard</span>
                            <small>Klik untuk melihat</small>
                        </summary>
                        <div class="shortcut-grid">
                            <span><kbd>/</kbd> Cari produk</span>
                            <span><kbd>F2</kbd> Produk manual</span>
                            <span><kbd>F3</kbd> Tanggal transaksi</span>
                            <span><kbd>Alt</kbd> + <kbd>J</kbd> Jumlah manual</span>
                            <span><kbd>Alt</kbd> + <kbd>Q</kbd> Ubah qty</span>
                            <span><kbd>Alt</kbd> + <kbd>I</kbd> Diskon item</span>
                            <span><kbd>Alt</kbd> + <kbd>X</kbd> Hapus item</span>
                            <span><kbd>F4</kbd> Member</span>
                            <span><kbd>F6</kbd> Catatan</span>
                            <span><kbd>Alt</kbd> + <kbd>S</kbd> Diskon</span>
                            <span><kbd>F8</kbd> / <kbd>Alt</kbd> + <kbd>B</kbd> Bayar</span>
                            <span><kbd>Alt</kbd> + <kbd>H</kbd> Bantuan shortcut</span>
                            <span><kbd>Enter</kbd> Lanjut / tambah</span>
                            <span><kbd>Esc</kbd> Batal / reset</span>
                            <span><kbd>Modal</kbd> <kbd>F2</kbd> Bayar pas</span>
                            <span><kbd>Modal</kbd> <kbd>F6</kbd> Jumlah bayar</span>
                            <span><kbd>Modal</kbd> <kbd>F9</kbd> Simpan</span>
                            <span><kbd>Modal</kbd> <kbd>Enter</kbd> Simpan cetak</span>
                            <span><kbd>Alt</kbd> + <kbd>T/D/Q/E</kbd> Metode bayar</span>
                        </div>
                    </details>
                </section>

                <!-- Hidden inputs for product data -->
                <input type="hidden" name="produk_id[]" id="produk_id_array">
                <input type="hidden" name="qty[]" id="qty_array">
                <input type="hidden" name="harga[]" id="harga_array">
                <input type="hidden" name="disc_item[]" id="disc_item_array">
                <input type="hidden" name="is_ppn[]" id="is_ppn_array">
                <input type="hidden" name="satuan_id_array[]" id="satuan_id_array">
                <input type="hidden" name="isi[]" id="isi_array">

                <!-- Action Buttons - Opens Payment Modal -->
                <div class="cashier-actions">
                    <button type="button" class="btn btn-danger cashier-action-button" id="btn_reset">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        RESET <kbd class="cashier-shortcut-key cashier-shortcut-key--button">Esc</kbd>
                    </button>
                    <button type="button" class="btn btn-secondary cashier-action-button d-none" id="btn_cancel_edit">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        BATAL EDIT
                    </button>
                    <button type="button" class="btn btn-success cashier-action-button" id="btn_checkout_payment">
                        <i class="bi bi-credit-card" aria-hidden="true"></i>
                        BAYAR <kbd class="cashier-shortcut-key cashier-shortcut-key--button">F8</kbd>
                    </button>
                </div>

                <div id="form-message"></div>
            </div>
        </form>
    </div>
</div>

<!-- Include Modal Pembayaran -->
<?php include 'kasir_payment_modal.php'; ?>

<!-- Modal Denom Uang -->
<div class="modal fade" id="denomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Pembayaran Cepat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="1000">1.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="2000">2.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="5000">5.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="10000">10.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="20000">20.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="50000">50.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2 denom-btn" data-amount="100000">100.000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 mb-2" id="btn_exact_amount">Pas</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
    .cashier-page {
        --cashier-dark: #181c32;
        --cashier-dark-soft: #252a44;
        --cashier-accent: #50cd89;
        --cashier-accent-dark: #137a47;
        --cashier-border: #e4e6ef;
        --cashier-muted: #7e8299;
        --cashier-surface: #f8fafc;
        width: 100%;
        max-width: 100%;
        padding-bottom: 24px;
        box-sizing: border-box;
    }

    #kasir-form,
    .cashier-content,
    .cashier-content > * {
        min-width: 0;
        max-width: 100%;
    }

    .cashier-card {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
        border: 1px solid var(--cashier-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(24, 28, 50, 0.06);
    }

    .cashier-page-header {
        display: flex;
        min-height: 78px;
        align-items: center;
        justify-content: space-between;
        padding: 18px 32px !important;
        border-bottom: 1px solid var(--cashier-border);
        background: #fff;
    }

    .cashier-page-title {
        margin: 0 0 2px;
        color: #009ef7;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .cashier-page-subtitle {
        margin: 0;
        color: var(--cashier-muted);
        font-size: 0.82rem;
        font-weight: 500;
    }

    .cashier-content {
        padding: 20px 32px 28px;
    }

    .cashier-total-panel {
        display: flex;
        min-height: 142px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
        border-radius: 10px;
        background: var(--cashier-dark);
        color: #fff;
        text-align: center;
    }

    .cashier-total-label {
        color: #fff;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .cashier-total-value {
        margin: 4px 0 2px;
        color: var(--cashier-accent);
        font-size: clamp(2.6rem, 4vw, 3.65rem);
        font-weight: 700;
        line-height: 1.05;
    }

    .cashier-total-hint {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #d7dbea;
        font-size: 0.78rem;
        font-weight: 500;
    }

    .cashier-details-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px 18px;
        margin-top: 20px;
    }

    .cashier-field {
        min-width: 0;
    }

    .cashier-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        min-height: 18px;
        margin-bottom: 6px;
        color: var(--cashier-dark);
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .cashier-shortcut-key {
        display: inline-flex;
        min-width: 25px;
        min-height: 20px;
        padding: 2px 6px;
        align-items: center;
        justify-content: center;
        border: 1px solid #d6dae5;
        border-radius: 5px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(24, 28, 50, .08);
        color: var(--cashier-dark);
        font-family: inherit;
        font-size: .62rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .cashier-section-title .cashier-shortcut-key,
    .cashier-entry-summary .cashier-shortcut-key {
        margin-left: 4px;
        vertical-align: middle;
    }

    .cashier-shortcut-key--button {
        margin-left: 5px;
        border-color: rgba(255, 255, 255, .55);
        background: rgba(255, 255, 255, .16);
        box-shadow: none;
        color: #fff;
    }

    .cashier-field .form-control {
        width: 100%;
        min-height: 42px;
        padding: 0.62rem 0.85rem;
        border: 1px solid #d9deea;
        border-radius: 8px;
        background-color: #fff;
        color: var(--cashier-dark);
        font-size: 0.85rem;
        line-height: 1.35;
    }

    .cashier-field .form-control:focus {
        border-color: #009ef7;
        box-shadow: 0 0 0 3px rgba(0, 158, 247, 0.12);
    }

    .cashier-field .form-control[readonly],
    .cashier-field .form-control:disabled {
        background: #f1f4f8;
        color: #5e6278;
        opacity: 1;
    }

    .cashier-field-hint {
        min-height: 17px;
        margin-top: 5px;
        color: var(--cashier-muted);
        font-size: 0.72rem;
        line-height: 1.35;
    }

    .cashier-page .select2-container {
        width: 100% !important;
        min-width: 0;
    }

    .cashier-page .select2-container--default .select2-selection--single {
        position: relative;
        display: flex;
        align-items: center;
        height: 42px !important;
        overflow: hidden;
        border: 1px solid #d9deea;
        border-radius: 8px;
        background: #fff;
    }

    .cashier-page .select2-container--default.select2-container--focus .select2-selection--single,
    .cashier-page .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #009ef7;
        box-shadow: 0 0 0 3px rgba(0, 158, 247, 0.12);
    }

    .cashier-page .select2-container--default .select2-selection--single .select2-selection__rendered {
        display: block;
        width: 100%;
        padding: 0 36px 0 13px !important;
        color: var(--cashier-dark);
        font-size: 0.85rem !important;
        line-height: 1.35 !important;
    }

    .cashier-page .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #a1a5b7;
    }

    .cashier-page .select2-container--default .select2-selection--single .select2-selection__arrow {
        width: 32px;
        height: 40px;
        right: 3px;
    }

    .cashier-page .select2-container--default .select2-selection--single .select2-selection__clear {
        height: 40px;
        margin-right: 4px;
        color: var(--cashier-muted);
        font-size: 1.15rem;
        line-height: 38px;
    }

    /* Keep the Select2 member value centered on every breakpoint.
       The active theme also styles Select2, so anchor the rendered value
       to the selection instead of relying on its inherited line-height. */
    #cust_id + .select2-container .select2-selection--single {
        position: relative;
        display: block;
    }

    #cust_id + .select2-container .select2-selection__rendered {
        position: absolute !important;
        inset: 0 !important;
        display: flex !important;
        align-items: center;
        width: auto !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 36px 0 13px !important;
        color: var(--cashier-dark) !important;
        line-height: 1.35 !important;
    }

    #cust_id + .select2-container .select2-selection__arrow {
        z-index: 1;
    }

    .cashier-search-control {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px;
        width: 100%;
    }

    .cashier-search-control .select2-container {
        grid-column: 1;
    }

    .cashier-search-control .select2-selection--single {
        border-right: 0 !important;
        border-radius: 8px 0 0 8px !important;
    }

    .cashier-search-control #btn_scan {
        grid-column: 2;
        width: 44px;
        height: 42px;
        padding: 0;
        border-radius: 0 8px 8px 0;
    }

    .cashier-product-browser {
        margin-top: 20px;
        padding: 16px 18px 18px;
        border: 1px solid var(--cashier-border);
        border-radius: 10px;
        background: #fff;
    }

    .cashier-product-browser-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 14px;
    }

    .cashier-product-browser-hint {
        color: var(--cashier-muted);
        font-size: 0.72rem;
    }

    .cashier-gallery-search {
        position: relative;
        width: min(100%, 320px);
    }

    .cashier-gallery-search i {
        position: absolute;
        top: 50%;
        left: 12px;
        z-index: 1;
        color: #a1a5b7;
        transform: translateY(-50%);
    }

    .cashier-gallery-search .form-control {
        height: 40px;
        padding-left: 36px;
        border-radius: 8px;
    }

    .cashier-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
        gap: 12px;
        max-height: 380px;
        padding: 2px;
        overflow-y: auto;
    }

    .cashier-product-card {
        position: relative;
        min-width: 0;
        padding: 0;
        overflow: hidden;
        border: 1px solid #e4e6ef;
        border-radius: 10px;
        background: #fff;
        color: var(--cashier-dark);
        text-align: left;
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .cashier-product-card:hover,
    .cashier-product-card:focus-visible {
        border-color: #009ef7;
        box-shadow: 0 7px 18px rgba(0, 158, 247, .14);
        transform: translateY(-2px);
    }

    .cashier-product-card:disabled {
        cursor: not-allowed;
        filter: grayscale(.45);
        opacity: .65;
    }

    .cashier-product-image-wrap {
        position: relative;
        height: 112px;
        overflow: hidden;
        background: #f1f4f8;
    }

    .cashier-product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cashier-product-stock {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 3px 7px;
        border-radius: 20px;
        background: rgba(24, 28, 50, .82);
        color: #fff;
        font-size: .64rem;
        font-weight: 700;
    }

    .cashier-product-stock.is-empty {
        background: rgba(241, 65, 108, .9);
    }

    .cashier-product-info {
        padding: 10px 11px 11px;
    }

    .cashier-product-category {
        margin-bottom: 3px;
        overflow: hidden;
        color: #009ef7;
        font-size: .64rem;
        font-weight: 700;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cashier-product-name {
        display: -webkit-box;
        min-height: 36px;
        overflow: hidden;
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.35;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .cashier-product-price {
        margin-top: 7px;
        color: var(--cashier-accent-dark);
        font-size: .8rem;
        font-weight: 800;
    }

    .cashier-product-loading,
    .cashier-product-empty {
        grid-column: 1 / -1;
        display: flex;
        min-height: 90px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--cashier-muted);
        font-size: .78rem;
    }

    .cashier-cart-product {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }

    .cashier-cart-thumb {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        object-fit: cover;
        border: 1px solid #e4e6ef;
        border-radius: 7px;
        background: #f1f4f8;
    }

    .cashier-entry {
        margin-top: 20px;
        padding: 15px 18px 18px;
        border: 1px solid var(--cashier-border);
        border-radius: 10px;
        background: var(--cashier-surface);
    }

    .cashier-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 13px;
        color: var(--cashier-dark);
        font-size: 0.9rem;
        font-weight: 700;
    }

    .cashier-section-title i {
        color: #009ef7;
    }

    .cashier-entry-grid {
        display: grid;
        grid-template-columns: minmax(260px, 2fr) minmax(95px, 0.8fr) minmax(115px, 0.8fr) minmax(145px, 1fr) 132px;
        gap: 14px;
        align-items: end;
    }

    .cashier-entry-action .btn {
        display: inline-flex;
        width: 100%;
        height: 42px;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .cashier-table-card {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        margin-top: 20px;
        overflow: hidden;
        border: 1px solid var(--cashier-border);
        border-radius: 10px;
        background: #fff;
    }

    .cashier-table-card .table-responsive {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
    }

    #list.cashier-table {
        width: 100%;
        min-width: 100%;
        margin: 0;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
    }

    #list th,
    #list td {
        vertical-align: middle;
    }

    #list thead th {
        padding: 11px 10px;
        border: 0;
        background: var(--cashier-dark) !important;
        color: #fff !important;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        white-space: nowrap;
    }

    #list tbody td {
        padding: 9px 10px;
        border: 0;
        border-bottom: 1px solid #edf0f5;
        color: var(--cashier-dark);
        font-size: 0.8rem;
    }

    #list tbody tr:not(.cashier-empty-row):hover td {
        background-color: #f8fafc;
    }

    #list tbody tr.low-stock td:first-child {
        box-shadow: inset 4px 0 #ffc700;
    }

    #list tbody tr.out-of-stock td:first-child {
        box-shadow: inset 4px 0 #f1416c;
    }

    .cashier-empty-row td {
        padding: 0 !important;
        background: #fff !important;
    }

    .cashier-empty-state {
        display: flex;
        width: 100%;
        min-height: 76px;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 12px 16px;
        color: var(--cashier-muted);
        font-size: 0.8rem;
        font-weight: 500;
        text-align: center;
    }

    .cashier-empty-state i {
        color: #b5b8c5;
        font-size: 1.15rem;
    }

    #list .qty-input,
    #list .price-input,
    #list .discount-input {
        width: 100% !important;
        min-width: 0;
        height: 34px;
        margin: 0 !important;
        padding: 5px 8px;
        border: 1px solid #d9deea;
        border-radius: 6px;
        background: #fff;
        font-size: 0.78rem;
        font-weight: 600;
    }

    #list .qty-input:focus,
    #list .price-input:focus,
    #list .discount-input:focus {
        border-color: #009ef7;
        box-shadow: 0 0 0 2px rgba(0, 158, 247, 0.1);
    }

    #list .price-input[readonly] {
        pointer-events: none;
        background: #f1f4f8;
        color: #181c32;
        cursor: not-allowed;
        box-shadow: none;
    }

    #list .remove-item {
        display: inline-flex;
        width: 34px;
        height: 34px;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 6px;
    }

    #list tfoot th {
        padding: 9px 12px;
        border: 0;
        border-top: 1px solid #edf0f5;
        background: #fff;
        color: var(--cashier-dark);
        font-size: 0.82rem;
        font-weight: 600;
    }

    #list .cashier-summary-row--discount th {
        padding-top: 7px;
        padding-bottom: 7px;
    }

    .cashier-invoice-discount {
        width: 150px;
        height: 36px;
        margin-left: auto;
        padding: 6px 10px;
        border: 1px solid #d9deea;
        border-radius: 7px;
        background: #fff;
        color: var(--cashier-dark);
        font-size: 0.82rem;
        font-weight: 600;
    }

    #list .cashier-summary-row--grand th {
        padding-top: 11px;
        padding-bottom: 11px;
        border-top-color: #dce1ea;
        background: #f1f4f8;
        color: var(--cashier-dark);
        font-size: 1rem;
        font-weight: 700;
    }

    #list .cashier-summary-row--change th {
        padding-top: 11px;
        padding-bottom: 11px;
        border-top-color: #bcebd1;
        background: #eafaf1;
        color: var(--cashier-accent-dark);
        font-size: 1rem;
        font-weight: 700;
    }

    /* Tombol denom */
    .denom-btn {
        font-weight: bold;
        font-size: 1.1rem;
        height: 50px;
    }

    /* Styling untuk produk yang stoknya rendah */
    .low-stock {
        background-color: #fff3cd !important;
    }

    .out-of-stock {
        background-color: #f8d7da !important;
    }

    .kasir-shortcut-help {
        width: 100%;
        padding: 14px 16px 15px;
        border-top: 1px solid var(--cashier-border);
        background: var(--cashier-surface);
        color: var(--cashier-dark);
        font-size: 0.72rem;
        line-height: 1.4;
    }

    .kasir-shortcut-help .shortcut-title {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        color: var(--cashier-dark);
        font-weight: 700;
    }

    .kasir-shortcut-help .shortcut-title i {
        color: #009ef7;
        font-size: 0.9rem;
    }

    .kasir-shortcut-help .shortcut-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px 18px;
    }

    .kasir-shortcut-help .shortcut-grid > span {
        display: flex;
        min-width: 0;
        align-items: center;
        color: #3f4254;
        white-space: nowrap;
    }

    .kasir-shortcut-help kbd {
        display: inline-block;
        min-width: 22px;
        padding: 2px 5px;
        margin: 0 3px;
        border: 1px solid #ccd3df;
        border-radius: 4px;
        background: #fff;
        box-shadow: 0 1px 1px rgba(24, 28, 50, 0.06);
        color: var(--cashier-dark);
        font-size: 0.68rem;
        font-weight: 700;
        text-align: center;
    }

    .kasir-shortcut-help .shortcut-grid > span > kbd:first-child {
        margin-left: 0;
    }

    .cashier-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .cashier-action-button {
        display: inline-flex;
        min-width: 132px;
        height: 50px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 18px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 700;
    }

    @media (max-width: 1199.98px) {
        .kasir-shortcut-help .shortcut-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .cashier-page-header {
            padding-right: 22px !important;
            padding-left: 22px !important;
        }

        .cashier-content {
            padding-right: 22px;
            padding-left: 22px;
        }

        .cashier-details-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cashier-entry-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cashier-entry-product {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 575.98px) {
        .cashier-page {
            padding-right: 12px;
            padding-left: 12px;
        }

        .cashier-card {
            border-radius: 10px;
        }

        .cashier-page-header {
            min-height: 68px;
            padding: 14px 16px !important;
        }

        .cashier-content {
            padding: 16px;
        }

        .cashier-total-panel {
            min-height: 126px;
            padding: 18px 14px;
        }

        .cashier-total-value {
            font-size: 2.35rem;
        }

        .cashier-details-grid,
        .cashier-entry-grid,
        .kasir-shortcut-help .shortcut-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .cashier-product-browser-head {
            align-items: stretch;
            flex-direction: column;
        }

        .cashier-gallery-search {
            width: 100%;
        }

        .cashier-product-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cashier-entry-product {
            grid-column: auto;
        }

        .cashier-entry {
            padding: 14px;
        }

        .kasir-shortcut-help .shortcut-grid > span {
            white-space: normal;
        }

        .cashier-actions {
            flex-direction: column;
        }

        .cashier-action-button {
            width: 100%;
        }
    }

    /* Compact responsive POS layout */
    .cashier-page {
        padding: 12px 14px 20px;
        background: #f3f6fb;
    }

    .cashier-card {
        overflow: visible;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .cashier-content {
        display: grid;
        grid-template-columns: minmax(330px, .82fr) minmax(590px, 1.35fr);
        grid-template-areas:
            "details details"
            "catalog total"
            "catalog manual"
            "catalog cart"
            "catalog actions"
            "catalog message";
        gap: 14px;
        align-items: start;
        padding: 0;
    }

    .cashier-details-grid {
        grid-area: details;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 10px;
        margin: 0;
        padding: 12px 14px;
        border: 1px solid var(--cashier-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 5px 16px rgba(24, 28, 50, .045);
    }

    .cashier-details-grid .cashier-label {
        margin-bottom: 4px;
        font-size: .72rem;
    }

    .cashier-details-grid .form-control,
    .cashier-details-grid .select2-selection--single {
        min-height: 38px !important;
        height: 38px !important;
    }

    .cashier-details-grid .select2-selection__rendered {
        line-height: 36px !important;
    }

    .cashier-details-grid .select2-selection__arrow {
        height: 36px !important;
    }

    .cashier-total-panel {
        grid-area: total;
        min-height: 104px;
        flex-flow: column nowrap;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 14px 20px;
        border: 0;
        border-radius: 12px;
        background: var(--cashier-dark);
        text-align: center;
        box-shadow: 0 9px 22px rgba(24, 28, 50, .14);
    }

    .cashier-total-label {
        font-size: .75rem;
        letter-spacing: .08em;
    }

    .cashier-total-value {
        width: 100%;
        margin: 1px 0;
        text-align: center;
        font-size: clamp(1.9rem, 3vw, 2.65rem);
        line-height: 1;
    }

    .cashier-total-hint {
        flex-basis: auto;
        justify-content: center;
        margin-top: 2px;
        font-size: .68rem;
    }

    .cashier-product-browser {
        grid-area: catalog;
        height: 100%;
        min-height: 0;
        margin: 0;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(24, 28, 50, .055);
    }

    .cashier-product-browser-head {
        align-items: stretch;
        flex-direction: column;
        margin-bottom: 12px;
    }

    .cashier-gallery-search {
        width: 100%;
    }

    .cashier-count-badge {
        display: inline-flex;
        min-height: 24px;
        align-items: center;
        padding: 3px 9px;
        border-radius: 999px;
        background: #e8f4ff;
        color: #006fba;
        font-size: .65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .cashier-product-grid {
        grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
        gap: 10px;
        min-height: min(410px, calc(100vh - 255px));
        max-height: calc(100vh - 255px);
        padding: 2px 4px 2px 2px;
    }

    .cashier-product-card {
        border-radius: 12px;
        box-shadow: 0 3px 10px rgba(24, 28, 50, .05);
    }

    .cashier-product-image-wrap {
        height: 104px;
    }

    .cashier-entry {
        grid-area: manual;
        margin: 0;
        padding: 0 14px;
        overflow: hidden;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 5px 16px rgba(24, 28, 50, .04);
    }

    .cashier-entry-summary,
    .kasir-shortcut-help .shortcut-title {
        display: flex;
        min-height: 46px;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin: 0;
        color: var(--cashier-dark);
        font-size: .78rem;
        font-weight: 700;
        cursor: pointer;
        list-style: none;
    }

    .cashier-entry-summary::-webkit-details-marker,
    .kasir-shortcut-help .shortcut-title::-webkit-details-marker {
        display: none;
    }

    .cashier-entry-summary span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .cashier-entry-summary i,
    .cashier-cart-head i {
        color: #009ef7;
    }

    .cashier-entry-summary small,
    .kasir-shortcut-help .shortcut-title small {
        color: var(--cashier-muted);
        font-size: .65rem;
        font-weight: 600;
    }

    .cashier-entry[open] {
        padding-bottom: 14px;
    }

    .cashier-entry[open] .cashier-entry-summary {
        margin-bottom: 10px;
        border-bottom: 1px solid #edf0f5;
    }

    .cashier-entry-grid {
        grid-template-columns: minmax(170px, 2fr) 80px 90px 120px 104px;
        gap: 9px;
    }

    .cashier-table-card {
        grid-area: cart;
        container: cashier-cart / inline-size;
        margin: 0;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(24, 28, 50, .055);
    }

    .cashier-cart-head {
        display: flex;
        min-height: 48px;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--cashier-border);
        background: #fff;
    }

    .cashier-table-card .table-responsive {
        max-height: calc(100vh - 390px);
        overflow: auto;
    }

    #list.cashier-table {
        min-width: 100%;
    }

    #list thead th {
        position: sticky;
        top: 0;
        z-index: 3;
    }

    /* Responsif mengikuti lebar card, bukan hanya lebar viewport/browser. */
    @container cashier-cart (max-width: 760px) {
        #list col:nth-child(1),
        #list col:nth-child(3),
        #list thead th:nth-child(1),
        #list thead th:nth-child(3),
        #list tbody tr[data-index] td:nth-child(1),
        #list tbody tr[data-index] td:nth-child(3) {
            display: none;
        }
    }

    .kasir-shortcut-help {
        padding: 0 14px;
    }

    .kasir-shortcut-help[open] {
        padding-bottom: 13px;
    }

    .kasir-shortcut-help .shortcut-title {
        margin-bottom: 0;
    }

    .kasir-shortcut-help[open] .shortcut-title {
        margin-bottom: 10px;
    }

    .cashier-actions {
        grid-area: actions;
        margin: 0;
        padding: 10px;
        border: 1px solid var(--cashier-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 6px 18px rgba(24, 28, 50, .06);
    }

    #form-message {
        grid-area: message;
    }

    @media (min-width: 1200px) and (max-width: 1499.98px) {
        .cashier-content {
            grid-template-columns: minmax(310px, .75fr) minmax(540px, 1.25fr);
        }

        #list col:nth-child(1),
        #list col:nth-child(3),
        #list thead th:nth-child(1),
        #list thead th:nth-child(3),
        #list tbody tr[data-index] td:nth-child(1),
        #list tbody tr[data-index] td:nth-child(3) {
            display: none;
        }

        .cashier-entry-grid {
            grid-template-columns: minmax(150px, 2fr) 76px 86px 112px 96px;
        }
    }

    @media (max-width: 1199.98px) {
        .cashier-content {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas:
                "total"
                "details"
                "catalog"
                "manual"
                "cart"
                "actions"
                "message";
        }

        .cashier-details-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .cashier-product-grid {
            min-height: 0;
            max-height: 350px;
        }

        .cashier-table-card .table-responsive {
            max-height: 430px;
        }
    }

    @media (max-width: 767.98px) {
        .cashier-page {
            padding: 8px 8px 76px;
        }

        .cashier-content {
            gap: 9px;
        }

        .cashier-total-panel {
            min-height: 76px;
            padding: 12px 15px;
        }

        .cashier-total-value {
            font-size: 1.75rem;
        }

        .cashier-total-hint {
            display: none;
        }

        .cashier-details-grid {
            grid-template-columns: none;
            grid-auto-flow: column;
            grid-auto-columns: minmax(210px, 78vw);
            gap: 8px;
            padding: 10px;
            overflow-x: auto;
            scroll-snap-type: x proximity;
        }

        .cashier-product-loading,
        .cashier-product-empty {
            flex: 1 0 100%;
        }

        .cashier-details-grid .cashier-field {
            scroll-snap-align: start;
        }

        .cashier-product-browser {
            padding: 12px;
        }

        .cashier-product-browser-head {
            align-items: stretch;
            gap: 10px;
        }

        .cashier-product-browser-hint {
            display: none;
        }

        .cashier-product-grid {
            display: flex;
            min-height: 0;
            max-height: none;
            padding-bottom: 7px;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x proximity;
        }

        .cashier-product-card {
            flex: 0 0 158px;
            scroll-snap-align: start;
        }

        .cashier-product-image-wrap {
            height: 92px;
        }

        .cashier-entry {
            padding-right: 11px;
            padding-left: 11px;
        }

        .cashier-entry-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cashier-entry-product,
        .cashier-entry-action {
            grid-column: 1 / -1;
        }

        .cashier-table-card .table-responsive {
            max-height: none;
            overflow: visible;
        }

        #list.cashier-table,
        #list tbody,
        #list tfoot {
            display: block;
            width: 100%;
            min-width: 0;
        }

        #list colgroup,
        #list thead {
            display: none;
        }

        #list tbody {
            max-height: 46vh;
            padding: 9px;
            overflow-y: auto;
            background: #f7f9fc;
        }

        #list tbody tr[data-index] {
            position: relative;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-areas:
                "product product"
                "stock qty"
                "price discount"
                "subtotal subtotal";
            gap: 8px 10px;
            margin-bottom: 9px;
            padding: 11px;
            border: 1px solid #e4e6ef;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(24, 28, 50, .04);
        }

        #list tbody tr[data-index] td {
            display: block;
            padding: 0;
            border: 0;
            background: transparent !important;
            text-align: left !important;
        }

        #list tbody tr[data-index] td:nth-child(1),
        #list tbody tr[data-index] td:nth-child(3) {
            display: none;
        }

        #list tbody tr[data-index] td:nth-child(2) {
            grid-area: product;
            padding-right: 38px;
        }

        #list tbody tr[data-index] td:nth-child(4) { grid-area: stock; }
        #list tbody tr[data-index] td:nth-child(5) { grid-area: qty; }
        #list tbody tr[data-index] td:nth-child(6) { grid-area: price; }
        #list tbody tr[data-index] td:nth-child(7) { grid-area: discount; }
        #list tbody tr[data-index] td:nth-child(8) { grid-area: subtotal; }

        #list tbody tr[data-index] td:nth-child(4)::before,
        #list tbody tr[data-index] td:nth-child(5)::before,
        #list tbody tr[data-index] td:nth-child(6)::before,
        #list tbody tr[data-index] td:nth-child(7)::before,
        #list tbody tr[data-index] td:nth-child(8)::before {
            display: block;
            margin-bottom: 3px;
            color: var(--cashier-muted);
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        #list tbody tr[data-index] td:nth-child(4)::before { content: "Stok"; }
        #list tbody tr[data-index] td:nth-child(5)::before { content: "Qty"; }
        #list tbody tr[data-index] td:nth-child(6)::before { content: "Harga"; }
        #list tbody tr[data-index] td:nth-child(7)::before { content: "Diskon"; }
        #list tbody tr[data-index] td:nth-child(8)::before { content: "Subtotal"; }

        #list tbody tr[data-index] td:nth-child(9) {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        #list tbody tr[data-index] td:nth-child(8) {
            padding-top: 8px;
            border-top: 1px dashed #d9deea;
            color: #006fba;
            font-size: .9rem;
        }

        #list .cashier-cart-thumb {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
        }

        #list .qty-input,
        #list .price-input,
        #list .discount-input {
            height: 36px;
        }

        #list .cashier-empty-row {
            display: block;
        }

        #list .cashier-empty-row td {
            display: block;
        }

        #list tfoot {
            padding: 4px 10px 10px;
            background: #fff;
        }

        #list tfoot tr {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
        }

        #list tfoot th {
            display: block;
            padding: 8px 5px;
            text-align: left !important;
        }

        #list tfoot th:last-child {
            min-width: 125px;
            text-align: right !important;
        }

        .cashier-invoice-discount {
            width: 125px;
        }

        .kasir-shortcut-help {
            display: none;
        }

        .cashier-actions {
            position: fixed;
            right: 8px;
            bottom: 8px;
            left: 8px;
            z-index: 20;
            flex-direction: row;
            padding: 8px;
            border-color: #d9deea;
            box-shadow: 0 9px 28px rgba(24, 28, 50, .2);
        }

        .cashier-action-button {
            width: auto;
            min-width: 0;
            height: 46px;
            flex: 1 1 auto;
        }

        #btn_reset {
            max-width: 52px;
            font-size: 0;
        }

        #btn_reset i {
            font-size: 1rem;
        }
    }

    @media (max-width: 374.98px) {
        .cashier-product-card {
            flex-basis: 145px;
        }

        .cashier-cart-head,
        .cashier-product-browser-head {
            gap: 7px;
        }

        .cashier-count-badge {
            padding-right: 7px;
            padding-left: 7px;
        }
    }
</style>

<script>
    $(document).ready(function() {
        const baseUrlKasir = "../classes/PenjualanKasir.php";
        const baseUrl = "../classes/Penjualan.php";
        const currentUserId = <?= $_settings->userdata('id') ?>;
        const currentUsername = "<?= $_settings->userdata('username') ?>";
        const requestedEditSaleId = <?= (int)$editKasirSaleId ?>;
        const riwayatTransaksiUrl = "<?= base_url ?>admin/?page=riwayat-transaksi";

        // Helper aman untuk HTML
        const escapeHtml = text => $('<div>').text(text || '').html();
        const formatRupiah = value => SarinahNumbers.formatMoney(value);
        const parseRupiah = value => SarinahNumbers.parseMoney(value);
        const paymentDigits = value => String(value ?? '').replace(/\D/g, '');
        const parsePaymentAmount = value => {
            const digits = paymentDigits(value);
            return digits === '' ? 0 : Number.parseInt(digits, 10) || 0;
        };
        const paymentCaretFromDigitCount = (formattedValue, digitCount) => {
            if (digitCount <= 0) return 0;

            let seenDigits = 0;
            for (let index = 0; index < formattedValue.length; index++) {
                if (/\d/.test(formattedValue[index])) {
                    seenDigits++;
                    if (seenDigits === digitCount) return index + 1;
                }
            }

            return formattedValue.length;
        };
        const normalizeMoneyInput = input => {
            const rawValue = String(input.value ?? '');
            const caretPosition = input.selectionStart ?? rawValue.length;
            const digitsBeforeCaret = paymentDigits(rawValue.slice(0, caretPosition)).length;
            const value = parsePaymentAmount(rawValue);
            const formattedValue = formatRupiah(value);

            input.value = formattedValue;
            if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
                const nextCaretPosition = formattedValue === '0'
                    ? formattedValue.length
                    : paymentCaretFromDigitCount(formattedValue, digitsBeforeCaret);
                input.setSelectionRange(nextCaretPosition, nextCaretPosition);
            }

            return value;
        };
        const normalizeQuantityInputText = input => {
            const rawValue = String(input.value ?? '');
            const allowNegative = input.dataset.allowNegative === 'true';
            const maxDecimals = Math.max(0, Number.parseInt(input.dataset.maxDecimals || '4', 10) || 0);
            const isNegative = allowNegative && /^\s*-/.test(rawValue);
            const unsignedValue = rawValue.replace(/[^0-9.,]/g, '');
            const separatorIndex = unsignedValue.search(/[.,]/);
            let integerPart = separatorIndex >= 0 ? unsignedValue.slice(0, separatorIndex) : unsignedValue;
            const fractionPart = separatorIndex >= 0
                ? unsignedValue.slice(separatorIndex + 1).replace(/[^0-9]/g, '').slice(0, maxDecimals)
                : '';

            integerPart = integerPart.replace(/^0+(?=\d)/, '');
            if (separatorIndex >= 0 && integerPart === '') integerPart = '0';

            let normalizedValue = integerPart;
            if (separatorIndex >= 0 && maxDecimals > 0) {
                normalizedValue += '.' + fractionPart;
            }
            if (isNegative && normalizedValue !== '') {
                normalizedValue = '-' + normalizedValue;
            }

            input.value = normalizedValue;
            if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(normalizedValue.length, normalizedValue.length);
            }

            return normalizedValue;
        };
        const normalizeKasirQuantity = value => {
            const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
            return Object.is(rounded, -0) ? 0 : rounded;
        };
        const kasirQuantityFromInput = value => normalizeKasirQuantity(value);
        const kasirQuantityInput = value => normalizeKasirQuantity(value)
            .toFixed(4)
            .replace(/\.?0+$/, '') || '0';
        const formatKasirQuantity = value => SarinahNumbers.formatQuantity(
            normalizeKasirQuantity(value),
            { minimumFractionDigits: 0, maximumFractionDigits: 4 }
        );
        const PPN_RATE = <?= json_encode($kasirPpnRateDecimal) ?>;
        const ppnFromGrossAmount = (grossAmount, isPpn) => {
            const gross = parseFloat(grossAmount || 0);
            return Number(isPpn || 0) === 1 ? gross - (gross / (1 + PPN_RATE)) : 0;
        };
        const isSweetAlertOpen = () => $('.swal2-container.swal2-shown, .swal2-container:visible').length > 0;
        const isSelect2Open = () => $('.select2-container--open').length > 0;
        const isPaymentModalOpen = () => $('#kasirPaymentModal').hasClass('show');

        function focusInput(selector, selectText = true) {
            const $field = $(selector);
            if (!$field.length) return;

            setTimeout(() => {
                const field = $field.get(0);
                if (!field) return;
                field.focus();
                if ($field.hasClass('cashier-money-input') && typeof field.setSelectionRange === 'function') {
                    const endPosition = String(field.value ?? '').length;
                    field.setSelectionRange(endPosition, endPosition);
                } else if (selectText && typeof field.select === 'function' && !$field.prop('disabled')) {
                    field.select();
                }
            }, 30);
        }

        $(document)
            .off('focus.kasirMoneyInput', '.cashier-money-input')
            .on('focus.kasirMoneyInput', '.cashier-money-input', function() {
                const input = this;
                setTimeout(() => {
                    if (document.activeElement !== input || typeof input.setSelectionRange !== 'function') return;
                    const endPosition = String(input.value ?? '').length;
                    input.setSelectionRange(endPosition, endPosition);
                }, 0);
            })
            .off('input.kasirQuantityInput', '.cashier-quantity-input')
            .on('input.kasirQuantityInput', '.cashier-quantity-input', function() {
                normalizeQuantityInputText(this);
            });

        function focusSelect2(selector, openDropdown = true) {
            const $select = $(selector);
            if (!$select.length) return;

            function focusOpenSearchInput() {
                const searchInput = document.querySelector('.select2-container--open .select2-search__field');
                if (!searchInput) return;

                searchInput.focus();
                if (typeof searchInput.select === 'function') {
                    searchInput.select();
                }
            }

            setTimeout(() => {
                if ($select.data('select2')) {
                    if (openDropdown) {
                        $select.off('select2:open.kasirShortcutFocus').one('select2:open.kasirShortcutFocus', function() {
                            activeSelect2Selector = selector;
                            setTimeout(focusOpenSearchInput, 0);
                            setTimeout(focusOpenSearchInput, 25);
                            setTimeout(focusOpenSearchInput, 75);
                        });
                        $select.off('select2:close.kasirShortcutFocus').one('select2:close.kasirShortcutFocus', function() {
                            if (activeSelect2Selector === selector) {
                                activeSelect2Selector = null;
                            }
                        });
                        $select.select2('open');
                        setTimeout(focusOpenSearchInput, 100);
                    } else {
                        const selection = $select.next('.select2-container').find('.select2-selection').get(0);
                        if (selection) selection.focus();
                    }
                    return;
                }

                $select.trigger('focus');
            }, 30);
        }

        function focusQuickSearch(collapseManualEntry = false) {
            closeOpenSelect2();
            if (collapseManualEntry) {
                const manualEntry = document.getElementById('manual_product_entry');
                if (manualEntry) manualEntry.open = false;
            }
            focusInput('#product_gallery_search');
        }

        function openSaleDatePicker() {
            closeOpenSelect2();
            const saleDateInput = document.getElementById('sale_date');
            if (!saleDateInput) return;

            saleDateInput.focus({ preventScroll: true });
            if (typeof saleDateInput.showPicker === 'function') {
                try {
                    saleDateInput.showPicker();
                    return;
                } catch (error) {
                    // Browser lama dapat menolak showPicker; klik menjadi fallback.
                }
            }

            saleDateInput.click();
        }

        function focusManualProductEntry() {
            const manualEntry = document.getElementById('manual_product_entry');
            if (manualEntry) manualEntry.open = true;
            focusInput('#cr_produk');
        }

        function closeOpenSelect2() {
            if (activeSelect2Selector && $(activeSelect2Selector).data('select2')) {
                $(activeSelect2Selector).select2('close');
            }
            activeSelect2Selector = null;
        }

        function getActiveCartIndex() {
            const $activeRow = $(document.activeElement).closest('tr[data-index]');
            if ($activeRow.length) {
                return parseInt($activeRow.data('index'), 10);
            }

            const $lastRow = $('#product_rows tr[data-index]').last();
            if ($lastRow.length) {
                return parseInt($lastRow.data('index'), 10);
            }

            return -1;
        }

        function focusCartQty(index = getActiveCartIndex()) {
            if (index < 0 || !cartItems[index]) {
                showMessage('Belum ada item di keranjang', 'warning');
                return;
            }

            closeOpenSelect2();
            focusInput(`.qty-input[data-index="${index}"]`);
        }

        function focusCartDiscount(index = getActiveCartIndex()) {
            if (index < 0 || !cartItems[index]) {
                showMessage('Belum ada item di keranjang', 'warning');
                return;
            }

            closeOpenSelect2();
            focusInput(`.discount-input[data-index="${index}"]`);
        }

        function removeCartItemByShortcut(index = getActiveCartIndex()) {
            if (index < 0 || !cartItems[index]) {
                showMessage('Belum ada item di keranjang', 'warning');
                return;
            }

            const itemName = cartItems[index].name || 'Item';
            closeOpenSelect2();
            cartItems.splice(index, 1);
            updateCartDisplay();
            showMessage(`${itemName} dihapus dari keranjang`, 'info');
            focusQuickSearch(true);
        }

        function setModalAmount(amount) {
            $('#modal_amount_paid').val(formatRupiah(amount || 0)).trigger('input');
            focusInput('#modal_amount_paid');
        }

        function activateQtyModalKeyboard() {
            const qtyInput = document.getElementById('qty_input');
            if (!qtyInput) return;

            setTimeout(() => {
                qtyInput.focus();
                if (typeof qtyInput.select === 'function') {
                    qtyInput.select();
                }
            }, 30);

            qtyInput.addEventListener('keydown', function(event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                event.stopPropagation();
                Swal.clickConfirm();
            });
        }

        function initializeShortcutHelp() {
            const $help = $('#kasir_shortcut_help');
            if (!$help.length) return;

            $help.removeClass('is-hidden');
        }

        let cartItems = [];
        let galleryProducts = [];
        let gallerySearchTimer = null;
        let galleryRequest = null;
        let currentRowIndex = 0;
        let currentEditSaleId = null;
        let currentEditPaymentMethod = 'cash';
        let currentEditAmountPaid = 0;
        let activeSelect2Selector = null;
        let minusPassword = '';
        let minusPasswordRequest = null;

        function hasReturnItems() {
            return cartItems.some(item => Number(item.qty || 0) < 0);
        }

        function requiresMinusPassword() {
            return hasReturnItems() || parseRupiah($('#sale_total').val() || '0') < 0;
        }

        async function requestMinusPassword() {
            if (minusPassword) return true;
            if (minusPasswordRequest) return minusPasswordRequest;

            minusPasswordRequest = (async function() {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: 'Otorisasi Quantity Minus',
                    text: 'Masukkan password Admin atau Supervisor yang aktif.',
                    input: 'password',
                    inputPlaceholder: 'Password Admin/Supervisor',
                    inputAttributes: {
                        autocomplete: 'current-password',
                        autocapitalize: 'off',
                        spellcheck: 'false'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Verifikasi',
                    cancelButtonText: 'Batal',
                    showLoaderOnConfirm: true,
                    allowOutsideClick: () => !Swal.isLoading(),
                    preConfirm: async(password) => {
                        if (!password) {
                            Swal.showValidationMessage('Password wajib diisi');
                            return false;
                        }

                        try {
                            const response = await $.ajax({
                                url: baseUrlKasir + '?f=verify_minus_password',
                                method: 'POST',
                                dataType: 'json',
                                data: { password: password }
                            });

                            if (!response || response.status !== 'success' || response.authorized !== true) {
                                Swal.showValidationMessage((response && response.msg) || 'Password tidak memiliki izin');
                                return false;
                            }

                            return password;
                        } catch (xhr) {
                            let message = 'Gagal memverifikasi password ke server';
                            if (xhr.responseJSON && xhr.responseJSON.msg) {
                                message = xhr.responseJSON.msg;
                            }
                            Swal.showValidationMessage(message);
                            return false;
                        }
                    }
                });

                if (!result.isConfirmed || !result.value) {
                    return false;
                }

                minusPassword = result.value;
                return true;
            })();

            try {
                return await minusPasswordRequest;
            } finally {
                minusPasswordRequest = null;
            }
        }

        /** ======================= [ INITIALIZATION ] ======================= **/
        function initializeForm() {
            generateInvoice();
            initializeCustomerDropdown();
            loadTodaySummary();
            loadProductGallery();
            updateCartDisplay();

            if (requestedEditSaleId > 0) {
                currentEditSaleId = requestedEditSaleId;
                setEditMode(true);
                showMessage('Memuat data transaksi kasir untuk diedit...', 'info');
                setTimeout(() => loadKasirTransactionForEdit(requestedEditSaleId), 350);
            }

            initializeKeyboardShortcutHints();
            initializeShortcutHelp();

            // Alur kasir dimulai dari tanggal, lalu member, catatan, produk, diskon, bayar.
            setTimeout(() => focusInput('#sale_date', false), 100);
        }

        /** ======================= [ GENERATE INVOICE ] ======================= **/
        function generateInvoice() {
            const now = new Date();
            const timestamp = now.getTime();
            const random = Math.floor(Math.random() * 1000);
            const invoice = `KSR-${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}-${timestamp.toString().slice(-6)}-${random.toString().padStart(3,'0')}`;
            $('#sale_invoice').val(invoice);
        }

        function getCurrentDateTimeLocal() {
            const now = new Date();
            const offsetMs = now.getTimezoneOffset() * 60000;
            return new Date(now.getTime() - offsetMs).toISOString().slice(0, 16);
        }

        function formatDateTimeLocal(value) {
            if (!value) return getCurrentDateTimeLocal();
            const normalized = String(value).replace(' ', 'T');
            return normalized.length >= 16 ? normalized.slice(0, 16) : getCurrentDateTimeLocal();
        }

        function setEditMode(enabled) {
            $('#cashier-total-label').text(enabled ? 'EDIT TRANSAKSI' : 'TOTAL BELANJA');
            $('#btn_checkout_payment').html(enabled
                ? '<i class="bi bi-save"></i> UPDATE <kbd class="cashier-shortcut-key cashier-shortcut-key--button">F8</kbd>'
                : '<i class="bi bi-credit-card"></i> BAYAR <kbd class="cashier-shortcut-key cashier-shortcut-key--button">F8</kbd>'
            );
            $('#btn_cancel_edit').toggleClass('d-none', !enabled);
        }

        function backToRiwayat(delay = 0) {
            setTimeout(() => {
                window.location.href = riwayatTransaksiUrl;
            }, delay);
        }

        /** ======================= [ INITIALIZE CUSTOMER DROPDOWN WITH SELECT2 ] ======================= **/
        function initializeCustomerDropdown() {
            // Initialize Select2 dengan AJAX search (minimal 2 karakter)
            $('#cust_id').select2({
                placeholder: '-- Pilih Member --',
                allowClear: true,
                minimumInputLength: 2, // Hanya tampil hasil setelah 2 karakter
                ajax: {
                    url: '<?php echo base_url ?>admin/penjualan/ajax_kasir.php?action=search_customers',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        console.log('Member search results:', data);
                        let results = [];

                        // Tambahkan Member Umum jika tidak ada hasil atau saat pertama kali
                        if (data.status === 'success' && data.data && data.data.length > 0) {
                            data.data.forEach(function(customer) {
                                results.push({
                                    id: customer.id,
                                    text: customer.nama + ' (' + (customer.telpon || '-') + ')'
                                });
                            });
                        }

                        return {
                            results: results
                        };
                    },
                    cache: true
                },
                language: {
                    noResults: function() {
                        return 'Member tidak ditemukan';
                    },
                    searching: function() {
                        return 'Mencari member...';
                    },
                    inputTooShort: function() {
                        return 'Ketik minimal 2 huruf untuk mencari member';
                    },
                    loadingMore: function() {
                        return 'Memuat lebih banyak...';
                    }
                }
            });

            // Set default value ke Member Umum
            $('#cust_id').val('0').trigger('change');
            $('#cust_id')
                .off('select2:select.kasirShortcut select2:clear.kasirShortcut')
                .on('select2:select.kasirShortcut select2:clear.kasirShortcut', function() {
                    focusInput('#catatan');
                });
            console.log('Customer dropdown initialized with Select2 AJAX');
        }

        /** ======================= [ LOAD TODAY SUMMARY ] ======================= **/
        function loadTodaySummary() {
            $.ajax({
                url: baseUrlKasir + "?f=get_today_summary",
                method: "GET",
                dataType: "json",
                success: function(resp) {
                    if (resp && resp.status === "success" && resp.data) {
                        // Bisa ditampilkan di header jika diperlukan
                        console.log('Today summary:', resp.data);
                    }
                },
                error: function(err) {
                    console.warn('Error loading today summary:', err);
                }
            });
        }

        function renderProductGallery(products) {
            galleryProducts = Array.isArray(products) ? products.filter(item => item && item.id) : [];
            $('#catalog_product_count').text(`${galleryProducts.length} Produk`);
            if (!galleryProducts.length) {
                $('#product_gallery').html('<div class="cashier-product-empty"><i class="bi bi-search"></i> Produk tidak ditemukan</div>');
                return;
            }

            const fallbackImage = '<?= base_url ?>uploads/no-image-available.png';
            const cards = galleryProducts.map((product, index) => {
                const stock = normalizeKasirQuantity(product.stok);
                const price = Number(product.harga_kasir || product.harga_sales || 0);
                return `
                    <button type="button" class="cashier-product-card" data-product-index="${index}" ${stock <= 0 ? 'disabled' : ''}>
                        <div class="cashier-product-image-wrap">
                            <img class="cashier-product-image" src="${escapeHtml(product.gambar_url || fallbackImage)}"
                                 alt="${escapeHtml(product.nama_produk || 'Produk')}" loading="lazy"
                                 onerror="this.src='${fallbackImage}'">
                            <span class="cashier-product-stock ${stock <= 0 ? 'is-empty' : ''}">
                                ${stock <= 0 ? 'Habis' : 'Stok ' + formatKasirQuantity(stock)}
                            </span>
                        </div>
                        <div class="cashier-product-info">
                            <div class="cashier-product-category">${escapeHtml(product.kategori || 'Tanpa Kategori')}</div>
                            <div class="cashier-product-name">${escapeHtml(product.nama_produk || '-')}</div>
                            <div class="cashier-product-price">Rp ${formatRupiah(price)}</div>
                        </div>
                    </button>`;
            }).join('');
            $('#product_gallery').html(cards);
        }

        function loadProductGallery(term = '') {
            if (galleryRequest && galleryRequest.readyState !== 4) {
                galleryRequest.abort();
            }

            $('#product_gallery').html('<div class="cashier-product-loading"><span class="spinner-border spinner-border-sm"></span> Memuat produk...</div>');
            galleryRequest = $.ajax({
                url: baseUrl + '?f=search_produk',
                method: 'GET',
                data: { term: term },
                dataType: 'json',
                success: renderProductGallery,
                error: function(xhr, status) {
                    if (status !== 'abort') {
                        $('#product_gallery').html('<div class="cashier-product-empty text-danger"><i class="bi bi-exclamation-circle"></i> Gagal memuat produk</div>');
                    }
                }
            });
        }

        $('#product_gallery_search').on('input', function() {
            const term = this.value.trim();
            clearTimeout(gallerySearchTimer);
            gallerySearchTimer = setTimeout(() => loadProductGallery(term), 280);
        });

        $('#product_gallery').on('click', '.cashier-product-card', function() {
            const product = galleryProducts[Number($(this).data('product-index'))];
            if (!product) return;

            const stock = normalizeKasirQuantity(product.stok);
            const existing = cartItems.find(item => Number(item.id) === Number(product.id));
            const nextQty = normalizeKasirQuantity((existing ? existing.qty : 0) + 1);
            if (stock <= 0 || nextQty > stock) {
                showMessage(`Stok ${product.nama_produk || 'produk'} tidak mencukupi`, 'warning');
                return;
            }

            addToCart({
                id: product.id,
                nama_produk: product.nama_produk,
                barcode: product.barcode,
                harga_jual: product.harga_kasir || product.harga_sales || 0,
                harga_modal: product.harga_newmodal || 0,
                stok: stock,
                satuan: product.satuan,
                is_ppn: product.is_ppn,
                gambar_url: product.gambar_url
            }, 1);
            showMessage(`${product.nama_produk} ditambahkan`, 'success');
        });

        /** ======================= [ SEARCH PRODUCT ] ======================= **/
        function searchProduct(term, options = {}) {
            const searchOptions = $.extend({
                promptQty: true,
                qty: 1,
                focusAfter: 'quick'
            }, options);

            start_loader();
            $.ajax({
                url: baseUrlKasir + "?f=search_produk_kasir",
                method: "GET",
                data: {
                    term: term
                },
                dataType: "json",
                success: function(resp) {
                    if (resp && resp.status === "success" && resp.data) {
                        const product = resp.data;
                        const addDirectly = searchOptions.promptQty === false;

                        if (addDirectly) {
                            const qty = kasirQuantityFromInput(searchOptions.qty);
                            const stock = normalizeKasirQuantity(product.stok);

                            if (qty <= 0) {
                                showMessage('Quantity harus lebih dari 0', 'warning');
                                focusInput('#sale_qty');
                                return;
                            }

                            if (qty > stock) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Stok Tidak Cukup',
                                    text: `${product.nama_produk || 'Produk'} hanya tersedia ${formatKasirQuantity(stock)} ${product.satuan || ''}`
                                }).then(() => focusInput('#sale_qty'));
                                return;
                            }

                            const productForCart = $.extend({}, product);

                            addToCart(productForCart, qty);
                            clearManualProductEntry();

                            if (searchOptions.focusAfter === 'manual') {
                                focusInput('#cr_produk');
                            } else {
                                focusQuickSearch(true);
                            }
                            return;
                        }

                        // Tampilkan dialog quantity
                        Swal.fire({
                            title: product.nama_produk || 'Produk',
                            html: `
                            <div class="text-start">
                                <img src="${escapeHtml(product.gambar_url || '<?= base_url ?>uploads/no-image-available.png')}" alt="" class="d-block mx-auto mb-3" style="width:130px;height:130px;object-fit:cover;border-radius:12px">
                                <p>Barcode: <b>${escapeHtml(product.barcode || '-')}</b></p>
                                <p>Harga: <b>${formatRupiah(product.harga_jual || 0)}</b></p>
                                <p>Stok: <b>${formatKasirQuantity(product.stok)}</b> ${escapeHtml(product.satuan || '')}</p>
                            </div>
                            <input type="text" id="qty_input" class="form-control mt-3 cashier-quantity-input"
                                   value="1" inputmode="decimal" autocomplete="off"
                                   data-max-decimals="4" aria-label="Quantity produk" autofocus>
                        `,
                            showCancelButton: true,
                            confirmButtonText: 'Tambah',
                            cancelButtonText: 'Batal',
                            didOpen: activateQtyModalKeyboard,
                            preConfirm: () => {
                                const qty = kasirQuantityFromInput($('#qty_input').val());
                                if (!qty || qty <= 0) {
                                    Swal.showValidationMessage('Masukkan jumlah yang valid');
                                    return false;
                                }
                                if (qty > normalizeKasirQuantity(product.stok)) {
                                    Swal.showValidationMessage('Jumlah melebihi stok');
                                    return false;
                                }
                                return qty;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                addToCart(product, result.value);
                            }
                            focusQuickSearch(true);
                        });

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Produk Tidak Ditemukan',
                            text: (resp && resp.msg) || 'Produk tidak ditemukan',
                            timer: 2000
                        });
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Gagal mencari produk', 'error');
                },
                complete: function() {
                    end_loader();
                    if (searchOptions.focusAfter === 'quick') {
                        focusQuickSearch();
                    }
                }
            });
        }

        function clearManualProductEntry() {
            $('#cr_produk').val('');
            $('#sale_qty').val('1');
            $('#satuan').val('');
            $('#sale_harga').val('0');
        }

        function addManualProduct() {
            const productName = $('#cr_produk').val().trim();
            const qty = kasirQuantityFromInput($('#sale_qty').val());

            if (!productName) {
                showMessage('Masukkan nama produk terlebih dahulu', 'warning');
                focusInput('#cr_produk');
                return;
            }

            searchProduct(productName, {
                promptQty: false,
                qty: qty,
                focusAfter: 'manual'
            });
        }

        /** ======================= [ CART MANAGEMENT ] ======================= **/
        function addToCart(product, qty = 1) {
            const normalizedQty = normalizeKasirQuantity(qty);
            const existingItemIndex = cartItems.findIndex(item => Number(item.id) === Number(product.id));

            if (existingItemIndex > -1) {
                cartItems[existingItemIndex].qty = normalizeKasirQuantity(
                    cartItems[existingItemIndex].qty + normalizedQty
                );
            } else {
                cartItems.push({
                    id: product.id,
                    name: product.nama_produk,
                    barcode: product.barcode,
                    price: product.harga_jual,
                    modal: product.harga_modal,
                    stok: normalizeKasirQuantity(product.stok),
                    is_ppn: product.is_ppn || 0,
                    satuan: product.satuan,
                    gambar_url: product.gambar_url || '<?= base_url ?>uploads/no-image-available.png',
                    qty: normalizedQty,
                    discount: 0,
                    index: cartItems.length
                });
            }

            updateCartDisplay();
        }

        function loadKasirTransactionForEdit(saleid) {
            start_loader();
            $.ajax({
                url: baseUrlKasir + "?f=get_transaction_detail",
                method: "GET",
                data: {
                    id: saleid
                },
                dataType: "json",
                success: function(resp) {
                    if (!resp || resp.status !== 'success' || !resp.data) {
                        showMessage((resp && resp.msg) || 'Gagal memuat transaksi kasir', 'danger');
                        return;
                    }

                    resetForm();
                    currentEditSaleId = saleid;
                    setEditMode(true);

                    const header = resp.data.header || {};
                    const items = resp.data.items || [];

                    $('#saleid').val(saleid);
                    $('#sale_date').val(formatDateTimeLocal(header.sale_date));
                    $('#sale_invoice').val(header.sale_invoice || '');
                    $('#catatan').val(header.catatan || '');
                    $('#sale_disc').val(formatRupiah(header.sale_disc || 0));
                    currentEditPaymentMethod = header.payment_method || 'cash';
                    currentEditAmountPaid = Number(header.amount_paid || 0);

                    if (header.cust_id && Number(header.cust_id) > 0) {
                        const memberText = header.pelanggan_nama || ('Member #' + header.cust_id);
                        $('#cust_id').empty().append(new Option(memberText, header.cust_id, true, true)).trigger('change');
                    } else {
                        $('#cust_id').val('0').trigger('change');
                    }

                    cartItems = items.map((item, index) => {
                        const qty = normalizeKasirQuantity(item.sale_qty);
                        const isi = Math.max(1, SarinahNumbers.fromApi(item.isi));
                        const stokSaatIni = normalizeKasirQuantity(item.stok);

                        return {
                            id: Number(item.produk_id),
                            name: item.nama_produk || '-',
                            barcode: item.barcode || '',
                            price: Number(item.sale_harga || 0),
                            modal: Number(item.sale_capital || item.harga_newmodal || 0),
                            stok: normalizeKasirQuantity(stokSaatIni + (qty * isi)),
                            is_ppn: Number(item.is_ppn || 0),
                            satuan: item.nama_satuan || item.satuan || '',
                            gambar_url: item.gambar_url || '<?= base_url ?>uploads/no-image-available.png',
                            satuan_id: Number(item.id_satuan || item.satuan_id || 0),
                            isi,
                            qty,
                            discount: Number(item.sale_pdisc || 0),
                            index
                        };
                    });

                    updateCartDisplay();
                    showMessage('Mode edit transaksi kasir aktif: ' + (header.sale_invoice || ''), 'info');
                },
                error: function(xhr) {
                    let message = 'Gagal memuat data transaksi kasir';
                    if (xhr.responseText) {
                        try {
                            const errorResp = JSON.parse(xhr.responseText);
                            message = errorResp.msg || message;
                        } catch (e) {}
                    }
                    showMessage(message, 'danger');
                },
                complete: function() {
                    end_loader();
                }
            });
        }

        function normalizeDiscount(amount, discount) {
            const numericAmount = Number(amount || 0);
            const numericDiscount = Math.max(0, Number(discount || 0));
            return Math.min(numericDiscount, Math.abs(numericAmount));
        }

        function applySignedDiscount(amount, discount) {
            const numericAmount = Number(amount || 0);
            const appliedDiscount = normalizeDiscount(numericAmount, discount);
            return numericAmount < 0
                ? numericAmount + appliedDiscount
                : numericAmount - appliedDiscount;
        }

        function applyInvoiceDiscountToLine(lineAmount, invoiceBase, invoiceDiscount) {
            const numericLine = Number(lineAmount || 0);
            const numericBase = Number(invoiceBase || 0);
            if (numericBase === 0) return numericLine;

            const discountedInvoice = applySignedDiscount(numericBase, invoiceDiscount);
            const invoiceAdjustment = discountedInvoice - numericBase;
            return numericLine + (invoiceAdjustment * (numericLine / numericBase));
        }

        function updateCartDisplay() {
            $('#product_rows').empty();
            $('#cart_item_count').text(`${cartItems.length} Item`);
            let subtotal = 0;
            cartItems.forEach((item, index) => {
                item.index = index;
                item.qty = normalizeKasirQuantity(item.qty);
                item.stok = normalizeKasirQuantity(item.stok);
                const itemSubtotal = item.price * item.qty;
                const itemDiscount = normalizeDiscount(itemSubtotal, item.discount);
                item.discount = itemDiscount;
                const itemTotal = applySignedDiscount(itemSubtotal, itemDiscount);

                subtotal += itemTotal;

                const row = `
                <tr data-index="${index}" class="${item.stok < item.qty ? 'out-of-stock' : (item.stok < 10 ? 'low-stock' : '')}">
                    <td class="text-center">${index + 1}</td>
                    <td>
                        <div class="cashier-cart-product">
                            <img class="cashier-cart-thumb" src="${escapeHtml(item.gambar_url || '<?= base_url ?>uploads/no-image-available.png')}" alt="" loading="lazy"
                                 onerror="this.src='<?= base_url ?>uploads/no-image-available.png'">
                            <div class="min-w-0">
                                <div class="fw-bold">${escapeHtml(item.name)}</div>
                                <small class="text-muted">${escapeHtml(item.satuan || '')}</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-start">${escapeHtml(item.barcode || '-')}</td>
                    <td class="text-center ${item.stok < item.qty ? 'text-danger fw-bold' : ''}">
                        ${formatKasirQuantity(item.stok)}
                    </td>
                    <td class="text-center">
                        <input type="text" class="form-control form-control-sm text-center qty-input cashier-quantity-input"
                               value="${kasirQuantityInput(item.qty)}" inputmode="decimal" autocomplete="off"
                               data-allow-negative="true" data-max-decimals="4" data-index="${index}"
                               aria-label="Quantity ${escapeHtml(item.name)}">
                    </td>
                    <td class="text-end">
                        <input type="text" class="form-control form-control-sm text-end price-input" 
                               value="${item.qty < 0 ? '-' : ''}${formatRupiah(item.price)}" data-index="${index}"
                               readonly tabindex="-1" aria-readonly="true" title="Harga mengikuti master barang">
                    </td>
                    <td class="text-end">
                        <input type="text" class="form-control form-control-sm text-end discount-input cashier-money-input"
                               value="${formatRupiah(item.discount)}" inputmode="numeric" pattern="[0-9.]*"
                               autocomplete="off" spellcheck="false" data-index="${index}"
                               aria-label="Diskon ${escapeHtml(item.name)}">
                    </td>
                    <td class="text-end fw-bold item-total">${formatRupiah(itemTotal)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}"
                                aria-label="Hapus ${escapeHtml(item.name)}" title="Hapus produk">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            `;
                $('#product_rows').append(row);
            });

            if (cartItems.length === 0) {
                $('#product_rows').html(`
                    <tr class="cashier-empty-row">
                        <td colspan="9">
                            <div class="cashier-empty-state">
                                <i class="bi bi-cart3" aria-hidden="true"></i>
                                <span>Belum ada produk dalam transaksi</span>
                            </div>
                        </td>
                    </tr>
                `);
            }

            // Hitung grand total
            const requestedInvoiceDiscount = Math.max(0, parseRupiah($('#sale_disc').val()));
            const discFaktur = normalizeDiscount(subtotal, requestedInvoiceDiscount);
            if (discFaktur !== requestedInvoiceDiscount) {
                $('#sale_disc').val(formatRupiah(discFaktur));
            }
            const totalAfterDisc = applySignedDiscount(subtotal, discFaktur);
            const grandTotal = totalAfterDisc;
            let totalPpn = 0;
            cartItems.forEach(item => {
                const itemSubtotal = Number(item.price || 0) * Number(item.qty || 0);
                const itemTotal = applySignedDiscount(itemSubtotal, item.discount);
                const itemAfterInvoiceDiscount = applyInvoiceDiscountToLine(itemTotal, subtotal, discFaktur);
                totalPpn += ppnFromGrossAmount(itemAfterInvoiceDiscount, item.is_ppn);
            });

            // Update displays
            $('#sub_total_display').text(formatRupiah(subtotal));
            $('#sale_ppn').val(Math.round(totalPpn));
            $('#grand_total_text').text(formatRupiah(Math.round(grandTotal)));
            $('#grand_total_display').text('Rp ' + formatRupiah(Math.round(grandTotal)));
            $('#sale_total').val(Math.round(grandTotal));

            // Update hidden arrays
            updateHiddenArrays();

            // Attach event handlers
            attachCartEventHandlers();
        }

        function attachCartEventHandlers() {
            // Quantity buttons removed - displaying qty as text only

            // Quantity input
            $('.qty-input').off('change blur').on('change', async function() {
                const index = $(this).data('index');
                const oldQty = normalizeKasirQuantity(cartItems[index]?.qty);
                const newQty = kasirQuantityFromInput($(this).val());
                if (newQty === 0) {
                    $(this).val(kasirQuantityInput(oldQty));
                    showMessage('Quantity tidak boleh 0', 'warning');
                    return;
                }

                if (newQty < 0) {
                    // Jangan tampilkan angka minus sebelum otorisasi server benar-benar berhasil.
                    if (!minusPassword) {
                        $(this).val(kasirQuantityInput(oldQty));
                    }
                    if (!(await requestMinusPassword())) {
                        $(this).val(kasirQuantityInput(oldQty));
                        return;
                    }
                }

                cartItems[index].qty = normalizeKasirQuantity(newQty);
                if (!hasReturnItems()) {
                    minusPassword = '';
                }
                updateCartDisplay();
            });

            // Discount input
            $('.discount-input').off('input blur')
            .on('input', function() {
                normalizeMoneyInput(this);
            })
            .on('blur', function() {
                const index = $(this).data('index');
                const lineSubtotal = Number(cartItems[index]?.price || 0) * Number(cartItems[index]?.qty || 0);
                const requestedDiscount = Math.max(0, parseRupiah($(this).val()));
                const discount = normalizeDiscount(lineSubtotal, requestedDiscount);
                if (discount !== requestedDiscount) {
                    showMessage('Diskon item tidak boleh melebihi subtotal barang', 'warning');
                }
                cartItems[index].discount = discount;
                updateCartDisplay();
            });

            // Remove item
            $('.remove-item').off('click').on('click', function() {
                const index = $(this).data('index');
                cartItems.splice(index, 1);
                updateCartDisplay();
            });
        }

        function updateHiddenArrays() {
            const produk_ids = [];
            const qtys = [];
            const harga_arrays = [];
            const disc_items = [];
            const is_ppns = [];
            const satuan_ids = [];
            const isis = [];

            cartItems.forEach(item => {
                produk_ids.push(item.id);
                qtys.push(item.qty);
                harga_arrays.push(item.price);
                disc_items.push(item.discount);
                is_ppns.push(item.is_ppn);
                satuan_ids.push(item.satuan_id || 0);
                isis.push(item.isi || 1);
            });

            $('#produk_id_array').val(JSON.stringify(produk_ids));
            $('#qty_array').val(JSON.stringify(qtys));
            $('#harga_array').val(JSON.stringify(harga_arrays));
            $('#disc_item_array').val(JSON.stringify(disc_items));
            $('#is_ppn_array').val(JSON.stringify(is_ppns));
            $('#satuan_id_array').val(JSON.stringify(satuan_ids));
            $('#isi_array').val(JSON.stringify(isis));
        }

        /** ======================= [ CALCULATION FUNCTIONS ] ======================= **/
        function calculateChange() {
            // No longer used - payment calculation moved to modal
        }

        /** ======================= [ FORM VALIDATION & SUBMISSION ] ======================= **/
        async function validateForm() {
            if (cartItems.length === 0) {
                showMessage('Tambahkan produk terlebih dahulu', 'warning');
                return false;
            }

            const grandTotal = parseRupiah($('#sale_total').val());

            if (grandTotal === 0) {
                showMessage('Total penjualan tidak boleh 0', 'warning');
                return false;
            }

            // Validasi stok
            let stockError = false;
            let errorMessage = '';

            cartItems.forEach(item => {
                if (item.qty > 0 && item.qty > item.stok) {
                    stockError = true;
                    errorMessage += `${item.name}: Stok ${formatKasirQuantity(item.stok)}, Permintaan ${formatKasirQuantity(item.qty)}<br>`;
                }
            });

            if (stockError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Stok Tidak Cukup',
                    html: errorMessage,
                    confirmButtonText: 'OK'
                });
                return false;
            }

            if (requiresMinusPassword() && !(await requestMinusPassword())) {
                return false;
            }

            return true;
        }

        async function submitTransaction() {
            if (!(await validateForm())) return;

            // Disable button to prevent double submission
            const $btn = $('#btn_save_print');
            if ($btn.prop('disabled')) {
                Swal.fire('Info', 'Transaksi sedang diproses...', 'info');
                return;
            }
            $btn.prop('disabled', true).addClass('opacity-50');

            start_loader();

            const custIdVal = $('#cust_id').val();
            const custId = (custIdVal && custIdVal !== '0') ? parseInt(custIdVal) : null;
            const formData = {
                saleid: currentEditSaleId || null,
                sale_date: $('#sale_date').val(),
                sale_invoice: $('#sale_invoice').val(),
                cust_id: custId,
                user_id: currentUserId,
                payment_method: $('#payment_method').val() || 'cash',
                catatan: $('#catatan').val() || '',
                sale_disc: parseRupiah($('#sale_disc').val() || '0'),
                sale_total: parseRupiah($('#sale_total').val() || '0'),
                sale_ppn: parseRupiah($('#sale_ppn').val() || '0'),
                minus_password: requiresMinusPassword() ? minusPassword : '',
                amount_paid: requiresMinusPassword() ? 0 : parseRupiah($('#amount_paid').val() || '0'),
                change_amount: requiresMinusPassword() ? 0 : parseRupiah($('#change_amount').val() || '0'),
                items: cartItems.map(item => ({
                    produk_id: item.id,
                    qty: item.qty,
                    harga: item.price,
                    disc_item: item.discount || 0,
                    is_ppn: item.is_ppn || 0,
                    satuan_id: item.satuan_id || 0,
                    isi: item.isi || 1,
                    sale_capital: item.modal || 0
                }))
            };

            const submitAction = currentEditSaleId ? 'update_penjualan_kasir' : 'save_penjualan_kasir';

            $.ajax({
                url: baseUrlKasir + "?f=" + submitAction,
                method: "POST",
                data: JSON.stringify(formData),
                contentType: "application/json",
                dataType: "json",
                success: function(resp) {
                    console.log('Transaction response:', resp);
                    if (resp && resp.status === "success" && resp.data) {
                        const wasEdit = !!currentEditSaleId;
                        // Tampilkan struk
                        if (resp.data.saleid) {
                            printReceipt(resp.data.saleid);
                        }

                        if (wasEdit) {
                            $btn.prop('disabled', false).removeClass('opacity-50');
                            const pointsText = (resp.data.points_earned && resp.data.points_earned > 0) ? `(+${resp.data.points_earned} poin)` : '';
                            showMessage('Transaksi berhasil diperbarui ' + pointsText + '. Mengalihkan ke riwayat...', 'success');
                            backToRiwayat(900);
                            return;
                        }

                        // Reset form
                        resetForm();

                        // Re-enable button after reset (which generates new invoice)
                        $btn.prop('disabled', false).removeClass('opacity-50');

                        // Tampilkan pesan sukses
                        const pointsText = (resp.data.points_earned && resp.data.points_earned > 0) ? `(+${resp.data.points_earned} poin)` : '';
                        showMessage((wasEdit ? 'Transaksi berhasil diperbarui ' : 'Transaksi berhasil disimpan ') + pointsText, 'success');

                        // Reload summary
                        loadTodaySummary();
                    } else {
                        // Re-enable button on error so user can retry
                        $btn.prop('disabled', false).removeClass('opacity-50');
                        showMessage((resp && resp.msg) || 'Gagal menyimpan transaksi', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error Status:', status);
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);

                    let errorMsg = 'Terjadi kesalahan saat menyimpan transaksi';
                    if (xhr.responseText) {
                        try {
                            const errorResp = JSON.parse(xhr.responseText);
                            errorMsg = errorResp.msg || errorMsg;
                        } catch (e) {
                            errorMsg = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }

                    // Re-enable button on network error so user can retry
                    $btn.prop('disabled', false).removeClass('opacity-50');
                    showMessage(errorMsg, 'danger');
                },
                complete: function() {
                    end_loader();
                }
            });
        }

        function resetForm() {
            cartItems = [];
            currentRowIndex = 0;
            currentEditSaleId = null;
            currentEditPaymentMethod = 'cash';
            currentEditAmountPaid = 0;
            minusPassword = '';
            setEditMode(false);
            $('#kasir-form')[0].reset();
            $('#saleid').val('');
            $('#sale_date').val(getCurrentDateTimeLocal());
            if ($('#cust_id option[value="0"]').length === 0) {
                $('#cust_id').append(new Option('Member Umum', '0', true, true));
            }
            $('#cust_id').val('0').trigger('change');
            $('#catatan').val('');
            $('#sale_disc').val('0');
            $('#cr_produk').val('');
            $('#product_gallery_search').val('');
            loadProductGallery();
            $('#sale_qty').val('1');
            $('#satuan').val('');
            $('#sale_harga').val('0');
            // Reset modal values
            $('#modal_amount_paid').val('0');
            $('#modal_method_tunai').prop('checked', true);
            generateInvoice();
            updateCartDisplay();
            focusInput('#sale_date', false);
        }

        function printReceipt(saleid) {
            const printWindow = window.open(baseUrlKasir + "?f=print_receipt&id=" + saleid, '_blank');
            if (!printWindow) {
                Swal.fire('Info', 'Izinkan popup untuk mencetak struk', 'info');
            }
        }

        function showMessage(msg, type = 'primary') {
            const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show">
                ${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `).hide();
            $('#form-message').html(alert);
            alert.show('slow');
            setTimeout(() => alert.hide('slow').remove(), 5000);
        }

        function getSelectedCustomerId() {
            const custIdVal = $('#cust_id').val();
            return (custIdVal && custIdVal !== '0') ? parseInt(custIdVal) : null;
        }

        function isPiutangPayment(amountPaid = null) {
            if (parseRupiah($('#sale_total').val()) < 0) return false;
            const paid = amountPaid === null ? parseRupiah($('#modal_amount_paid').val()) : amountPaid;
            return paid <= 0;
        }

        function getModalPaymentMethod() {
            if (parseRupiah($('#sale_total').val()) < 0) return 'retur';
            return isPiutangPayment() ? 'piutang' : ($('input[name="modal_payment_method"]:checked').val() || 'cash');
        }

        function validateModalPayment() {
            const grandTotal = parseRupiah($('#sale_total').val());
            const amountPaid = parseRupiah($('#modal_amount_paid').val());
            const custId = getSelectedCustomerId();

            if (grandTotal < 0) {
                return true;
            }

            if (amountPaid < 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pembayaran Tidak Valid',
                    text: 'Jumlah pembayaran tidak valid'
                }).then(() => focusInput('#modal_amount_paid'));
                return false;
            }

            if (amountPaid <= 0) {
                if (!custId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih Member',
                        text: 'Transaksi piutang wajib menggunakan member/pelanggan.'
                    }).then(() => {
                        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('kasirPaymentModal'));
                        if (modalInstance) modalInstance.hide();
                        setTimeout(() => focusSelect2('#cust_id', true), 250);
                    });
                    return false;
                }
                return true;
            }

            if (amountPaid < grandTotal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pembayaran Kurang',
                    text: 'Jumlah pembayaran kurang dari total belanja. Isi 0 jika ingin masuk piutang.'
                }).then(() => focusInput('#modal_amount_paid'));
                return false;
            }

            return true;
        }

        /** ======================= [ EVENT HANDLERS ] ======================= **/
        // Add product via manual form
        $('#add_to_list').click(function() {
            addManualProduct();
        });

        $('#sale_disc').on('input', function() {
            normalizeMoneyInput(this);
            updateCartDisplay();
        });

        // Buttons (moved to modal)
        $('#btn_reset').click(function() {
            Swal.fire({
                title: 'Reset Transaksi?',
                text: 'Semua item dalam keranjang akan dihapus',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Reset',
                cancelButtonText: 'Tidak'
            }).then((result) => {
                if (result.isConfirmed) {
                    resetForm();
                }
            });
        });

        $('#btn_cancel_edit').click(function() {
            Swal.fire({
                title: 'Batalkan Edit?',
                text: 'Perubahan pada invoice ini tidak akan disimpan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Lanjut Edit'
            }).then((result) => {
                if (result.isConfirmed) {
                    backToRiwayat();
                }
            });
        });

        // Handle BAYAR button click
        $('#btn_checkout_payment').click(async function() {
            console.log('🛒 BAYAR button clicked');

            // Validate form
            if (!(await validateForm())) {
                console.log('❌ Form validation failed');
                return;
            }

            console.log('✅ Form validation passed, showing payment modal');
            showPaymentModal();
        });

        // Modal payment buttons
        $('#modal_btn_cancel').click(function() {
            // Close modal and reset form
            bootstrap.Modal.getInstance(document.getElementById('kasirPaymentModal')).hide();
            if (cartItems.length > 0) {
                Swal.fire({
                    title: 'Batalkan Transaksi?',
                    text: 'Semua item dalam keranjang akan hilang',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Batalkan',
                    cancelButtonText: 'Tidak'
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetForm();
                    }
                });
            } else {
                resetForm();
            }
        });

        // SIMPAN only (tanpa cetak) - di modal
        $('#modal_btn_save').click(async function() {
            if (!(await validateForm())) return;

            // Validate payment amount
            if (!validateModalPayment()) return;

            const $btn = $(this);
            if ($btn.prop('disabled')) {
                Swal.fire('Info', 'Transaksi sedang diproses...', 'info');
                return;
            }
            $btn.prop('disabled', true).addClass('opacity-50');

            start_loader();

            const custId = getSelectedCustomerId();

            const formData = {
                saleid: currentEditSaleId || null,
                sale_date: $('#sale_date').val(),
                sale_invoice: $('#sale_invoice').val(),
                cust_id: custId,
                user_id: currentUserId,
                payment_method: getModalPaymentMethod(),
                catatan: $('#catatan').val() || '',
                sale_disc: parseRupiah($('#sale_disc').val() || '0'),
                sale_total: parseRupiah($('#sale_total').val() || '0'),
                sale_ppn: parseRupiah($('#sale_ppn').val() || '0'),
                minus_password: requiresMinusPassword() ? minusPassword : '',
                amount_paid: requiresMinusPassword() ? 0 : parseRupiah($('#modal_amount_paid').val() || '0'),
                change_amount: requiresMinusPassword() ? 0 : Math.max(parseRupiah($('#modal_amount_paid').val() || '0') - parseRupiah($('#sale_total').val() || '0'), 0),
                items: cartItems.map(item => ({
                    produk_id: item.id,
                    qty: item.qty,
                    harga: item.price,
                    disc_item: item.discount || 0,
                    is_ppn: item.is_ppn || 0,
                    satuan_id: item.satuan_id || 0,
                    isi: item.isi || 1,
                    sale_capital: item.modal || 0
                }))
            };
            const submitAction = currentEditSaleId ? 'update_penjualan_kasir' : 'save_penjualan_kasir';

            $.ajax({
                url: baseUrlKasir + "?f=" + submitAction,
                method: "POST",
                data: JSON.stringify(formData),
                contentType: "application/json",
                dataType: "json",
                success: function(resp) {
                    if (resp && resp.status === "success" && resp.data) {
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('kasirPaymentModal')).hide();
                        const wasEdit = !!currentEditSaleId;
                        if (wasEdit) {
                            $btn.prop('disabled', false).removeClass('opacity-50');
                            showMessage((resp.msg || 'Transaksi berhasil diperbarui') + '. Mengalihkan ke riwayat...', 'success');
                            backToRiwayat(900);
                            return;
                        }

                        resetForm();
                        $btn.prop('disabled', false).removeClass('opacity-50');
                        showMessage(resp.msg || 'Transaksi berhasil disimpan', 'success');
                        loadTodaySummary();
                    } else {
                        $btn.prop('disabled', false).removeClass('opacity-50');
                        showMessage((resp && resp.msg) || 'Gagal menyimpan transaksi', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Terjadi kesalahan saat menyimpan transaksi';
                    if (xhr.responseText) {
                        try {
                            const errorResp = JSON.parse(xhr.responseText);
                            errorMsg = errorResp.msg || errorMsg;
                        } catch (e) {
                            errorMsg = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    $btn.prop('disabled', false).removeClass('opacity-50');
                    showMessage(errorMsg, 'danger');
                },
                complete: function() {
                    end_loader();
                }
            });
        });

        // SIMPAN & CETAK - dari modal
        $('#modal_btn_save_print').click(async function() {
            if (!(await validateForm())) return;

            // Validate payment amount
            if (!validateModalPayment()) return;

            const $btn = $(this);
            if ($btn.prop('disabled')) {
                Swal.fire('Info', 'Transaksi sedang diproses...', 'info');
                return;
            }
            $btn.prop('disabled', true).addClass('opacity-50');

            start_loader();

            const custId = getSelectedCustomerId();

            const formData = {
                saleid: currentEditSaleId || null,
                sale_date: $('#sale_date').val(),
                sale_invoice: $('#sale_invoice').val(),
                cust_id: custId,
                user_id: currentUserId,
                payment_method: getModalPaymentMethod(),
                catatan: $('#catatan').val() || '',
                sale_disc: parseRupiah($('#sale_disc').val() || '0'),
                sale_total: parseRupiah($('#sale_total').val() || '0'),
                sale_ppn: parseRupiah($('#sale_ppn').val() || '0'),
                minus_password: requiresMinusPassword() ? minusPassword : '',
                amount_paid: requiresMinusPassword() ? 0 : parseRupiah($('#modal_amount_paid').val() || '0'),
                change_amount: requiresMinusPassword() ? 0 : Math.max(parseRupiah($('#modal_amount_paid').val() || '0') - parseRupiah($('#sale_total').val() || '0'), 0),
                items: cartItems.map(item => ({
                    produk_id: item.id,
                    qty: item.qty,
                    harga: item.price,
                    disc_item: item.discount || 0,
                    is_ppn: item.is_ppn || 0,
                    satuan_id: item.satuan_id || 0,
                    isi: item.isi || 1,
                    sale_capital: item.modal || 0
                }))
            };
            const submitAction = currentEditSaleId ? 'update_penjualan_kasir' : 'save_penjualan_kasir';

            $.ajax({
                url: baseUrlKasir + "?f=" + submitAction,
                method: "POST",
                data: JSON.stringify(formData),
                contentType: "application/json",
                dataType: "json",
                success: function(resp) {
                    if (resp && resp.status === "success" && resp.data) {
                        // Close modal & print
                        bootstrap.Modal.getInstance(document.getElementById('kasirPaymentModal')).hide();
                        const wasEdit = !!currentEditSaleId;
                        if (resp.data.saleid) {
                            printReceipt(resp.data.saleid);
                        }

                        if (wasEdit) {
                            $btn.prop('disabled', false).removeClass('opacity-50');
                            showMessage((resp.msg || 'Transaksi berhasil diperbarui & dicetak') + '. Mengalihkan ke riwayat...', 'success');
                            backToRiwayat(900);
                            return;
                        }

                        resetForm();
                        $btn.prop('disabled', false).removeClass('opacity-50');
                        showMessage(resp.msg || 'Transaksi berhasil disimpan & dicetak', 'success');
                        loadTodaySummary();
                    } else {
                        $btn.prop('disabled', false).removeClass('opacity-50');
                        showMessage((resp && resp.msg) || 'Gagal menyimpan transaksi', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Terjadi kesalahan saat menyimpan transaksi';
                    if (xhr.responseText) {
                        try {
                            const errorResp = JSON.parse(xhr.responseText);
                            errorMsg = errorResp.msg || errorMsg;
                        } catch (e) {
                            errorMsg = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    $btn.prop('disabled', false).removeClass('opacity-50');
                    showMessage(errorMsg, 'danger');
                },
                complete: function() {
                    end_loader();
                }
            });
        });

        // Function untuk tampilkan modal pembayaran - dipanggil saat btn_save_print atau btn_save dari main page ditekan
        function showPaymentModal() {
            const grandTotal = parseRupiah($('#sale_total').val());
            const subtotal = parseRupiah($('#sub_total_display').text());
            const discountFaktur = parseRupiah($('#sale_disc').val());

            // Update modal summary
            $('#modal_grand_total').text('Rp ' + formatRupiah(grandTotal));
            $('#modal_subtotal').text('Rp ' + formatRupiah(subtotal));
            $('#modal_discount').text('Rp ' + formatRupiah(discountFaktur));
            $('#modal_total').text('Rp ' + formatRupiah(grandTotal));

            const initialPaid = grandTotal < 0 ? 0 : (currentEditSaleId ? Math.max(currentEditAmountPaid, grandTotal) : 0);

            // Reset modal input fields
            $('#modal_amount_paid').val(formatRupiah(initialPaid));
            $('#modal_payment_display').text('Rp ' + formatRupiah(initialPaid));
            $('#modal_change_display').text('Rp ' + formatRupiah(Math.max(initialPaid - grandTotal, 0)));

            // Set default payment method
            const methodToSelect = currentEditSaleId ? currentEditPaymentMethod : 'cash';
            const methodInput = $(`input[name="modal_payment_method"][value="${methodToSelect}"]`);
            if (methodInput.length) {
                methodInput.prop('checked', true);
            } else {
                $('#modal_method_tunai').prop('checked', true);
            }
            updatePaymentMethodDisplay();

            // Tampilkan modal
            const modalElement = document.getElementById('kasirPaymentModal');
            $(modalElement).one('shown.bs.modal', () => focusInput('label[for="modal_method_tunai"]', false));
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }

        // Handle denomination button clicks in modal
        $(document).on('click', '.denom-btn', function() {
            const amount = $(this).data('amount') || 0;
            const currentAmount = parseRupiah($('#modal_amount_paid').val());
            const newAmount = currentAmount + amount;
            $('#modal_amount_paid').val(formatRupiah(newAmount)).trigger('input');
        });

        // Handle exact amount button
        $(document).on('click', '#btn_exact_amount', function() {
            const grandTotal = parseRupiah($('#sale_total').val());
            $('#modal_amount_paid').val(formatRupiah(grandTotal)).trigger('input');
        });

        // Handle modal amount input
        $('#modal_amount_paid').on('input', function() {
            const val = normalizeMoneyInput(this);

            const grandTotal = parseRupiah($('#sale_total').val());
            const change = Math.max(val - grandTotal, 0);

            $('#modal_payment_display').text('Rp ' + formatRupiah(val));
            $('#modal_change_display').text('Rp ' + formatRupiah(change));
            updatePaymentMethodDisplay();
        });

        $('#modal_amount_paid').on('blur', function() {
            normalizeMoneyInput(this);
        });

        $('#modal_amount_paid').off('keydown.kasirPaymentEnter').on('keydown.kasirPaymentEnter', function(e) {
            if (e.key !== 'Enter') return;

            e.preventDefault();
            e.stopPropagation();
            $('#modal_btn_save_print').trigger('click');
        });

        // Handle payment method selection in modal
        $(document).on('change', '.payment-method-check', updatePaymentMethodDisplay);
        $(document).on('focus', '.payment-method-check', function() {
            $(this).prop('checked', true).trigger('change');
        });
        $(document).on('focus', '.payment-method-label', function() {
            const targetId = $(this).attr('for');
            if (targetId) {
                $('#' + targetId).prop('checked', true).trigger('change');
            }
        });
        $(document).on('keydown', '.payment-method-label', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            const targetId = $(this).attr('for');
            if (targetId) {
                $('#' + targetId).prop('checked', true).trigger('change').focus();
            }
        });

        function updatePaymentMethodDisplay() {
            const selectedMethod = $('input[name="modal_payment_method"]:checked').val();
            if (isPiutangPayment()) {
                $('#modal_payment_method').text('Piutang');
                return;
            }

            const methodDisplay = {
                'cash': 'Tunai',
                'debit_card': 'Kartu Debit',
                'credit_card': 'Kartu Kredit',
                'qris': 'QRIS',
                'e_wallet': 'E-Wallet',
                'piutang': 'Piutang'
            };
            $('#modal_payment_method').text(methodDisplay[selectedMethod] || selectedMethod);
        }

        function initializeKeyboardShortcutHints() {
            $('#product_gallery_search').attr({
                'aria-keyshortcuts': '/',
                title: '/: cari produk'
            });
            $('#sale_date').attr({
                'aria-keyshortcuts': 'F3',
                title: 'F3: tanggal transaksi'
            });
            $('#cr_produk').attr({
                'aria-keyshortcuts': 'F2',
                title: 'F2: tambah produk manual'
            });
            $('#cust_id').attr({
                'aria-keyshortcuts': 'F4',
                title: 'F4: pilih member'
            });
            $('#catatan').attr({
                'aria-keyshortcuts': 'F6',
                title: 'F6: catatan transaksi'
            });
            $('#sale_qty').attr({
                'aria-keyshortcuts': 'Alt+J',
                title: 'Alt+J: jumlah produk manual'
            });
            $('#sale_disc').attr({
                'aria-keyshortcuts': 'Alt+S',
                title: 'Alt+S: diskon'
            });
            $('#btn_checkout_payment').attr({
                'aria-keyshortcuts': 'F8 Alt+B',
                title: 'F8 / Alt+B: bayar'
            });
            $('#btn_reset').attr({
                'aria-keyshortcuts': 'Escape',
                title: 'Esc: batal / reset transaksi'
            });
            $('#product_rows').attr({
                'aria-keyshortcuts': 'Alt+Q Alt+I Alt+X Delete',
                title: 'Alt+Q: ubah qty, Alt+I: diskon item, Alt+X/Delete: hapus item terakhir/aktif'
            });
            $('#modal_amount_paid').attr({
                'aria-keyshortcuts': 'F2 F6 Enter Control+Enter F9 F10',
                title: 'F6: jumlah pembayaran, F2: bayar pas, Enter/F10: simpan & cetak, F9: simpan'
            });
            $('#modal_method_tunai').attr('aria-keyshortcuts', 'Alt+T');
            $('#modal_method_debit').attr('aria-keyshortcuts', 'Alt+D');
            $('#modal_method_credit').attr('aria-keyshortcuts', 'Alt+K');
            $('#modal_method_qris').attr('aria-keyshortcuts', 'Alt+Q');
            $('#modal_method_ewallet').attr('aria-keyshortcuts', 'Alt+E');
        }

        function handleEnterStep(e) {
            const target = e.target;
            const $target = $(target);
            const targetId = target.id;

            if ($target.is('button, a, textarea') || $target.hasClass('select2-search__field')) {
                return false;
            }

            if (targetId === 'sale_date') {
                e.preventDefault();
                focusSelect2('#cust_id', true);
                return true;
            }

            if (targetId === 'catatan') {
                e.preventDefault();
                focusQuickSearch(true);
                return true;
            }

            if (targetId === 'cr_produk') {
                e.preventDefault();
                if ($('#cr_produk').val().trim()) {
                    focusInput('#sale_qty');
                } else {
                    focusQuickSearch(true);
                }
                return true;
            }

            if (targetId === 'sale_qty') {
                e.preventDefault();
                addManualProduct();
                return true;
            }

            if (targetId === 'sale_disc') {
                e.preventDefault();
                $('#btn_checkout_payment').trigger('click');
                return true;
            }

            if ($target.hasClass('qty-input')) {
                e.preventDefault();
                $target.trigger('change');
                const index = $target.data('index');
                focusInput(`.discount-input[data-index="${index}"]`);
                return true;
            }

            if ($target.hasClass('discount-input')) {
                e.preventDefault();
                $target.trigger('blur');
                focusQuickSearch(true);
                return true;
            }

            return false;
        }

        function selectPaymentMethodByShortcut(selector) {
            const $method = $(selector);
            if (!$method.length) return;

            $method.prop('checked', true).trigger('change');
            focusInput('#modal_amount_paid');
        }

        function handlePaymentModalShortcut(e) {
            const key = normalizeShortcutKey(e);
            const lowerKey = key.toLowerCase();
            const paymentMethodShortcuts = {
                t: '#modal_method_tunai',
                d: '#modal_method_debit',
                k: '#modal_method_credit',
                q: '#modal_method_qris',
                e: '#modal_method_ewallet'
            };

            if (e.altKey && !e.ctrlKey && !e.shiftKey && paymentMethodShortcuts[lowerKey]) {
                e.preventDefault();
                selectPaymentMethodByShortcut(paymentMethodShortcuts[lowerKey]);
                return true;
            }

            if (key === 'Escape') {
                e.preventDefault();
                $('#modal_btn_cancel').trigger('click');
                return true;
            }

            if (key === 'F2') {
                e.preventDefault();
                setModalAmount(parseRupiah($('#sale_total').val()));
                return true;
            }

            if (key === 'F6') {
                e.preventDefault();
                focusInput('#modal_amount_paid');
                return true;
            }

            if (key === 'F9') {
                e.preventDefault();
                $('#modal_btn_save').trigger('click');
                return true;
            }

            if (key === 'F10' || (e.ctrlKey && key === 'Enter')) {
                e.preventDefault();
                $('#modal_btn_save_print').trigger('click');
                return true;
            }

            if (key === 'Enter' && e.target.id === 'modal_amount_paid') {
                e.preventDefault();
                $('#modal_btn_save_print').trigger('click');
                return true;
            }

            return false;
        }

        function normalizeShortcutKey(event) {
            const nativeEvent = event.originalEvent || event;
            const code = nativeEvent.code || '';
            const key = nativeEvent.key || '';

            if (/^F(?:[1-9]|1[0-2])$/.test(code)) return code;
            if (key && key !== 'Unidentified') return key;
            if (code === 'Slash') return '/';

            const keyCode = nativeEvent.which || nativeEvent.keyCode || 0;
            if (keyCode >= 112 && keyCode <= 123) return `F${keyCode - 111}`;
            if (keyCode === 191) return '/';
            return '';
        }

        function handlePriorityCashierShortcut(event) {
            if (isSweetAlertOpen() || isPaymentModalOpen()) return;

            const key = normalizeShortcutKey(event);
            const lowerKey = key.toLowerCase();
            const altPayment = event.altKey && !event.ctrlKey && !event.shiftKey && lowerKey === 'b';
            let handled = true;

            if (key === 'F2') {
                closeOpenSelect2();
                focusManualProductEntry();
            } else if (key === 'F3') {
                openSaleDatePicker();
            } else if (key === 'F8' || altPayment) {
                closeOpenSelect2();
                $('#btn_checkout_payment').trigger('click');
            } else if (key === '/' && !event.ctrlKey && !event.altKey && !event.metaKey) {
                focusQuickSearch(true);
            } else {
                handled = false;
            }

            if (!handled) return;
            event.preventDefault();
            event.stopPropagation();
        }

        if (window.sarinahKasirPriorityKeydown) {
            document.removeEventListener('keydown', window.sarinahKasirPriorityKeydown, true);
        }
        window.sarinahKasirPriorityKeydown = handlePriorityCashierShortcut;
        document.addEventListener('keydown', window.sarinahKasirPriorityKeydown, true);

        // Keyboard shortcuts
        $(document).off('keydown.kasirShortcuts').on('keydown.kasirShortcuts', function(e) {
            const key = normalizeShortcutKey(e);
            const lowerKey = key.toLowerCase();

            if (isSweetAlertOpen()) return;

            if (isPaymentModalOpen()) {
                handlePaymentModalShortcut(e);
                return;
            }

            if (isSelect2Open()) {
                const isSelect2SearchInput = $(e.target).hasClass('select2-search__field');
                const searchTerm = isSelect2SearchInput ? $(e.target).val().trim() : '';

                if (key === 'F8' || (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'b')) {
                    e.preventDefault();
                    closeOpenSelect2();
                    $('#btn_checkout_payment').trigger('click');
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'q') {
                    e.preventDefault();
                    focusCartQty();
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'i') {
                    e.preventDefault();
                    focusCartDiscount();
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'x') {
                    e.preventDefault();
                    removeCartItemByShortcut();
                    return;
                }

                if (key === 'Escape') {
                    e.preventDefault();
                    closeOpenSelect2();
                    if (!$('#btn_cancel_edit').hasClass('d-none')) {
                        $('#btn_cancel_edit').trigger('click');
                    } else {
                        $('#btn_reset').trigger('click');
                    }
                    return;
                }

                if (key === 'F3') {
                    e.preventDefault();
                    openSaleDatePicker();
                    return;
                }

                if (key === 'F2') {
                    e.preventDefault();
                    closeOpenSelect2();
                    focusManualProductEntry();
                    return;
                }

                if (key === 'F4') {
                    e.preventDefault();
                    focusSelect2('#cust_id', true);
                    return;
                }

                if (key === 'F6') {
                    e.preventDefault();
                    closeOpenSelect2();
                    focusInput('#catatan');
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 's') {
                    e.preventDefault();
                    closeOpenSelect2();
                    focusInput('#sale_disc');
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'j') {
                    e.preventDefault();
                    closeOpenSelect2();
                    const manualEntry = document.getElementById('manual_product_entry');
                    if (manualEntry) manualEntry.open = true;
                    focusInput('#sale_qty');
                    return;
                }

                if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'h') {
                    e.preventDefault();
                    closeOpenSelect2();
                    const shortcutHelp = document.getElementById('kasir_shortcut_help');
                    if (shortcutHelp) {
                        shortcutHelp.open = !shortcutHelp.open;
                        shortcutHelp.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                }

                if (activeSelect2Selector === '#cust_id' && key === 'Enter' && isSelect2SearchInput && searchTerm === '') {
                    e.preventDefault();
                    $('#cust_id').select2('close');
                    focusInput('#catatan');
                }

                return;
            }

            if (key === 'Escape') {
                e.preventDefault();
                if (!$('#btn_cancel_edit').hasClass('d-none')) {
                    $('#btn_cancel_edit').trigger('click');
                } else {
                    $('#btn_reset').trigger('click');
                }
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'q') {
                e.preventDefault();
                focusCartQty();
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'i') {
                e.preventDefault();
                focusCartDiscount();
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'x') {
                e.preventDefault();
                removeCartItemByShortcut();
                return;
            }

            if (key === 'Delete' && $(e.target).closest('tr[data-index]').length) {
                e.preventDefault();
                removeCartItemByShortcut();
                return;
            }

            if (key === '/' && !e.ctrlKey && !e.altKey && !e.metaKey) {
                e.preventDefault();
                focusQuickSearch(true);
                return;
            }

            if (key === 'F2') {
                e.preventDefault();
                focusManualProductEntry();
                return;
            }

            if (key === 'F3') {
                e.preventDefault();
                openSaleDatePicker();
                return;
            }

            if (key === 'F4') {
                e.preventDefault();
                focusSelect2('#cust_id', true);
                return;
            }

            if (key === 'F6') {
                e.preventDefault();
                closeOpenSelect2();
                focusInput('#catatan');
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'j') {
                e.preventDefault();
                const manualEntry = document.getElementById('manual_product_entry');
                if (manualEntry) manualEntry.open = true;
                focusInput('#sale_qty');
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'h') {
                e.preventDefault();
                const shortcutHelp = document.getElementById('kasir_shortcut_help');
                if (shortcutHelp) {
                    shortcutHelp.open = !shortcutHelp.open;
                    shortcutHelp.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return;
            }

            if (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 's') {
                e.preventDefault();
                focusInput('#sale_disc');
                return;
            }

            if (key === 'F8' || (e.altKey && !e.ctrlKey && !e.shiftKey && lowerKey === 'b')) {
                e.preventDefault();
                $('#btn_checkout_payment').trigger('click');
                return;
            }

            if (key === 'Enter') {
                handleEnterStep(e);
            }
        });

        // Initialize form
        initializeForm();
    });
</script>
