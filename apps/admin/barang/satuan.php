<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <!-- Header -->
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Data Satuan</span>
                    </h3>                                        
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Tambah Satuan Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if($_settings->set_userdata('user_type') < 3): ?>
                                <div class="form-group col-auto pt-6">
                                    <a class="btn btn-icon btn-success btn-sm" href="#" data-bs-toggle="modal" data-bs-target="#add_modal" id="btn_add">
                                        <i class="bi bi-file-earmark-plus fs-3"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>                          
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>             
                <div class="separator"></div>

                <!-- Nav Tabs -->
                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <div class="d-flex overflow-auto h-40px">
                            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bolder flex-column">
                                <!--begin::Barang-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 " href="<?php echo base_url?>admin/?page=barang">Barang</a>
                                </li>
                                <!--end::Barang-->                                
                                <!--begin::Satuan-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 active" href="<?php echo base_url?>admin/?page=barang/satuan">Satuan</a>
                                </li>
                                <!--end::Satuan-->
                                <!--begin::Kategori-->
                                <li class="nav-item">
                                    <a class="nav-link text-active-primary me-6 " href="<?php echo base_url?>admin/?page=barang/kategori">Kategori</a>
                                </li>
                                <!--end::Kategori-->
                            </ul>
                        </div>
                        
                        <!-- Table -->
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th class="text-start" style="width: 45%;">Nama</th>
                                        <th class="text-center" style="width: 10%;">Aksi</th>
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

<!-- Modal Tambah -->
<div class="modal fade" id="add_modal" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="new-satuan-frm">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Satuan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-md-12">
            <label class="form-label required">Nama Satuan</label>
            <input type="text" name="nama_satuan" class="form-control text-uppercase" autocomplete="off" required>
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

<!-- Modal Edit -->
<div class="modal fade" id="edit_modal" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="edit-satuan-frm">
        <div class="modal-header">
          <h5 class="modal-title">Edit Satuan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <input type="hidden" name="id">
          <div class="col-md-12">
            <label class="form-label required">Nama Satuan</label>
            <input type="text" name="nama_satuan" class="form-control text-uppercase" autocomplete="off" required>
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
    const baseUrl = "../classes/Master.php";

    function load_data() {
        membersDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            stateSave: true,
            ajax: {
                url: baseUrl + "?f=get_data_satuan",
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
            columns: [
                { 
                    data: null,
                    className: "text-center fw-bold",
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { 
                    data: "nama_satuan",
                    render: function(d, t, row) {
                        return `
                            <span class="text-dark fw-bolder text-hover-primary mb-0 fs-7">
                                ${escapeHtml(row.nama_satuan)}
                            </span>
                        `;
                    }
                },
                { 
                    data: null, 
                    orderable: false, 
                    className: "text-center",
                    render: function(d, t, row) {
                        return `
                            <button class="btn btn-icon btn-light-primary btn-sm me-1 edit_data" 
                                    data-id="${row.id}" 
                                    title="Edit Data">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-light-danger btn-sm delete_data" 
                                    data-id="${row.id}" 
                                    data-name="${escapeHtml(row.nama_satuan)}" 
                                    title="Hapus Data">
                                <i class="fa fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            order: [[0, 'desc']], // Urutkan berdasarkan id
            language: {
                oPaginate: {
                    sNext: '<i class="fa fa-angle-right"></i>',
                    sPrevious: '<i class="fa fa-angle-left"></i>',
                    sFirst: '<i class="fa fa-step-backward"></i>',
                    sLast: '<i class="fa fa-step-forward"></i>'
                },
                emptyTable: "Tidak ada data satuan",
                processing: "Memproses...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(difilter dari _MAX_ total data)"
            }
        });

        // Event delegation untuk edit dan delete
        $(document).on('click', '.edit_data', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            editData(id);
        });

        $(document).on('click', '.delete_data', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            _conf(`Yakin hapus permanen satuan <b>${escapeHtml(name)}</b>?`, "deleteData", [id]);
        });
    }

    function editData(id) {
        $.ajax({
            url: baseUrl + "?f=get_single_satuan",
            type: "POST",
            data: { id },
            dataType: 'json',
            success: function(resp) {
                if (resp.status) {
                    $('#edit_modal').find('[name="id"]').val(resp.data.id);
                    $('#edit_modal').find('[name="nama_satuan"]').val(resp.data.nama_satuan);
                    $('#edit_modal').modal('show');
                } else {
                    show_msg("Data tidak ditemukan", "danger");
                }
            },
            error: function() {
                show_msg("Gagal mengambil data", "danger");
            }
        });
    }

    $(document).ready(function() {
        load_data();
        
        // Reset form saat modal tambah dibuka
        $('#add_modal').on('show.bs.modal', function() {
            $('#new-satuan-frm')[0].reset();
        });
        
        $('#add_modal').on('hidden.bs.modal', function() {
            $('#new-satuan-frm')[0].reset();
        });

        $('#add_modal').on('hide.bs.modal', function () {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        $('#edit_modal').on('hide.bs.modal', function () {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        $('#confirm_modal').on('hide.bs.modal', function () {
            // Alihkan fokus ke elemen luar, misal tombol utama
            setTimeout(() => {
                $('#btn_add').focus(); // ganti dengan ID tombol pemicu modal
            }, 10);
        });
        // Untuk form tambah satuan
        $('#new-satuan-frm input[name=nama_satuan]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
        
        // Untuk form edit satuan
        $('#edit-satuan-frm input[name=nama_satuan]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });  

    });

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

    // Tambah data
    $('#new-satuan-frm').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find("button[type=submit]");
        
        $.ajax({
            url: baseUrl + "?f=save_satuan",
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            beforeSend: function() {
                submitBtn.prop("disabled", true).text("Menyimpan...");
            },
            success: function(resp) {
                if (resp?.status === 'success') {
                    form[0].reset();
                    $('#add_modal').modal('hide');
                    reloadDataTable();
                    show_msg("Data berhasil ditambahkan", "success");
                } else {
                    show_msg(resp?.msg || "Data gagal disimpan", "danger");
                }
            },
            error: function() {
                show_msg("Terjadi kesalahan server", "danger");
            },
            complete: function() {
                submitBtn.prop("disabled", false).text("Simpan");
            }
        });
    });

    // Edit data
    $('#edit-satuan-frm').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find("button[type=submit]");
        
        $.ajax({
            url: baseUrl + "?f=update_satuan",
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            beforeSend: function() {
                submitBtn.prop("disabled", true).text("Memperbarui...");
            },
            success: function(resp) {
                if (resp?.status === 'success') {
                    $('#edit_modal').modal('hide');
                    reloadDataTable();
                    show_msg("Data berhasil diperbarui", "success");
                } else {
                    show_msg(resp?.msg || "Update gagal", "danger");
                }
            },
            error: function() {
                show_msg("Terjadi kesalahan server", "danger");
            },
            complete: function() {
                submitBtn.prop("disabled", false).text("Update");
            }
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
            url: baseUrl + "?f=delete_satuan",
            method: "POST",
            data: { id: id },
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

    // Auto uppercase
    $(document).on("input", ".text-uppercase", function() {
        this.value = this.value.toUpperCase();
    });
</script>
