<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Manajemen User</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Kelola pengguna dan akun aplikasi</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Tambah User Baru.">
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
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th class="text-center" style="width: 15%;">Username</th>
                                        <th style="width: 20%;">Nama</th>
                                        <th>Telepon</th>
                                        <th>Level</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 12%;">Aksi</th>
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

<!-- Modal Tambah User -->
<div class="modal fade" id="add_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="new-user-frm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label required">Username</label>
                        <input type="text" name="username" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Password</label>
                        <input type="password" name="password" class="form-control" id="new_password" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Konfirmasi Password</label>
                        <input type="password" name="cpassword" class="form-control" id="new_cpassword" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Nama Lengkap</label>
                        <input type="text" name="firstname" class="form-control text-uppercase" autocomplete="off" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="nohp1" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" class="form-control text-uppercase" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control text-uppercase" rows="2"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Level User</label>
                        <select name="level_id" class="form-control" required>
                            <option value="">-- Pilih Level --</option>
                            <option value="1">Admin</option>
                            <option value="2">Kasir</option>
                            <option value="3">Salesman</option>
                            <option value="4">Supervisor</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa fa-chevron-left"></i> Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Simpan <i class="fa fa-chevron-right"></i></span>
                        <span class="indicator-progress">Menyimpan...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="edit_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="edit-user-frm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit_user_id">
                    <div class="col-md-12">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" id="edit_username" disabled>
                        <small class="text-muted">Username tidak dapat diubah</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Nama Lengkap</label>
                        <input type="text" name="firstname" class="form-control text-uppercase" autocomplete="off" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="nohp1" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota" class="form-control text-uppercase" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control text-uppercase" rows="2"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Level User</label>
                        <select name="level_id" class="form-control" required>
                            <option value="1">Admin</option>
                            <option value="2">Kasir</option>
                            <option value="3">Salesman</option>
                            <option value="4">Supervisor</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa fa-chevron-left"></i> Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Simpan <i class="fa fa-chevron-right"></i></span>
                        <span class="indicator-progress">Menyimpan...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ubah Password -->
<div class="modal fade" id="password_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="change-password-frm">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="pwd_user_id">
                    <div class="col-md-12">
                        <label class="form-label required">Password Baru</label>
                        <input type="password" name="password" class="form-control" id="new_pwd" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Konfirmasi Password</label>
                        <input type="password" name="cpassword" class="form-control" id="confirm_pwd" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa fa-chevron-left"></i> Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Ubah <i class="fa fa-chevron-right"></i></span>
                        <span class="indicator-progress">Mengubah...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let table;

        // Load user table
        function load_data() {
            if (table) table.destroy();

            table = $('#table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "<?= base_url ?>classes/Master.php?f=get_users_datatable",
                    type: "POST"
                },
                columns: [{
                        data: 'no',
                        className: 'text-center'
                    },
                    {
                        data: 'username',
                        className: 'text-center'
                    },
                    {
                        data: 'firstname'
                    },
                    {
                        data: 'nohp1'
                    },
                    {
                        data: 'level_name'
                    },
                    {
                        data: 'status_badge'
                    },
                    {
                        data: 'actions',
                        className: 'text-center',
                        orderable: false
                    }
                ],
                columnDefs: [{
                    targets: [0, 1, 4, 5, 6],
                    searchable: false
                }]
            });
        }

        load_data();

        // Add user form
        $('#new-user-frm').on('submit', function(e) {
            e.preventDefault();

            let password = $('[name="password"]').val();
            let cpassword = $('[name="cpassword"]').val();

            if (password.length < 6) {
                alert_toast('Password minimal 6 karakter', 'warning');
                return false;
            }

            if (password !== cpassword) {
                alert_toast('Konfirmasi password tidak cocok', 'warning');
                return false;
            }

            $.ajax({
                url: "<?= base_url ?>classes/Master.php?f=save_user",
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(resp) {
                    if (resp.status == 'success') {
                        alert_toast(resp.msg, 'success');
                        $('#add_modal').modal('hide');
                        $('#new-user-frm')[0].reset();
                        load_data();
                    } else {
                        alert_toast(resp.msg, 'danger');
                    }
                },
                error: function() {
                    alert_toast('Error menyimpan data', 'danger');
                }
            });
        });

        // Edit user form
        $('#edit-user-frm').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: "<?= base_url ?>classes/Master.php?f=update_user",
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(resp) {
                    if (resp.status == 'success') {
                        alert_toast(resp.msg, 'success');
                        $('#edit_modal').modal('hide');
                        load_data();
                    } else {
                        alert_toast(resp.msg, 'danger');
                    }
                },
                error: function() {
                    alert_toast('Error menyimpan data', 'danger');
                }
            });
        });

        // Change password form
        $('#change-password-frm').on('submit', function(e) {
            e.preventDefault();

            let password = $('[name="password"]', this).val();
            let cpassword = $('[name="cpassword"]', this).val();

            if (password.length < 6) {
                alert_toast('Password minimal 6 karakter', 'warning');
                return false;
            }

            if (password !== cpassword) {
                alert_toast('Konfirmasi password tidak cocok', 'warning');
                return false;
            }

            // Show loading indicator
            let $btn = $('[type="submit"]', this);
            let originalHTML = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Mengubah...');

            $.ajax({
                url: "<?= base_url ?>classes/Master.php?f=change_password",
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(resp) {
                    if (resp.status == 'success') {
                        alert_toast(resp.msg, 'success');
                        $('#password_modal').modal('hide');
                        $('#change-password-frm')[0].reset();
                        // Reload data table after password change
                        setTimeout(() => {
                            load_data();
                        }, 500);
                    } else {
                        alert_toast(resp.msg, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error:', error);
                    alert_toast('Error mengubah password: ' + (xhr.responseText || error), 'danger');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHTML);
                }
            });
        });

        // Global edit function
        window.edit_user = function(id) {
            $.ajax({
                url: "<?= base_url ?>classes/Master.php?f=get_user",
                method: 'POST',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(user) {
                    $('#edit_user_id').val(user.id);
                    $('#edit_username').val(user.username);
                    $('[name="firstname"]', '#edit-user-frm').val(user.firstname);
                    $('[name="nohp1"]', '#edit-user-frm').val(user.nohp1);
                    $('[name="kota"]', '#edit-user-frm').val(user.kota);
                    $('[name="alamat"]', '#edit-user-frm').val(user.alamat);
                    $('[name="level_id"]', '#edit-user-frm').val(user.level_id);
                    $('[name="status"]', '#edit-user-frm').val(user.status);
                    $('#edit_modal').modal('show');
                }
            });
        };

        // Global change password function
        window.change_password = function(id, username) {
            $('#pwd_user_id').val(id);
            $('#password_modal').modal('show');
        };

        // Global delete function
        window.delete_user = function(id, username) {
            if (confirm('Yakin hapus user ' + username + '?')) {
                $.ajax({
                    url: "<?= base_url ?>classes/Master.php?f=delete_user",
                    method: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status == 'success') {
                            alert_toast(resp.msg, 'success');
                            load_data();
                        } else {
                            alert_toast(resp.msg, 'danger');
                        }
                    }
                });
            }
        };

        // Prevent modal from closing when form has errors
        $('#add_modal').on('hidden.bs.modal', function() {
            $('#new-user-frm')[0].reset();
        });
    });
</script>
