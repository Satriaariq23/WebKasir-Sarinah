<?php
$purcid = $purcid ?? null;
$purc_invoice = $purc_invoice ?? '';
$purc_datefak = $purc_datefak ?? date('Y-m-d');
$purc_date = $purc_date ?? $purc_datefak;
$purc_jt = $purc_jt ?? $purc_datefak;
$purc_disc = $purc_disc ?? 0;
$ppn = $ppn ?? 0;
$purc_total = $purc_total ?? 0;
$discount_perc = $discount_perc ?? 0;
$bayar_id = $bayar_id ?? 1;
$purc_nofak = $purc_nofak ?? '';
$supplier_nama = $supplier_nama ?? '';
$supplier_id = $supplier_id ?? '';
$catatan = $catatan ?? '';
$meta = $meta ?? ($_SESSION['userdata'] ?? []);
$pembelianFormPpnRate = $_settings->info('ppn_rate');
if ($pembelianFormPpnRate === false || $pembelianFormPpnRate === '' || !is_numeric($pembelianFormPpnRate)) {
    $pembelianFormPpnRate = 11;
}
$pembelianFormPpnRateDecimal = max(0, min(100, (float)$pembelianFormPpnRate)) / 100;
?>
<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-bordered">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title">
                        <span class="card-label text-dark fw-bolder fs-3 mb-1">
                            <?php echo !empty($purcid) ? "Edit Faktur Pembelian : " . $purc_invoice : 'Faktur Pembelian Baru' ?>
                        </span>
                    </h3>
                    <div class="card-toolbar">
                        <?php if ($_settings->userdata('level_id') < 2): ?>
                            <div class="card-toolbar" data-bs-toggle="tooltip" title="Tambah Master Data">
                                <a href="#" class="btn btn-sm btn-light btn-active-success me-3" id="new_supplier" data-bs-toggle="modal" data-bs-target="#uni_modal">
                                    + Supplier
                                </a>
                                <a href="#" class="btn btn-sm btn-light btn-active-success me-3" id="new_produk" data-bs-toggle="modal" data-bs-target="#uni_modal">
                                    + Produk
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="separator"></div>
                <div class="card-body">
                    <form action="" id="purc-form" method="post">
                        <input type="hidden" name="purcid" value="<?php echo $purcid ?? '' ?>">
                        <input type="hidden" name="purc_date" id="purc_date" value="<?php echo $purc_date ?? $purc_datefak ?? date('Y-m-d') ?>">
                        <input type="hidden" name="purc_disc" id="purc_disc_hidden" value="<?php echo $purc_disc ?? 0 ?>">
                        <input type="hidden" name="purc_ppn" id="purc_ppn_hidden" value="<?php echo $ppn ?? 0 ?>">
                        <input type="hidden" name="purc_total" id="purc_total_hidden" value="<?php echo $purc_total ?? 0 ?>">
                        <?php if (!empty($purcid)): ?>
                            <input type="hidden" name="user_edit" value="<?php echo $meta['id'] ?? '' ?>">
                        <?php else: ?>
                            <input type="hidden" name="user_id" value="<?php echo $meta['id'] ?? '' ?>">
                        <?php endif; ?>

                        <!-- Form Input Utama -->
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Tanggal & No Faktur -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="fw-bold required">Tanggal Faktur</label>
                                        <input required type="date" name="purc_datefak" id="purc_datefak"
                                            class="form-control form-control-sm"
                                            value="<?php echo $purc_datefak ?? date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="fw-bold">No. Faktur</label>
                                        <input type="text" class="form-control form-control-sm" id="purc_nofak"
                                            name="purc_nofak" value="<?php echo $purc_nofak ?? '' ?>"
                                            placeholder="No. Faktur dari Supplier">
                                    </div>
                                </div>

                                <!-- Jenis Bayar & Jatuh Tempo -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="fw-bold">Jenis Pembayaran</label>
                                        <select class="form-select form-select-sm text-uppercase" name="bayar_id" id="bayar_id">
                                            <option value="1" <?= (isset($bayar_id) && $bayar_id == 1) ? 'selected' : '' ?>>TUNAI</option>
                                            <option value="2" <?= (isset($bayar_id) && $bayar_id == 2) ? 'selected' : '' ?>>KREDIT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="fw-bold">Jatuh Tempo</label>
                                        <input type="date" name="purc_jt_display" id="purc_jt" class="form-control form-control-sm"
                                            value="<?php echo $purc_jt ?? $purc_datefak ?? date('Y-m-d') ?>">
                                        <input type="hidden" name="purc_jt" id="purc_jt_hidden" value="<?php echo $purc_jt ?? $purc_datefak ?? date('Y-m-d') ?>">
                                        <!-- <input type="date" name="purc_jt" id="purc_jt" class="form-control form-control-sm" value="<?php echo $purc_jt ?? date('Y-m-d') ?>" <?= (isset($bayar_id) && $bayar_id == 2) ? '' : 'readonly' ?>> -->
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-bold">No. Invoice</label>
                                <input type="text" class="form-control form-control-sm" readonly
                                    name="purc_invoice" id="purc_invoice"
                                    value="<?php echo $purc_invoice ?? '' ?>"
                                    placeholder="Generated otomatis">

                                <label class="fw-bold mt-3 required">Supplier</label>
                                <input type="text" class="form-control form-control-sm" id="supplier"
                                    value="<?php echo $supplier_nama ?? '' ?>" placeholder="Cari Nama Supplier...">
                                <input type="hidden" name="supplier_id" id="supplier_id" value="<?php echo $supplier_id ?? '' ?>">
                            </div>
                        </div>

                        <!-- Input Produk -->
                        <div class="separator my-3"></div>
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="fw-bold">Produk</label>
                                <input type="text" class="form-control form-control-sm" id="cr_produk" placeholder="Cari Produk...">
                                <input type="hidden" id="produk_id_current" value="">
                                <input type="hidden" id="produk_ppn_current" value="0">
                                <input type="hidden" id="produk_isi_current" value="1">
                            </div>
                            <div class="col-md-1">
                                <label class="fw-bold">Qty</label>
                                <input type="number" id="purc_qty" value="0" step="any" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-1">
                                <label class="fw-bold">Satuan</label>
                                <input type="text" id="satuan" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-md-2">
                                <label class="fw-bold">Harga</label>
                                <input type="number" id="purc_harga" value="0" step="any" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-1"><label class="fw-bold">% D1</label><input type="number" id="purc_pdisc1" value="0" step=".1" min="0" max="100" class="form-control form-control-sm"></div>
                            <div class="col-md-1"><label class="fw-bold">% D2</label><input type="number" id="purc_pdisc2" value="0" step=".1" min="0" max="100" class="form-control form-control-sm"></div>
                            <div class="col-md-1"><label class="fw-bold">% D3</label><input type="number" id="purc_pdisc3" value="0" step=".1" min="0" max="100" class="form-control form-control-sm"></div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-success btn-sm w-100" id="add_to_list">Add</button>
                            </div>
                        </div>

                        <!-- Table Produk -->
                        <div class="table-responsive mt-3">
                            <table class="table table-hover table-bordered" id="list">
                                <thead class="bg-dark text-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Produk</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-center">Satuan</th>
                                        <th class="text-end">Harga</th>
                                        <th class="text-end">% D1</th>
                                        <th class="text-end">% D2</th>
                                        <th class="text-end">% D3</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="8" class="text-end">Sub Total</th>
                                        <th class="text-end sub-total">0</th>
                                    </tr>
                                    <tr>
                                        <th colspan="8" class="text-end">Discount <input style="width:60px" name="discount_perc" type="number" min="0" max="100" value="<?php echo $discount_perc ?? 0 ?>"> %</th>
                                        <th class="text-end purc_disc"><?php echo $purc_disc ?? 0 ?></th>
                                    </tr>
                                    <tr class="d-none">
                                        <th colspan="8" class="text-end">PPN Otomatis</th>
                                        <th class="text-end ppn"><?php echo $ppn ?? 0 ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="8" class="text-end fs-4">GRAND TOTAL</th>
                                        <th class="text-end fs-4 grand-total">0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Catatan -->
                        <div class="mt-3">
                            <label class="fw-bold">Catatan</label>
                            <textarea name="catatan" id="catatan" rows="3" class="form-control"><?php echo $catatan ?? '' ?></textarea>
                        </div>
                    </form>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a class="btn btn-danger" href="<?php echo base_url . '/admin?page=pembelian' ?>"><i class="fa fa-chevron-left"></i> Kembali</a>
                    <button type="submit" class="btn btn-primary" form="purc-form">Simpan <i class="fa fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        const escapeHtml = text => $("<div>").text(text || "").html();

        function hitungTotal() {
            let subtotal = 0;
            $("#list tbody tr").each(function() {
                subtotal += parseFloat($(this).find("input[name='total[]']").val()) || 0;
            });

            let discPerc = parseFloat($("input[name='discount_perc']").val()) || 0;
            let disc = subtotal * (discPerc / 100);

            let ppn = 0;
            $("#list tbody tr").each(function() {
                ppn += parseFloat($(this).find("input[name='ppn[]']").val()) || 0;
            });

            let grand = subtotal - disc + ppn;

            $(".sub-total").text(subtotal.toLocaleString("id-ID"));
            $(".purc_disc").text(disc.toLocaleString("id-ID"));
            $(".ppn").text(ppn.toLocaleString("id-ID"));
            $(".grand-total").text(grand.toLocaleString("id-ID"));

            $("#purc_disc_hidden").val(disc);
            $("#purc_ppn_hidden").val(ppn);
            $("#purc_total_hidden").val(grand);
        }

        // Tambah produk ke list
        $("#add_to_list").on("click", function() {
            let nama = $("#cr_produk").val();
            let produkId = $("#produk_id_current").val();
            let isPpn = parseInt($("#produk_ppn_current").val()) || 0;
            let isi = parseInt($("#produk_isi_current").val()) || 1;
            let qty = parseFloat($("#purc_qty").val()) || 0;
            let satuan = $("#satuan").val();
            let harga = parseFloat($("#purc_harga").val()) || 0;
            let d1 = parseFloat($("#purc_pdisc1").val()) || 0;
            let d2 = parseFloat($("#purc_pdisc2").val()) || 0;
            let d3 = parseFloat($("#purc_pdisc3").val()) || 0;

            if (!produkId || !nama || qty <= 0 || harga <= 0) {
                alert("Isi produk dan qty!");
                return;
            }

            let total = qty * harga;
            total -= total * (d1 / 100);
            total -= total * (d2 / 100);
            total -= total * (d3 / 100);
            let ppnItem = isPpn === 1 ? Math.round(total * <?= json_encode($pembelianFormPpnRateDecimal) ?>) : 0;

            let row = `
            <tr>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger rem_row">&times;</button></td>
                <td>${escapeHtml(nama)}<input type="hidden" name="produk_id[]" value="${produkId}"><input type="hidden" name="isi[]" value="${isi}"></td>
                <td class="text-center">${qty}<input type="hidden" name="purc_qty[]" value="${qty}"></td>
                <td class="text-center">${escapeHtml(satuan)}<input type="hidden" name="satuan[]" value="${escapeHtml(satuan)}"></td>
                <td class="text-end">${harga.toLocaleString("id-ID")}<input type="hidden" name="purc_harga[]" value="${harga}"></td>
                <td class="text-end">${d1}<input type="hidden" name="purc_pdisc1[]" value="${d1}"></td>
                <td class="text-end">${d2}<input type="hidden" name="purc_pdisc2[]" value="${d2}"></td>
                <td class="text-end">${d3}<input type="hidden" name="purc_pdisc3[]" value="${d3}"></td>
                <td class="text-end">${total.toLocaleString("id-ID")}<input type="hidden" name="ppn[]" value="${ppnItem}"><input type="hidden" name="total[]" value="${total}"></td>
            </tr>
        `;

            $("#list tbody").append(row);
            hitungTotal();

            // Reset input produk
            $("#cr_produk, #satuan, #produk_id_current").val("");
            $("#produk_ppn_current").val("0");
            $("#produk_isi_current").val("1");
            $("#purc_qty, #purc_harga, #purc_pdisc1, #purc_pdisc2, #purc_pdisc3").val("0");

            // Fokus balik ke kolom produk
            $("#cr_produk").focus();

        });

        // Hapus baris produk
        $(document).on("click", ".rem_row", function() {
            $(this).closest("tr").remove();
            hitungTotal();
        });

        // Recalculate on discount change
        $(document).on("input", "input[name='discount_perc']", hitungTotal);

        // Auto set jatuh tempo
        // $("#bayar_id").on("change", function() {
        //     if ($(this).val() == "1") {
        //         $("#purc_jt").prop("disabled", true).val($("#purc_datefak").val());
        //     } else {
        //         $("#purc_jt").prop("disabled", false);
        //     }
        // });

        // Auto set jatuh tempo
        // $("#bayar_id").on("change", function() {
        //     if ($(this).val() == "1") {
        //         // ambil tanggal faktur
        //         let tgl = $("#purc_datefak").val();
        //         $("#purc_jt").prop("disabled", true).val(tgl);
        //         $("#purc_jt_hidden").val(tgl); // mirror ke hidden
        //     } else {
        //         $("#purc_jt").prop("disabled", false);
        //         $("#purc_jt_hidden").val($("#purc_jt").val());
        //     }
        // });

        // // Sinkronisasi setiap kali user ganti jatuh tempo manual
        // $("#purc_jt").on("change", function() {
        //     $("#purc_jt_hidden").val($(this).val());
        // });

        // // Inisialisasi saat load halaman
        // $(document).ready(function() {
        //     $("#purc_jt_hidden").val($("#purc_jt").val());
        // });
        // Auto set jatuh tempo saat ganti jenis bayar
        $("#bayar_id").on("change", function() {
            if ($(this).val() == "1") {
                let tgl = $("#purc_datefak").val();
                $("#purc_jt").prop("disabled", true).val(tgl);
                $("#purc_jt_hidden").val(tgl); // mirror ke hidden
            } else {
                $("#purc_jt").prop("disabled", false);
                $("#purc_jt_hidden").val($("#purc_jt").val());
            }
        });

        $("#purc_datefak").on("change", function() {
            $("#purc_date").val($(this).val());
            if ($("#bayar_id").val() == "1") {
                $("#purc_jt").val($(this).val());
                $("#purc_jt_hidden").val($(this).val());
            }
        });

        // Sinkronisasi kalau jatuh tempo diubah manual
        $("#purc_jt").on("change", function() {
            $("#purc_jt_hidden").val($(this).val());
        });

        // Saat halaman pertama kali load
        $(document).ready(function() {
            let bayar = $("#bayar_id").val();
            if (bayar == "1") {
                let tgl = $("#purc_datefak").val() || new Date().toISOString().slice(0, 10);
                $("#purc_jt").prop("disabled", true).val(tgl);
                $("#purc_jt_hidden").val(tgl);
            } else {
                $("#purc_jt_hidden").val($("#purc_jt").val());
            }
        });


        hitungTotal();
    });

    $(document).ready(function() {
        // // === Autocomplete Supplier ===
        $("#supplier").autocomplete({
            source: _base_url_ + "classes/Pembelian.php?f=search_supplier",
            minLength: 1,
            autoFocus: true,
            select: function(event, ui) {
                $("#supplier_id").val(ui.item.id);
                $("#supplier").val(ui.item.nama || ui.item.value || ui.item.text); // nama supplier tampil
                if (ui.item.alamat) {
                    $("#alamat").val(ui.item.alamat); // isi alamat kalau ada field
                }
                return false;
            }
        });

        // === Autocomplete Produk ===
        $("#cr_produk").autocomplete({
            source: _base_url_ + "classes/Pembelian.php?f=search_produk",
            minLength: 1,
            autoFocus: true,
            response: function(event, ui) {
                if (ui.content.length === 1 && !ui.content[0].id) {
                    ui.content[0].disabled = true;
                }
            },
            select: function(event, ui) {
                if (!ui.item.id) {
                    // reset qty = 0
                    $("#purc_qty").val(0);
                    $("#purc_harga").val(0);
                    $("#satuan").val("");
                    $("#produk_id_current").val("");
                    return false;
                }

                // Kalau produk valid, isi field
                const namaProduk = ui.item.nama_produk || ui.item.value || ui.item.text || '';
                $('#cr_produk').val(namaProduk);
                $("#produk_id_current").val(ui.item.id);
                $("#produk_ppn_current").val(ui.item.is_ppn || 0);
                $("#produk_isi_current").val(ui.item.isi || 1);
                $("#satuan").val(ui.item.satuan || ui.item.nama_satuan || '');
                $("#purc_harga").val(ui.item.harga_beli || ui.item.harga_newmodal || 0);
                $("#purc_qty").val(1); // default isi qty 1
                return false;
            }
        });
    });

    $(document).on("keydown", "input, select, textarea", function(e) {
        if (e.key === "Enter") {
            e.preventDefault(); // cegah submit form

            // cari semua input/select/textarea yang bisa difokus
            var focusable = $("input, select, textarea, button")
                .filter(":visible:not([disabled])");

            var index = focusable.index(this);
            if (index > -1 && index + 1 < focusable.length) {
                focusable.eq(index + 1).focus();
            }
        }
    });

    $(document).ready(function() {
        <?php if (!isset($purcid)) : ?>
            $("#purc_nofak").focus(); // form baru
        <?php else: ?>
            $("#supplier").focus(); // form edit
        <?php endif; ?>
    });

    // $(document).ready(function() {
    //     $("#purc-form").on("submit", function(e) {
    //         e.preventDefault();
    //         start_loader(); // kalau kamu sudah ada loader
    //         $.ajax({
    //             url: _base_url_ + "classes/Pembelian.php?f=save_pembelian",
    //             type: "POST",
    //             data: $(this).serialize(),
    //             dataType: "json",
    //             success: function(resp) {
    //                 end_loader();
    //                 if (resp.status === "success") {
    //                     alert_toast(resp.msg, "success");
    //                     setTimeout(function() {
    //                         location.href = _base_url_ + "admin?page=pembelian";
    //                     }, 1500);
    //                 } else {
    //                     alert_toast(resp.msg || "Gagal menyimpan", "error");
    //                 }
    //             },
    //             error: function(xhr, status, error) {
    //                 end_loader();
    //                 console.error("AJAX Error:", error);
    //                 alert_toast("Koneksi ke server gagal", "error");
    //             }
    //         });
    //     });
    // });

    // $(document).ready(function() {
    //     $("#purc-form").on("submit", function(e) {
    //         e.preventDefault();
    //         start_loader();

    //         $.ajax({
    //             url: _base_url_ + "classes/Pembelian.php?f=save_pembelian",
    //             type: "POST",
    //             data: $(this).serialize(),
    //             dataType: "json",
    //             success: function(resp) {
    //                 end_loader();
    //                 if (resp.status === "success") {
    //                     alert_toast(resp.msg, "success");
    //                     setTimeout(function() {
    //                         location.href = _base_url_ + "admin?page=pembelian";
    //                     }, 1500);
    //                 } else {
    //                     alert_toast(resp.msg || "Gagal menyimpan", "error");
    //                 }
    //             },
    //             error: function(xhr, status, error) {
    //                 end_loader();
    //                 console.error("AJAX Error:", error);
    //                 alert_toast("Koneksi ke server gagal", "error");
    //             }
    //         });
    //     });
    // });

    $("#purc-form").on("submit", function(e) {
        e.preventDefault();
        start_loader();
        $("#purc_jt_hidden").val($("#purc_jt").val());
        const purcid = $.trim($("input[name='purcid']").val());
        const action = purcid ? "update_pembelian" : "save_pembelian";

        $.ajax({
            url: _base_url_ + "classes/Pembelian.php?f=" + action,
            method: "POST",
            data: $(this).serialize(),
            dataType: "json",
            error: function(err) {
                console.error("AJAX Error:", err);
                alert_toast("Koneksi ke server gagal.", "error");
                end_loader();
            },
            success: function(resp) {
                if (resp.status === "success") {
                    const invoice = resp.purc_invoice || $("#purc_invoice").val() || "-";
                    alert_toast("Faktur berhasil disimpan. No. Invoice: " + invoice, "success");
                    setTimeout(() => {
                        location.href = _base_url_ + "admin?page=pembelian";
                    }, 1500);
                } else {
                    alert_toast(resp.msg || "Gagal menyimpan data", "error");
                }
                end_loader();
            }
        });
    });
</script>
