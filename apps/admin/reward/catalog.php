<div class="content-wrapper py-3 px-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-gift me-2"></i>Katalog Reward</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal_reward">
                <i class="bi bi-plus-lg me-1"></i>Tambah Reward
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="table_catalog" class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Nama Reward</th>
                            <th>Tipe</th>
                            <th>Nilai</th>
                            <th>Poin Dibutuhkan</th>
                            <th>Min Skor</th>
                            <th>Stok</th>
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

<!-- Modal Add/Edit Reward -->
<div class="modal fade" id="modal_reward" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="form_reward">
                <input type="hidden" name="id" id="reward_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal_title">Tambah Reward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nama Reward <span class="text-danger">*</span></label>
                            <input type="text" name="reward_name" id="reward_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipe Reward</label>
                            <select name="reward_type" id="reward_type" class="form-select">
                                <option value="DISCOUNT">Diskon (%)</option>
                                <option value="VOUCHER">Voucher (Rp)</option>
                                <option value="CASHBACK">Cashback (%)</option>
                                <option value="PRODUCT">Produk Gratis</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Reward <span class="text-danger">*</span></label>
                            <input type="number" name="reward_value" id="reward_value" class="form-control" step="0.01" required>
                            <small class="text-muted">Persen untuk diskon/cashback, nominal untuk voucher</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Poin Dibutuhkan <span class="text-danger">*</span></label>
                            <input type="number" name="points_required" id="points_required" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Skor Profile</label>
                            <input type="number" name="min_score" id="min_score" class="form-control" step="0.01" value="0">
                            <small class="text-muted">0-5, kosongkan jika tidak ada syarat</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" name="stock" id="stock" class="form-control" value="-1">
                            <small class="text-muted">-1 untuk unlimited</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Berlaku Dari</label>
                            <input type="date" name="valid_from" id="valid_from" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Berlaku Sampai</label>
                            <input type="date" name="valid_until" id="valid_until" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const rewardApi = '../classes/RewardSystem.php';

    // Load catalog
    function loadCatalog() {
        $.get(rewardApi + '?f=get_reward_catalog', function(response) {
            if (response.status === 'success') {
                let html = '';
                response.data.forEach((r, i) => {
                    const typeLabels = {
                        'DISCOUNT': '<span class="badge bg-info">Diskon</span>',
                        'VOUCHER': '<span class="badge bg-success">Voucher</span>',
                        'CASHBACK': '<span class="badge bg-warning">Cashback</span>',
                        'PRODUCT': '<span class="badge bg-primary">Produk</span>'
                    };
                    const valueDisplay = ['DISCOUNT', 'CASHBACK'].includes(r.reward_type) ?
                        r.reward_value + '%' :
                        'Rp ' + Number(r.reward_value).toLocaleString('id-ID');

                    html += `
                    <tr>
                        <td>${i + 1}</td>
                        <td><strong>${r.reward_name}</strong><br><small class="text-muted">${r.description || ''}</small></td>
                        <td>${typeLabels[r.reward_type] || r.reward_type}</td>
                        <td>${valueDisplay}</td>
                        <td><span class="badge bg-primary">${Number(r.points_required).toLocaleString('id-ID')}</span></td>
                        <td>${r.min_score}</td>
                        <td>${r.stock == -1 ? '∞' : r.stock}</td>
                        <td>${r.is_active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>'}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editReward(${JSON.stringify(r).replace(/"/g, '&quot;')})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteReward(${r.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                });
                if (!html) {
                    html = '<tr><td colspan="9" class="text-center text-muted">Belum ada data reward</td></tr>';
                }
                $('#table_catalog tbody').html(html);
            }
        }, 'json');
    }

    // Edit reward
    function editReward(data) {
        $('#modal_title').text('Edit Reward');
        $('#reward_id').val(data.id);
        $('#reward_name').val(data.reward_name);
        $('#reward_type').val(data.reward_type);
        $('#reward_value').val(data.reward_value);
        $('#points_required').val(data.points_required);
        $('#min_score').val(data.min_score);
        $('#stock').val(data.stock);
        $('#valid_from').val(data.valid_from);
        $('#valid_until').val(data.valid_until);
        $('#description').val(data.description);
        $('#is_active').prop('checked', data.is_active == 1);
        $('#modal_reward').modal('show');
    }

    // Delete reward
    function deleteReward(id) {
        Swal.fire({
            title: 'Hapus Reward?',
            text: 'Reward akan dinonaktifkan',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(rewardApi, {
                    action: 'save_reward',
                    id: id,
                    is_active: 0
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Berhasil', 'Reward berhasil dinonaktifkan', 'success');
                        loadCatalog();
                    }
                }, 'json');
            }
        });
    }

    // Form submit
    $('#form_reward').on('submit', function(e) {
        e.preventDefault();

        // Explicit form data construction to avoid checkbox serialization issues
        const formData = {
            action: 'save_reward',
            id: $('#reward_id').val(),
            reward_name: $('#reward_name').val(),
            reward_type: $('#reward_type').val(),
            reward_value: $('#reward_value').val(),
            points_required: $('#points_required').val(),
            min_score: $('#min_score').val(),
            stock: $('#stock').val(),
            valid_from: $('#valid_from').val(),
            valid_until: $('#valid_until').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0,  // Explicit 1 or 0
            description: $('#description').val()
        };

        console.log('Form data to send:', formData);  // Debug log
        
        $.post(rewardApi, formData, function(response) {
            console.log('Response:', response);  // Debug log
            if (response.status === 'success') {
                Swal.fire('Berhasil', response.msg, 'success');
                $('#modal_reward').modal('hide');
                $('#form_reward')[0].reset();
                $('#reward_id').val('');
                loadCatalog();
            } else {
                Swal.fire('Error', response.msg, 'error');
            }
        }, 'json');
    });

    // Reset form on modal close
    $('#modal_reward').on('hidden.bs.modal', function() {
        $('#form_reward')[0].reset();
        $('#reward_id').val('');
        $('#modal_title').text('Tambah Reward');
    });

    // Init
    $(document).ready(function() {
        loadCatalog();
    });
</script>