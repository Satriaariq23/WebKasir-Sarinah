<?php
$defaultSalesProfitPercent = $_settings->info('sales_profit_percent');
if (!is_numeric($defaultSalesProfitPercent)) {
    $defaultSalesProfitPercent = 100;
}
$defaultSalesProfitPercent = max(0, min(100, (float)$defaultSalesProfitPercent));
?>
<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Data Salesman</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Tim Sales / Penjualan</span>
                    </h3>                                        
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Tambah Salesman Baru.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if (in_array(current_user_level(), [1, 4], true)): ?>
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
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th class="text-center" style="width: 30%;">Salesman</th> <!-- Lebar diperbesar -->
                                        <th>Telepon</th>
                                        <th class="text-center">Profit Sales</th>
                                        <th>Alamat & Kota</th>
                                        <th>Status</th>
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

<!-- Modal Tambah Salesman -->
<div class="modal fade" id="add_modal" data-bs-backdrop="static" aria-hidden="true">    
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="new-salesman-frm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Salesman</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
            <!-- Foto Profil -->
            <div class="col-md-12 text-center">
                <div class="avatar-upload">
                    <div class="avatar-edit">
                        <input type='file' id="avatarUpload" name="avatar" accept=".png, .jpg, .jpeg" />
                        <label for="avatarUpload">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <div class="avatar-preview">
                        <div id="avatarPreview" style="background-image: url('../assets/media/avatars/blank.png');">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Klik ikon kamera untuk upload foto (max 2MB)</small>
                </div>
            </div>            
            <div class="col-md-12">
                <label class="form-label required">Nama Sales</label>
                <input type="text" name="firstname" class="form-control text-uppercase" autocomplete="off" required>
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
                <input type="text" name="nohp1" class="form-control" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label required">Username</label>
                <input type="text" name="username" class="form-control" autocomplete="off" maxlength="20" required>
            </div>
            <div class="col-md-6">
                <label class="form-label required">Password</label>
                <input type="password" name="password" class="form-control" autocomplete="new-password" required>
            </div>
            <div class="col-md-6">
                <label class="form-label required">Persentase Profit Sales</label>
                <div class="input-group">
                    <input type="number" name="sales_profit_percent" class="form-control" min="0" max="100" step="0.01" value="<?= e($defaultSalesProfitPercent) ?>" required>
                    <span class="input-group-text">%</span>
                </div>
                <small class="text-muted">Dihitung dari (harga jual - New Modal) &times; persentase.</small>
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

<!-- Modal Edit Salesman -->
<div class="modal fade" id="edit_modal" data-bs-backdrop="static" aria-hidden="true">   
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="edit-salesman-frm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Edit Salesman</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
            <input type="hidden" name="id">
            <input type="hidden" name="current_avatar" id="current_avatar">
            
            <!-- Foto Profil -->
            <div class="col-md-12 text-center">
                <div class="avatar-upload">
                    <div class="avatar-edit">
                        <input type='file' id="avatarUploadEdit" name="avatar" accept=".png, .jpg, .jpeg" />
                        <label for="avatarUploadEdit">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <div class="avatar-preview">
                        <div id="avatarPreviewEdit" style="background-image: url('../assets/media/avatars/blank.png');">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Klik ikon kamera untuk upload foto (max 2MB)</small>
                </div>
            </div>

            <div class="col-md-12">
                <label class="form-label required">Nama Sales</label>
                <input type="text" name="firstname" class="form-control text-uppercase" autocomplete="off" required>
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
                <input type="text" name="nohp1" class="form-control" autocomplete="off">
            </div>            
            <div class="col-md-6">
                <label class="form-label required">Username</label>
                <input type="text" name="username" class="form-control" autocomplete="off" maxlength="20" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
            </div>
            <div class="col-md-6">
                <label class="form-label required">Persentase Profit Sales</label>
                <div class="input-group">
                    <input type="number" name="sales_profit_percent" class="form-control" min="0" max="100" step="0.01" required>
                    <span class="input-group-text">%</span>
                </div>
                <small class="text-muted">Nilai ini khusus untuk sales yang sedang diedit.</small>
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


<style>
    .avatar-upload {
        position: relative;
        max-width: 150px;
        margin: 0 auto;
    }
    .avatar-upload .avatar-edit {
        position: absolute;
        right: 10px;
        z-index: 1;
        top: 10px;
    }
    .avatar-upload .avatar-edit input {
        display: none;
    }
    .avatar-upload .avatar-edit label {
        display: inline-block;
        width: 34px;
        height: 34px;
        margin-bottom: 0;
        border-radius: 100%;
        background: #FFFFFF;
        border: 1px solid transparent;
        box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.12);
        cursor: pointer;
        font-weight: normal;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .avatar-upload .avatar-edit label:hover {
        background: #f1f1f1;
        border-color: #d6d6d6;
    }
    .avatar-upload .avatar-preview {
        width: 150px;
        height: 150px;
        position: relative;
        border-radius: 100%;
        border: 6px solid #F8F8F8;
        box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.1);
        margin: 0 auto;
    }
    .avatar-upload .avatar-preview > div {
        width: 100%;
        height: 100%;
        border-radius: 100%;
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
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
            responsive: true,
            ajax: {
                url: baseUrl + "?f=get_data_salesman",
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
                { data: "no", className: "text-center fw-bold" },
                { 
                    data: null,
                    className: "text-start",
                    render: function(data, type, row) {
                        const namaLengkap = `${row.firstname || ''}`.trim();
                        const statusColor = row.status == 1 ? 'bg-success' : 'bg-danger';
                        const statusText = row.status == 1 ? 'Online' : 'Offline';
                        
                        // Handle avatar dengan symbol class
                        const avatarHtml = row.avatar 
                            ? `<div class="symbol symbol-40px symbol-circle me-3">
                                 <img src="${_base_url_}uploads/${row.avatar}" alt="${namaLengkap}" class="img-avatar img-thumbnail p-0 border-2" onerror="this.src='${_base_url_}assets/media/avatars/blank.png'">
                               </div>`
                            : `<div class="symbol symbol-40px symbol-circle me-3">
                                 <img src="${_base_url_}assets/media/avatars/blank.png" alt="${namaLengkap}" class="img-avatar img-thumbnail p-0 border-2">
                               </div>`;
                        
                        return `
                            <div class="d-flex align-items-center">
                                <div class="position-relative">
                                    ${avatarHtml}
                                    <span class="position-absolute bottom-0 end-0 badge ${statusColor} border border-white rounded-circle" style="width: 12px; height: 12px; margin-right: 25px; margin-bottom: 25px;" title="${statusText}"></span>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">${namaLengkap || '-'}</div>
                                    <small class="text-muted">${row.username || ''}</small>
                                </div>
                            </div>
                        `;
                    }
                },
                { data: "nohp1" },
                {
                    data: "sales_profit_percent",
                    className: "text-center",
                    render: data => `${Number(data || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`
                },
                { 
                    data: null,
                    className: "text-start",
                    render: function(data, type, row) {
                        const alamat = row.alamat || '';
                        const kota = row.kota || '';
                        
                        if (!alamat && !kota) {
                            return '<span class="text-muted">-</span>';
                        }
                        
                        return `
                            <div class="d-flex">
                                <i class="fas fa-map-marker-alt text-muted me-2 mt-1"></i>
                                <div class="d-flex flex-column">
                                    ${alamat ? `<div class="fw-medium">${escapeHtml(alamat)}</div>` : ''}
                                    ${kota ? `<div class="text-muted small">${escapeHtml(kota)}</div>` : ''}
                                </div>
                            </div>
                        `;
                    }
                },
                { 
                    data: "status", 
                    className: "text-center",
                    render: function(data) {
                        return data == 1 
                            ? '<span class="badge bg-success">Aktif</span>' 
                            : '<span class="badge bg-danger">Nonaktif</span>';
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
                    <button class="btn btn-icon btn-light-danger btn-sm delete_data" data-id="${row.id}" data-name="${escapeHtml(row.firstname)}" title="Hapus Data.">
                        <i class="fa fa-trash"></i>
                    </button>
                    `
                }
            ],
            order: [[0, 'desc']],
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

        // Update event handler untuk modal edit
        $('#table').on('click', '.edit_data', function() {
            const id = $(this).data('id');
            $.ajax({
                url: baseUrl + "?f=get_single_salesman",
                type: "POST",
                data: { id },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status) {
                        for (let k in resp.data) {
                            if (k === 'status') {
                                $('#edit_modal').find(`[name="${k}"]`).prop('checked', resp.data[k] == 1);
                            } else if (k === 'avatar') {
                                // Set avatar preview
                                if (resp.data[k]) {
                                    $('#avatarPreviewEdit').css('background-image', 'url(../uploads/' + resp.data[k] + ')');
                                    $('#current_avatar').val(resp.data[k]);
                                } else {
                                    $('#avatarPreviewEdit').css('background-image', 'url(../assets/media/avatars/blank.png)');
                                }
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
            _conf(`Yakin hapus permanen Salesman <b>${escapeHtml(name)}</b>?`, "deleteData", [id]);
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

    // Update form submission untuk menggunakan FormData
    $('#new-salesman-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        let formData = new FormData(this);
        
        $.ajax({
            url: baseUrl + "?f=save_salesman",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Menyimpan..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    form.get(0).reset();
                    $('#avatarPreview').css('background-image', 'url(../assets/media/avatars/blank.png)');
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

    // Update form submission untuk edit salesman - TAMBAHKAN RELOAD PAGE
    $('#edit-salesman-frm').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        let formData = new FormData(this);
        
        $.ajax({
            url: baseUrl + "?f=update_salesman",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            beforeSend: () => form.find("button[type=submit]").prop("disabled", true).text("Memperbarui..."),
            success: function(resp) {
                if (resp && resp.status === 'success') {
                    $('#edit_modal').modal('hide');
                    reloadDataTable();
                    show_msg("Data berhasil diperbaharui", "success");
                    
                    // ✅ TAMBAHKAN RELOAD PAGE SETELAH 1.5 DETIK
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                    
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
            url: baseUrl + "?f=delete_salesman",
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

    // Paksa input nama, alamat, kota jadi huruf besar sebelum submit dan saat typing
    $('#new-salesman-frm, #edit-salesman-frm').on('submit', function() {
        $(this).find('input[name=firstname], textarea[name=alamat], input[name=kota]').each(function() {
            $(this).val($(this).val().toUpperCase());
        });
    });
    
    $(document).ready(function() {
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

        // Juga konversi ke uppercase saat user mengetik (real-time)
        // Untuk form tambah salesman
        $('#new-salesman-frm input[name=firstname], #new-salesman-frm textarea[name=alamat], #new-salesman-frm input[name=kota]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
        
        // Untuk form edit salesman
        $('#edit-salesman-frm input[name=firstname], #edit-salesman-frm textarea[name=alamat], #edit-salesman-frm input[name=kota]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
        
        load_data();

        // Reset form saat modal tambah dibuka
        $('#add_modal').on('show.bs.modal', function() {
            $('#new-salesman-frm')[0].reset();
        });
        
        $('#add_modal').on('hidden.bs.modal', function() {
            $('#new-salesman-frm')[0].reset();
        });        
    });

// Function untuk preview avatar
    function readURL(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#' + previewId).css('background-image', 'url('+e.target.result +')');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

// Event untuk upload avatar di modal tambah
    $("#avatarUpload").change(function() {
        readURL(this, "avatarPreview");
    });

// Event untuk upload avatar di modal edit
    $("#avatarUploadEdit").change(function() {
        readURL(this, "avatarPreviewEdit");
    });



</script>
