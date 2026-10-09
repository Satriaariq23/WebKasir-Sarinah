<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Log Aktivitas</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Riwayat aktivitas pengguna dalam sistem</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Hapus semua log.">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if ((int)($_settings->userdata('level_id') ?? 0) === 1): ?>
                                <div class="form-group col-auto pt-5">
                                    <a class="btn btn-icon btn-danger btn-sm" href="javascript:void(0)" id="btn_clear_logs" title="Hapus Semua Log"><i class="bi bi-trash fs-3"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter Tanggal Dari</label>
                                <input type="date" id="filter-date-from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filter Tanggal Sampai</label>
                                <input type="date" id="filter-date-to" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filter Aksi</label>
                                <select id="filter-aksi" class="form-control">
                                    <option value="">-- Semua Aksi --</option>
                                    <option value="INSERT">Tambah Data</option>
                                    <option value="UPDATE">Edit Data</option>
                                    <option value="DELETE">Hapus Data</option>
                                    <option value="LOGIN">Login</option>
                                    <option value="LOGOUT">Logout</option>
                                    <option value="MINUS_AUTH">Otorisasi Qty Minus</option>
                                    <option value="OTHER">Aktivitas Lain</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-white">.</label>
                                <button class="btn btn-primary w-100" onclick="load_logs()"><i class="bi bi-search"></i> Filter</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th style="width: 12%;">Tanggal & Waktu</th>
                                        <th style="width: 12%;">User</th>
                                        <th class="text-center" style="width: 10%;">Aksi</th>
                                        <th style="width: 15%;">Tabel</th>
                                        <th class="text-center">Record ID</th>
                                        <th>Keterangan</th>
                                        <th style="width: 10%;">IP Address</th>
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

<!-- Modal Detail Log -->
<div class="modal fade" id="detail_modal" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Log Aktivitas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">User</label>
                        <p id="detail_user" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Waktu</label>
                        <p id="detail_time" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Aksi</label>
                        <p id="detail_aksi" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tabel</label>
                        <p id="detail_tabel" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Record ID</label>
                        <p id="detail_record_id" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">IP Address</label>
                        <p id="detail_ip" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Keterangan</label>
                        <p id="detail_keterangan" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">User Agent</label>
                        <p id="detail_user_agent" class="form-control-plaintext" style="font-size: 0.85rem; word-break: break-all;"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let table;

        // Load logs table
        function load_logs() {
            if (table) table.destroy();

            const date_from = $('#filter-date-from').val();
            const date_to = $('#filter-date-to').val();
            const aksi = $('#filter-aksi').val();

            table = $('#table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "<?= base_url ?>classes/Master.php?f=get_activity_logs",
                    type: "POST",
                    data: {
                        date_from: date_from,
                        date_to: date_to,
                        aksi: aksi
                    }
                },
                columns: [{
                        data: 'no',
                        className: 'text-center'
                    },
                    {
                        data: 'created_at'
                    },
                    {
                        data: 'firstname'
                    },
                    {
                        data: 'aksi_badge',
                        defaultContent: '<span class="badge bg-secondary">Aktivitas</span>',
                        className: 'text-center'
                    },
                    {
                        data: 'tabel'
                    },
                    {
                        data: 'record_id',
                        className: 'text-center'
                    },
                    {
                        data: 'keterangan'
                    },
                    {
                        data: 'ip_address'
                    }
                ],
                columnDefs: [{
                    targets: [0, 3, 7],
                    searchable: false
                }],
                order: [
                    [1, 'desc']
                ]
            });
        }

        load_logs();

        // Filter button
        window.load_logs = function() {
            load_logs();
        };

        // Clear all logs
        $('#btn_clear_logs').on('click', function() {
            if (confirm('Yakin hapus SEMUA log aktivitas? Tindakan ini tidak dapat dibatalkan!')) {
                $.ajax({
                    url: "<?= base_url ?>classes/Master.php?f=clear_all_logs",
                    method: 'POST',
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status == 'success') {
                            alert_toast(resp.msg, 'success');
                            load_logs();
                        } else {
                            alert_toast(resp.msg, 'danger');
                        }
                    },
                    error: function() {
                        alert_toast('Error menghapus log', 'danger');
                    }
                });
            }
        });

        // View detail
        window.view_detail = function(id) {
            $.ajax({
                url: "<?= base_url ?>classes/Master.php?f=get_log_detail",
                method: 'POST',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(log) {
                    $('#detail_user').text(log.firstname || '-');
                    $('#detail_time').text(log.created_at || '-');
                    $('#detail_aksi').html('<span class="badge bg-' + log.aksi_color + '">' + (log.aksi_label || log.aksi || '-') + '</span>');
                    $('#detail_tabel').text(log.tabel || '-');
                    $('#detail_record_id').text(log.record_id || '-');
                    $('#detail_ip').text(log.ip_address || '-');
                    $('#detail_keterangan').text(log.keterangan || '-');
                    $('#detail_user_agent').text(log.user_agent || '-');
                    $('#detail_modal').modal('show');
                }
            });
        };
    });
</script>
