<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Data Member</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Customer yang belanja langsung ke toko (eceran / datang sendiri)</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Tambah Member Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if ($_settings->set_userdata('user_type') < 3): ?>
                                <div class="form-group col-auto pt-5">
                                    <a class="btn btn-icon btn-success btn-sm" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#add_modal" id="btn_add"><i class="bi bi-file-earmark-plus fs-3"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <div class="d-flex overflow-auto h-40px">
                            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bolder">
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6" href="<?php echo base_url ?>admin/?page=pelanggan">Pelanggan</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 active" href="<?php echo base_url ?>admin/?page=pelanggan/member">Member</a>
                                </li>
                            </ul>
                        </div>

                        <!-- Tab Content -->
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 3%;">No</th>
                                        <th>Kode</th>
                                        <th class="text-start" style="width: 15%;">Nama</th>
                                        <th>Alamat</th>
                                        <th>Kota</th>
                                        <th>Telepon</th>
                                        <th style="width: 10%;">Status</th>
                                        <th class="text-center" style="width: 8%;">Aksi</th>
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
</div>
</div>

<!-- Modal Tambah Member -->
<div class="modal fade" id="add_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="new-member-frm">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label required">Nama</label>
                        <input type="text" name="nama" class="form-control text-uppercase" autocomplete="off" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control text-uppercase" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" class="form-control text-uppercase" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telpon" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="status" value="1" id="status" checked>
                            <label class="form-check-label" for="status">
                                Aktif
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

<!-- Modal Edit Member -->
<div class="modal fade" id="edit_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="edit-member-frm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id">
                    <!-- TAMPILKAN KODE SEBAGAI TEXT (READ-ONLY) -->
                    <div class="col-md-12">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" id="edit_kode_display" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Nama</label>
                        <input type="text" name="nama" class="form-control text-uppercase" autocomplete="off" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control text-uppercase" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" class="form-control text-uppercase" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telpon" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="status" value="1" id="edit_status">
                            <label class="form-check-label" for="edit_status">
                                Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    let membersDT;
    const baseUrl = "<?php echo base_url; ?>classes/Master.php";

    function load_data() {
        membersDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            stateSave: false,
            ajax: {
                url: baseUrl + "?f=get_data_member",
                type: "POST",
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
                    data: "kode"
                },
                {
                    data: "nama"
                },
                {
                    data: "alamat"
                },
                {
                    data: "kota"
                },
                {
                    data: "telpon"
                },
                {
                    data: "status",
                    className: "text-center",
                    render: function(data) {
                        return data == 1 ?
                            '<span class="badge bg-success">Aktif</span>' :
                            '<span class="badge bg-danger">Nonaktif</span>';
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
                    <button class="btn btn-icon btn-light-danger btn-sm delete_data" data-id="${row.id}" data-name="${escapeHtml(row.nama)}" title="Hapus Data.">
                        <i class="fa fa-trash"></i>
                    </button>
                    `
                }
            ],
            order: [
                [0, 'desc']
            ],
            language: {
                oPaginate: {
                    sNext: '<i class="fa fa-angle-right"></i>',
                    sPrevious: '<i class="fa fa-angle-left"></i>',
                    sFirst: '<i class="fa fa-step-backward"></i>',
                    sLast: '<i class="fa fa-step-forward"></i>'
                }
            }
        });

        $('#table').on('click', '.edit_data', function() {
            const id = $(this).data('id');
            $.ajax({
                url: baseUrl + "?f=get_single_member",
                type: "POST",
                data: {
                    id
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status) {
                        for (let k in resp.data) {
                            if (k === 'status') {
                                $('#edit_modal').find(`[name="${k}"]`).prop('checked', resp.data[k] == 1);
                            } else if (k === 'kode') {
                                // Tampilkan kode di field read-only
                                $('#edit_kode_display').val(resp.data[k]);
                            } else {
                                $('#edit_modal').find(`[name="${k}"]`).val(resp.data[k]);
                            }
                        }
                        $('#edit_modal').modal('show');
                    } else {
                        alert("Data tidak ditemukan");
                    }
                },
                error: () => alert("Gagal fetch data")
            });
        });

        $('#table').on('click', '.delete_data', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            _conf(`Yakin hapus permanen data <b>${escapeHtml(name)}</b>?`, "deleteData", [id]);
        });
    }

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

    $('#new-member-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        $.ajax({
            url: baseUrl + "?f=save_member",
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Menyimpan..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    form.get(0).reset();
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

    $('#edit-member-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        $.ajax({
            url: baseUrl + "?f=update_member",
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Memperbarui..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
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

    function reloadDataTable(resetPaging = false) {
        if (membersDT && $.fn.dataTable.isDataTable('#table')) {
            membersDT.ajax.reload(null, resetPaging);
        }
    }

    function deleteData(id) {
        start_loader();
        $.ajax({
            url: baseUrl + "?f=delete_member",
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
                        let _el = $('<div>')
                            .hide()
                            .addClass("alert alert-primary alert_msg")
                            .text("Data berhasil dihapus");
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

    // Paksa input nama, alamat, kota jadi huruf besar sebelum submit dan saat typing
    $('#new-member-frm, #edit-member-frm').on('submit', function() {
        $(this).find('input[name=nama], textarea[name=alamat], input[name=kota]').each(function() {
            $(this).val($(this).val().toUpperCase());
        });
    });

    $(document).ready(function() {
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

        // Untuk form tambah member
        $('#new-member-frm input[name=nama], #new-member-frm textarea[name=alamat], #new-member-frm input[name=kota]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        // Untuk form edit member
        $('#edit-member-frm input[name=nama], #edit-member-frm textarea[name=alamat], #edit-member-frm input[name=kota]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        load_data();

        // Reset form saat modal tambah dibuka
        $('#add_modal').on('show.bs.modal', function() {
            $('#new-member-frm')[0].reset();
        });

        $('#add_modal').on('hidden.bs.modal', function() {
            $('#new-member-frm')[0].reset();
        });
    });

    /**
     * Load reward data (point dan score) untuk semua row di table
     */
    function loadRewardDataForAllRows() {
        $('#table tbody tr').each(function() {
            const row = membersDT.row(this).data();
            if (row && row.id) {
                loadCustomerRewardData(row.id);
            }
        });
    }

    /**
     * Load reward data untuk satu customer
     */
    function loadCustomerRewardData(customerId) {
        // Load point
        $.ajax({
            url: baseUrl + "?f=get_customer_points",
            type: "POST",
            data: {
                pelanggan_id: customerId
            },
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success') {
                    const points = resp.total_points || 0;
                    $(`.loading-point-${customerId}`).text(points + ' Poin').removeClass('badge-info bg-info').addClass('badge-success bg-success');
                } else {
                    $(`.loading-point-${customerId}`).text('0 Poin').removeClass('badge-info bg-info').addClass('badge-secondary bg-secondary');
                }
            },
            error: function() {
                $(`.loading-point-${customerId}`).text('Error').removeClass('badge-info bg-info').addClass('badge-danger bg-danger');
            }
        });

        // Load reward score
        $.ajax({
            url: baseUrl + "?f=get_customer_reward_score",
            type: "POST",
            data: {
                pelanggan_id: customerId
            },
            dataType: "json",
            success: function(resp) {
                if (resp.status === 'success' && resp.score) {
                    const score = resp.score;
                    const nominalScore = parseFloat(score.nominal_score || 0).toFixed(1);
                    const frequencyScore = parseFloat(score.frequency_score || 0).toFixed(1);
                    const finalScore = parseFloat(score.final_score || 0).toFixed(1);
                    const eligibility = score.eligibility_status === 'ELIGIBLE' ?
                        '<span class="badge bg-success">ELIGIBLE</span>' :
                        '<span class="badge bg-danger">NOT ELIGIBLE</span>';

                    // Update nominal score
                    $(`.loading-nominal-${customerId}`)
                        .html(nominalScore)
                        .removeClass('bg-primary')
                        .addClass('bg-info');

                    // Update frequency score
                    $(`.loading-frequency-${customerId}`)
                        .html(frequencyScore)
                        .removeClass('bg-primary')
                        .addClass('bg-info');

                    // Update final score
                    $(`.loading-final-${customerId}`)
                        .html(finalScore)
                        .removeClass('bg-warning')
                        .addClass(finalScore >= 65 ? 'bg-success' : 'bg-danger');

                    // Update eligibility
                    $(`.loading-eligibility-${customerId}`).html(eligibility).removeClass('bg-secondary');
                } else {
                    // Belum ada score
                    $(`.loading-nominal-${customerId}`).html('-').removeClass('bg-primary').addClass('bg-secondary');
                    $(`.loading-frequency-${customerId}`).html('-').removeClass('bg-primary').addClass('bg-secondary');
                    $(`.loading-final-${customerId}`).html('-').removeClass('bg-warning').addClass('bg-secondary');
                    $(`.loading-eligibility-${customerId}`).html('-').removeClass('bg-secondary');
                }
            },
            error: function() {
                $(`.loading-nominal-${customerId}`).html('Error').removeClass('bg-primary').addClass('bg-danger');
                $(`.loading-frequency-${customerId}`).html('Error').removeClass('bg-primary').addClass('bg-danger');
                $(`.loading-final-${customerId}`).html('Error').removeClass('bg-warning').addClass('bg-danger');
                $(`.loading-eligibility-${customerId}`).html('Error').removeClass('bg-secondary').addClass('bg-danger');
            }
        });
    }

    // Reload reward data saat table di-refresh
    $('#table').on('draw.dt', function() {
        setTimeout(() => {
            loadRewardDataForAllRows();
        }, 300);
    });
</script>
