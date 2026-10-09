<?php

/**
 * ==========================================================
 *  FILE: admin/penjualan/modal_payment.php
 *  DESCRIPTION: Modal untuk pembayaran dan cetak
 *  AUTHOR: System
 * ==========================================================
 */
?>

<!-- Modal Pembayaran -->
<div class="modal fade" id="modal_payment" tabindex="-1" aria-labelledby="modal_payment_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white fw-bold" id="modal_payment_label">
                    <i class="bi bi-credit-card me-2"></i> Konfirmasi Pembayaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Grand Total Display -->
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block">Total Belanja:</small>
                            <h3 class="text-primary fw-bold mb-0" id="modal_grand_total">Rp 0</h3>
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
                    </label>
                    <div class="row g-3">
                        <!-- Tunai -->
                        <div class="col-12">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_tunai" value="tunai" checked>
                                <label class="form-check-label w-100 p-3 border rounded cursor-pointer" for="modal_method_tunai" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">💰 Tunai</strong>
                                    <small class="text-muted">Pembayaran tunai/cash</small>
                                </label>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="col-12">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_bank" value="bank">
                                <label class="form-check-label w-100 p-3 border rounded cursor-pointer" for="modal_method_bank" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">🏦 Bank Transfer</strong>
                                    <small class="text-muted">Transfer ke rekening toko</small>
                                </label>
                            </div>
                        </div>

                        <!-- Debit/Credit Card -->
                        <div class="col-12">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_card" value="card">
                                <label class="form-check-label w-100 p-3 border rounded cursor-pointer" for="modal_method_card" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">💳 Kartu (Debit/Credit)</strong>
                                    <small class="text-muted">Pembayaran menggunakan kartu</small>
                                </label>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div class="col-12">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_ewallet" value="ewallet">
                                <label class="form-check-label w-100 p-3 border rounded cursor-pointer" for="modal_method_ewallet" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">📱 E-Wallet</strong>
                                    <small class="text-muted">GoPay, OVO, Dana, LinkAja, dll</small>
                                </label>
                            </div>
                        </div>

                        <!-- Cicilan -->
                        <div class="col-12">
                            <div class="form-check form-check-lg">
                                <input class="form-check-input payment-method-check" type="radio" name="modal_payment_method" id="modal_method_cicilan" value="cicilan">
                                <label class="form-check-label w-100 p-3 border rounded cursor-pointer" for="modal_method_cicilan" style="cursor: pointer; transition: all 0.3s;">
                                    <strong class="d-block mb-1">📋 Cicilan/Hutang</strong>
                                    <small class="text-muted">Pembayaran dengan cicilan</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="mb-4">
                    <label class="form-label">Catatan Pembayaran (Opsional)</label>
                    <textarea class="form-control" id="modal_payment_note" rows="2" placeholder="Masukkan catatan tambahan jika diperlukan..."></textarea>
                </div>

                <!-- Summary -->
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6"><small class="text-muted">Subtotal:</small></div>
                            <div class="col-6 text-end"><strong id="modal_subtotal">Rp 0</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><small class="text-muted">Diskon:</small></div>
                            <div class="col-6 text-end text-danger"><strong id="modal_discount">Rp 0</strong></div>
                        </div>
                        <div class="row mb-0">
                            <div class="col-6"><small class="text-muted">PPN:</small></div>
                            <div class="col-6 text-end text-info"><strong id="modal_ppn">Rp 0</strong></div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-6"><strong>Total Belanja:</strong></div>
                            <div class="col-6 text-end"><strong class="text-primary fs-5" id="modal_total">Rp 0</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Batal
                </button>
                <button type="button" class="btn btn-primary" id="btn_confirm_payment">
                    <i class="bi bi-printer me-1"></i> Proses & Cetak
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS untuk styling payment method options -->
<style>
    .payment-method-check {
        cursor: pointer;
    }

    .form-check-label {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .form-check-input:checked+label {
        background-color: #e7f1ff !important;
        border-color: #0d6efd !important;
        border-width: 2px !important;
    }

    .form-check-label:hover {
        background-color: #f8f9fa !important;
    }
</style>