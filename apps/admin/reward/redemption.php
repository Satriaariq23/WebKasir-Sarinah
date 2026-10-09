<div class="content-wrapper py-3 px-3">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Riwayat Penukaran Reward</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Filter Status</label>
                    <select id="filter_status" class="form-select">
                        <option value="">Semua</option>
                        <option value="PENDING">Pending</option>
                        <option value="APPROVED">Approved</option>
                        <option value="USED">Digunakan</option>
                        <option value="EXPIRED">Expired</option>
                        <option value="REJECTED">Ditolak</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" id="filter_from" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" id="filter_to" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="loadRedemptions()">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="table_redemption" class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Reward</th>
                            <th>Poin</th>
                            <th>Skor PM</th>
                            <th>Kode Voucher</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Approve/Reject -->
<div class="modal fade" id="modal_action" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Penukaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="action_id">
                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea id="action_notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="updateStatus('REJECTED')">
                    <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
                <button type="button" class="btn btn-success" onclick="updateStatus('APPROVED')">
                    <i class="bi bi-check-lg me-1"></i>Setujui
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const rewardApi = '../classes/RewardSystem.php';

    function loadRedemptions() {
        const status = $('#filter_status').val();
        const from = $('#filter_from').val();
        const to = $('#filter_to').val();

        // For now, show sample data structure
        // In production, this would load from database
        $.ajax({
            url: rewardApi,
            type: 'POST',
            data: {
                action: 'get_all_redemptions',
                status: status,
                from_date: from,
                to_date: to
            },
            dataType: 'json',
            success: function(response) {
                renderRedemptions(response.data || []);
            },
            error: function() {
                // Show empty state
                renderRedemptions([]);
            }
        });
    }

    function renderRedemptions(data) {
        let html = '';

        if (data.length > 0) {
            data.forEach((r, i) => {
                const statusBadges = {
                    'PENDING': 'bg-warning',
                    'APPROVED': 'bg-success',
                    'USED': 'bg-info',
                    'EXPIRED': 'bg-secondary',
                    'REJECTED': 'bg-danger'
                };

                html += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${r.created_at || '-'}</td>
                    <td>${r.pelanggan_nama || '-'}</td>
                    <td>${r.reward_name || '-'}</td>
                    <td><span class="badge bg-primary">${Number(r.points_used || 0).toLocaleString('id-ID')}</span></td>
                    <td>${Number(r.profile_score || 0).toFixed(2)}</td>
                    <td><code>${r.voucher_code || '-'}</code></td>
                    <td><span class="badge ${statusBadges[r.status] || 'bg-secondary'}">${r.status}</span></td>
                    <td>
                        ${r.status === 'PENDING' ? `
                            <button class="btn btn-sm btn-primary" onclick="showAction(${r.id})">
                                <i class="bi bi-check2-square"></i> Review
                            </button>
                        ` : '-'}
                    </td>
                </tr>
            `;
            });
        } else {
            html = `
            <tr>
                <td colspan="9" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Belum ada riwayat penukaran reward
                </td>
            </tr>
        `;
        }

        $('#table_redemption tbody').html(html);
    }

    function showAction(id) {
        $('#action_id').val(id);
        $('#action_notes').val('');
        $('#modal_action').modal('show');
    }

    function updateStatus(status) {
        const id = $('#action_id').val();
        const notes = $('#action_notes').val();

        $.post(rewardApi, {
            action: 'update_redemption_status',
            id: id,
            status: status,
            notes: notes
        }, function(response) {
            if (response.status === 'success') {
                Swal.fire('Berhasil', response.msg, 'success');
                $('#modal_action').modal('hide');
                loadRedemptions();
            } else {
                Swal.fire('Error', response.msg || 'Terjadi kesalahan', 'error');
            }
        }, 'json');
    }

    $(document).ready(function() {
        loadRedemptions();
    });
</script>