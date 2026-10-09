<?php

/**
 * REWARD ITEMS PAGE
 * Untuk diinclude dari admin/pengaturan/index.php dengan page parameter
 */

// Session dan auth sudah di-load dari parent file
?>

<div class="content-wrapper py-4 px-3">
    <!-- Header Section -->
    <div class="mb-4">
        <div class="row align-items-center mb-3">
            <div class="col-md-8">
                <div>
                    <h2 style="color: #212529; font-weight: 700; margin-bottom: 6px; font-size: 1.8rem;">
                        <i class="fa me-3" style="color: #f5576c;"></i>Daftar Reward Items
                    </h2>
                    <p style="color: #6c757d; margin: 0; font-size: 0.95rem;">
                        <i class="fa me-2"></i>Kelola semua reward items yang tersedia untuk ditukar dengan point
                    </p>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" onclick="openAddRewardModal()"
                    style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; border-radius: 8px; padding: 12px 24px; font-weight: 600; font-size: 0.95rem; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3); transition: all 0.3s ease;">
                    <i class="fa fa-plus me-2"></i> Tambah Reward Item
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Total Reward</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="totalRewardItems">-</h4>
                            </div>
                            <i class="fa fa-gifts" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Aktif</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="activeRewardItems">-</h4>
                            </div>
                            <i class="fa fa-check-circle" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Tidak Aktif</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="inactiveRewardItems">-</h4>
                            </div>
                            <i class="fa fa-times-circle" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Total Stok</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="totalStokItems">-</h4>
                            </div>
                            <i class="fa fa-boxes" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
        <div class="card-body p-4">
            <!-- Search & Filter -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: 8px 0 0 8px; border: 2px solid #e9ecef;">
                            <i class="fa fa-search text-muted"></i>
                        </span>
                        <input type="text" id="searchReward" class="form-control border-start-0"
                            placeholder="Cari nama reward atau deskripsi..."
                            style="border-radius: 0 8px 8px 0; border: 2px solid #e9ecef; border-left: 0; padding: 10px 12px;">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterStatus" class="form-select" style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px;">
                        <option value="">📊 Semua Status</option>
                        <option value="active">✅ Aktif</option>
                        <option value="inactive">❌ Tidak Aktif</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" onclick="loadRewardItems()"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; font-weight: 600; padding: 10px;">
                        <i class="fa fa-filter me-2"></i> Filter
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover" style="border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <th style="border-radius: 8px 0 0 0; padding: 12px; font-weight: 600;">No</th>
                            <th style="padding: 12px; font-weight: 600;">Nama Reward</th>
                            <th style="padding: 12px; font-weight: 600;">Deskripsi</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600;">Point</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600;">Stok</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600;">Status</th>
                            <th style="border-radius: 0 8px 0 0; padding: 12px; text-align: center; font-weight: 600;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableRewardBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                                <p class="mt-2">Memuat reward items...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formRewardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; padding: 20px;">
                <h5 class="modal-title" id="modalTitle" style="color: white; font-weight: 600; font-size: 1.1rem;">
                    <i class="fa fa-plus me-2"></i>Tambah Reward Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <form id="formReward">
                    <input type="hidden" id="rewardId" value="">

                    <div class="form-group mb-4">
                        <label style="font-weight: 600; color: #333; margin-bottom: 8px;">Nama Reward <span class="text-danger">*</span></label>
                        <input type="text" id="namaReward" class="form-control" placeholder="Masukkan nama reward..."
                            style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px;" required>
                    </div>

                    <div class="form-group mb-4">
                        <label style="font-weight: 600; color: #333; margin-bottom: 8px;">Deskripsi</label>
                        <textarea id="deskripsi" class="form-control" rows="3" placeholder="Deskripsi reward (opsional)"
                            style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px; resize: none;"></textarea>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6 mb-4">
                            <label style="font-weight: 600; color: #333; margin-bottom: 8px;">Point Dibutuhkan <span class="text-danger">*</span></label>
                            <input type="number" id="poinDibutuhkan" class="form-control" value="0" min="1"
                                placeholder="Masukkan jumlah point" style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px;" required>
                        </div>
                        <div class="form-group col-md-6 mb-4">
                            <label style="font-weight: 600; color: #333; margin-bottom: 8px;">Stok <span class="text-danger">*</span></label>
                            <input type="number" id="stok" class="form-control" value="0" min="0"
                                placeholder="Masukkan jumlah stok" style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px;" required>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label style="font-weight: 600; color: #333; margin-bottom: 8px;">Status</label>
                        <select id="statusReward" class="form-select" style="border-radius: 8px; border: 2px solid #e9ecef; padding: 10px 12px;">
                            <option value="active" selected>✅ Aktif</option>
                            <option value="inactive">❌ Tidak Aktif</option>
                        </select>
                    </div>

                    <div id="alertMessage" class="alert" style="display:none; border-radius: 8px;"></div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 15px 25px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 6px; padding: 8px 20px;">
                    Batal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveRewardItem()"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 6px; padding: 8px 20px; font-weight: 600;">
                    <i class="fa fa-save me-2"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .table-hover tbody tr:hover {
        background-color: #f8f9ff !important;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    .table tbody td {
        vertical-align: middle;
        padding: 14px 12px;
        border: none;
        border-bottom: 1px solid #e9ecef;
    }

    .table thead th {
        border: none;
        vertical-align: middle;
    }

    .badge-custom {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .btn-action {
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    }
</style>

<script>
    const BASE_URL = '<?= base_url ?>';
    let rewardItemsCache = [];

    function loadRewardItems() {
        const search = $('#searchReward').val();
        const status = $('#filterStatus').val();

        $.ajax({
            url: `pengaturan/api.php?action=get_reward_items&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    rewardItemsCache = res.data || [];
                    displayRewardItems(res.data);
                    updateStats(res.data);
                } else {
                    alert_toast(res.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert_toast('Gagal memuat reward items', 'danger');
            }
        });
    }

    function updateStats(data) {
        if (!data || data.length === 0) {
            $('#totalRewardItems').text('0');
            $('#activeRewardItems').text('0');
            $('#inactiveRewardItems').text('0');
            $('#totalStokItems').text('0');
            return;
        }

        const totalItems = data.length;
        const activeItems = data.filter(item => item.status === 'active').length;
        const inactiveItems = totalItems - activeItems;
        const totalStok = data.reduce((sum, item) => sum + parseInt(item.stok || 0), 0);

        $('#totalRewardItems').text(totalItems);
        $('#activeRewardItems').text(activeItems);
        $('#inactiveRewardItems').text(inactiveItems);
        $('#totalStokItems').text(totalStok);
    }

    function displayRewardItems(data) {
        const tbody = $('#tableRewardBody');
        if (!data || data.length === 0) {
            tbody.html(`<tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <i class="fa fa-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-3">Tidak ada reward items</p>
                </td>
            </tr>`);
            return;
        }

        let html = '';
        data.forEach((item, idx) => {
            const statusBadge = item.status === 'active' ?
                '<span class="badge-custom" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); color: white;">✅ Aktif</span>' :
                '<span class="badge-custom" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">❌ Tidak Aktif</span>';

            const stokBadge = item.stok > 10 ?
                `<span style="color: #28a745; font-weight: 600;">${item.stok}</span>` :
                item.stok > 0 ?
                `<span style="color: #ff9800; font-weight: 600;">${item.stok}</span>` :
                `<span style="color: #dc3545; font-weight: 600;">0</span>`;

            html += `<tr style="transition: all 0.3s ease;">
                <td style="font-weight: 600; color: #667eea;">${idx + 1}</td>
                <td>
                    <div style="font-weight: 600; color: #212529; font-size: 0.95rem;">
                        <i class="fa" style="color: #ffc107; margin-right: 6px;"></i>${item.nama_reward}
                    </div>
                </td>
                <td>
                    <small style="color: #6c757d; line-height: 1.4;">
                        ${item.deskripsi ? item.deskripsi.substring(0, 50) + (item.deskripsi.length > 50 ? '...' : '') : '-'}
                    </small>
                </td>
                <td style="text-align: center;">
                    <span class="badge-custom" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                         ${item.poin_dibutuhkan}
                    </span>
                </td>
                <td style="text-align: center;">
                    ${stokBadge}
                </td>
                <td style="text-align: center;">
                    ${statusBadge}
                </td>
                <td style="text-align: center; gap: 6px; display: flex; justify-content: center;">
                    <button class="btn btn-warning btn-action btn-sm" onclick="editRewardItem(${item.id})" title="Edit">
                        <i class="fa fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-action btn-sm" onclick="deleteRewardItem(${item.id})" title="Hapus">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        tbody.html(html);
    }

    function openAddRewardModal() {
        resetForm();
        $('#modalTitle').html('<i class="fa fa-plus me-2"></i>Tambah Reward Item');
        const modal = new bootstrap.Modal(document.getElementById('formRewardModal'));
        modal.show();
    }

    function editRewardItem(id) {
        const tableBody = $('#tableRewardBody');
        const rows = tableBody.find('tr');

        rows.each(function() {
            if ($(this).html().includes(`onclick="editRewardItem(${id})"`)) {
                const cells = $(this).find('td');
                const namaReward = cells.eq(1).text().trim().replace('⭐', '').trim();
                const deskripsi = cells.eq(2).text().trim();
                const poin = cells.eq(3).text().trim().replace('💎', '').trim();
                const stok = cells.eq(4).text().trim();

                $('#rewardId').val(id);
                $('#namaReward').val(namaReward);
                $('#deskripsi').val(deskripsi === '-' ? '' : deskripsi);
                $('#poinDibutuhkan').val(poin);
                $('#stok').val(stok);
                $('#modalTitle').html('<i class="fa fa-edit me-2"></i>Edit Reward Item');

                const modal = new bootstrap.Modal(document.getElementById('formRewardModal'));
                modal.show();
            }
        });
    }

    function editRewardItem(id) {
        const item = rewardItemsCache.find(reward => Number(reward.id) === Number(id));
        if (!item) {
            alert_toast('Data reward tidak ditemukan, silakan refresh halaman', 'danger');
            return;
        }

        $('#rewardId').val(item.id);
        $('#namaReward').val(item.nama_reward || '');
        $('#deskripsi').val(item.deskripsi || '');
        $('#poinDibutuhkan').val(item.poin_dibutuhkan || 0);
        $('#stok').val(item.stok || 0);
        $('#statusReward').val(item.status === 'inactive' ? 'inactive' : 'active');
        $('#modalTitle').html('<i class="fa fa-edit me-2"></i>Edit Reward Item');

        const modal = new bootstrap.Modal(document.getElementById('formRewardModal'));
        modal.show();
    }

    function saveRewardItem() {
        const id = $('#rewardId').val();
        const data = {
            action: id ? 'update_reward_item' : 'add_reward_item',
            nama_hadiah: $('#namaReward').val(),
            deskripsi: $('#deskripsi').val(),
            poin_diperlukan: $('#poinDibutuhkan').val(),
            qty: $('#stok').val(),
            status: $('#statusReward').val()
        };

        if (id) {
            data.id = id;
        }

        $.ajax({
            url: 'pengaturan/api.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    alert_toast('✅ Berhasil menyimpan reward', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('formRewardModal')).hide();
                    resetForm();
                    loadRewardItems();
                } else {
                    alert_toast(res.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                alert_toast('Terjadi kesalahan', 'danger');
            }
        });
    }

    function deleteRewardItem(id) {
        if (confirm('Apakah Anda yakin ingin menghapus reward ini?')) {
            $.ajax({
                url: 'pengaturan/api.php',
                type: 'POST',
                data: {
                    action: 'delete_reward_item',
                    id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        alert_toast('✅ Reward berhasil dihapus', 'success');
                        loadRewardItems();
                    } else {
                        alert_toast(res.message, 'danger');
                    }
                }
            });
        }
    }

    function resetForm() {
        $('#rewardId').val('');
        $('#formReward')[0].reset();
        $('#statusReward').val('active');
    }

    // Initial load
    $(document).ready(function() {
        loadRewardItems();
    });
</script>