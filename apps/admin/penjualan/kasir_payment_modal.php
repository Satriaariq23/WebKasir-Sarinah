<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/kasir_payment_modal.php
 *  DESCRIPTION: Modal pembayaran untuk kasir
 *  AUTHOR: System
 * ==========================================================
 */
?>

<!-- Modal Pembayaran Kasir -->
<div class="modal fade" id="kasirPaymentModal" tabindex="-1" aria-labelledby="kasirPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white fw-bold" id="kasirPaymentModalLabel">
                    <i class="bi bi-credit-card me-2"></i> Konfirmasi Pembayaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Grand Total Display -->
                <div class="alert alert-info mb-4">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <small class="text-muted d-block">TOTAL BELANJA:</small>
                            <h2 class="text-primary fw-bold mb-0" id="modal_grand_total">Rp 0</h2>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">Metode Pembayaran:</small>
                            <h4 class="text-primary fw-bold mb-0" id="modal_payment_method">-</h4>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold mb-3">
                        <i class="bi bi-wallet2 me-2"></i> Pilih Metode Pembayaran
                        <small class="payment-shortcut-note">Alt + tombol</small>
                    </label>
                    <div class="row g-3">
                        <!-- Tunai -->
                        <div class="col-6 col-md">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_tunai" value="cash" tabindex="-1" checked>
                                <label class="form-check-label payment-method-label w-100 p-3 border rounded cursor-pointer" for="modal_method_tunai" tabindex="0" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">💰 Tunai <kbd class="payment-shortcut-key">Alt+T</kbd></strong>
                                    <small class="text-muted">Pembayaran tunai/cash</small>
                                </label>
                            </div>
                        </div>

                        <!-- Kartu Debit -->
                        <div class="col-6 col-md">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_debit" value="debit_card" tabindex="-1">
                                <label class="form-check-label payment-method-label w-100 p-3 border rounded cursor-pointer" for="modal_method_debit" tabindex="0" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">🏦 Kartu Debit <kbd class="payment-shortcut-key">Alt+D</kbd></strong>
                                    <small class="text-muted">Pembayaran dengan kartu debit</small>
                                </label>
                            </div>
                        </div>

                        <!-- Kartu Kredit -->
                        <div class="col-6 col-md">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_credit" value="credit_card" tabindex="-1">
                                <label class="form-check-label payment-method-label w-100 p-3 border rounded cursor-pointer" for="modal_method_credit" tabindex="0" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">💳 Kartu Kredit <kbd class="payment-shortcut-key">Alt+K</kbd></strong>
                                    <small class="text-muted">Pembayaran menggunakan kartu kredit</small>
                                </label>
                            </div>
                        </div>

                        <!-- QRIS -->
                        <div class="col-6 col-md">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_qris" value="qris" tabindex="-1">
                                <label class="form-check-label payment-method-label w-100 p-3 border rounded cursor-pointer" for="modal_method_qris" tabindex="0" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">📱 QRIS <kbd class="payment-shortcut-key">Alt+Q</kbd></strong>
                                    <small class="text-muted">QRIS - Standar pembayaran digital</small>
                                </label>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div class="col-6 col-md">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_ewallet" value="e_wallet" tabindex="-1">
                                <label class="form-check-label payment-method-label w-100 p-3 border rounded cursor-pointer" for="modal_method_ewallet" tabindex="0" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">📲 E-Wallet <kbd class="payment-shortcut-key">Alt+E</kbd></strong>
                                    <small class="text-muted">GoPay, OVO, Dana, LinkAja, dll</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="payment-detail-grid">
                <!-- Summary -->
                <div class="card bg-light mb-0">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6"><small class="text-muted">Subtotal:</small></div>
                            <div class="col-6 text-end"><strong id="modal_subtotal">Rp 0</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><small class="text-muted">Diskon Faktur:</small></div>
                            <div class="col-6 text-end text-danger"><strong id="modal_discount">Rp 0</strong></div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-6"><strong>TOTAL BELANJA:</strong></div>
                            <div class="col-6 text-end"><strong class="text-primary fs-5" id="modal_total">Rp 0</strong></div>
                        </div>
                    </div>
                </div>

                <!-- Payment Input Section -->
                <div class="card mb-0 border-success">
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label for="modal_amount_paid" class="form-label fw-bold d-flex align-items-center justify-content-between gap-2">
                                <span>Masukkan Jumlah Pembayaran</span>
                                <span><kbd class="payment-shortcut-key">F6</kbd> <kbd class="payment-shortcut-key">F2 = Uang Pas</kbd></span>
                            </label>
                            <input type="text" class="form-control form-control-lg text-end cashier-money-input"
                                id="modal_amount_paid" placeholder="0" value="0"
                                inputmode="numeric" pattern="[0-9.]*" autocomplete="off" spellcheck="false"
                                style="font-size: 1.3rem; font-weight: bold; letter-spacing: 2px;">
                            <small class="text-muted d-block mt-2">Masukkan jumlah uang yang diterima. Isi 0 untuk masuk piutang pelanggan.</small>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Pembayaran:</small>
                                <p class="fs-5 fw-bold text-success mb-0" id="modal_payment_display">Rp 0</p>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">Kembalian:</small>
                                <p class="fs-5 fw-bold text-info mb-0" id="modal_change_display">Rp 0</p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" id="modal_btn_cancel" style="flex: 1;">
                    <i class="bi bi-x-circle me-1"></i> BATAL <kbd class="payment-shortcut-key payment-shortcut-key--button">Esc</kbd>
                </button>
                <button type="button" class="btn btn-success btn-lg" id="modal_btn_save" style="flex: 1;">
                    <i class="bi bi-save me-1"></i> SIMPAN <kbd class="payment-shortcut-key payment-shortcut-key--button">F9</kbd>
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="modal_btn_save_print" style="flex: 1;">
                    <i class="bi bi-printer me-1"></i> SIMPAN &amp; CETAK <kbd class="payment-shortcut-key payment-shortcut-key--button">F10</kbd>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS untuk styling payment method options -->
<style>
    #kasirPaymentModal .payment-method-check {
        cursor: pointer;
    }

    #kasirPaymentModal .payment-shortcut-note {
        margin-left: 7px;
        color: #7e8299;
        font-size: .68rem;
        font-weight: 500;
    }

    #kasirPaymentModal .payment-shortcut-key {
        display: inline-flex;
        min-height: 19px;
        padding: 2px 5px;
        align-items: center;
        justify-content: center;
        border: 1px solid #cfd5e2;
        border-radius: 4px;
        background: #fff;
        color: #181c32;
        font-family: inherit;
        font-size: .58rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    #kasirPaymentModal .payment-shortcut-key--button {
        margin-left: 5px;
        border-color: rgba(255, 255, 255, .55);
        background: rgba(255, 255, 255, .16);
        color: #fff;
    }

    #kasirPaymentModal .form-check-label {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    #kasirPaymentModal .form-check-input:checked+label {
        background-color: #e7f1ff !important;
        border-color: #0d6efd !important;
        border-width: 2px !important;
    }

    #kasirPaymentModal .form-check-label:hover {
        background-color: #f8f9fa !important;
    }

    #kasirPaymentModal .modal-content {
        max-height: calc(100vh - 24px);
        overflow: hidden;
        border: 0;
        box-shadow: 0 18px 55px rgba(24, 28, 50, .24);
    }

    #kasirPaymentModal .modal-body {
        overflow-y: auto;
    }

    #kasirPaymentModal .payment-method-label {
        height: 100%;
        min-height: 74px;
        padding: 11px !important;
        font-size: .78rem;
    }

    #kasirPaymentModal .payment-method-label small {
        display: block;
        font-size: .65rem;
        line-height: 1.3;
    }

    #kasirPaymentModal .payment-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
        gap: 12px;
    }

    @media (max-width: 767.98px) {
        #kasirPaymentModal .modal-dialog {
            margin: 8px;
        }

        #kasirPaymentModal .modal-header,
        #kasirPaymentModal .modal-body,
        #kasirPaymentModal .modal-footer {
            padding: 12px;
        }

        #kasirPaymentModal .alert {
            margin-bottom: 12px !important;
            padding: 10px;
        }

        #kasirPaymentModal #modal_grand_total {
            font-size: 1.25rem;
        }

        #kasirPaymentModal #modal_payment_method {
            font-size: .95rem;
        }

        #kasirPaymentModal .payment-detail-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        #kasirPaymentModal .modal-footer {
            display: grid;
            grid-template-columns: .75fr 1fr 1.25fr;
            gap: 7px;
        }

        #kasirPaymentModal .modal-footer .btn {
            min-width: 0;
            margin: 0;
            padding: 10px 5px;
            font-size: .68rem;
        }
    }
</style>
