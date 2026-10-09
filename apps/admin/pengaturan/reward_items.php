<?php
include '../inc/sess_auth.php';
include $_SERVER['DOCUMENT_ROOT'] . '/sarinah-apps/apps/classes/PointRewardSystem.php';

$reward = new PointRewardSystem();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-gift"></i> Pengaturan Reward Items
                    </h5>
                    <button class="btn btn-light btn-sm" data-toggle="modal" data-target="#formRewardModal">
                        <i class="fas fa-plus"></i> Tambah Reward
                    </button>
                </div>
                <div class="card-body">
                    <!-- Search & Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" id="searchReward" class="form-control form-control-sm"
                                placeholder="Cari reward...">
                        </div>
                        <div class="col-md-3">
                            <select id="filterStatus" class="form-control form-control-sm">
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Tidak Aktif</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-sm btn-block" onclick="loadRewardItems()">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Reward</th>
                                    <th>Deskripsi</th>
                                    <th>Poin Dibutuhkan</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableRewardBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formRewardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Reward Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formReward">
                    <input type="hidden" id="rewardId" value="">

                    <div class="form-group">
                        <label>Nama Reward <span class="text-danger">*</span></label>
                        <input type="text" id="namaReward" class="form-control form-control-sm" required>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea id="deskripsi" class="form-control form-control-sm" rows="3"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Poin Dibutuhkan <span class="text-danger">*</span></label>
                            <input type="number" id="poinDibutuhkan" class="form-control form-control-sm"
                                value="0" min="1" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Stok <span class="text-danger">*</span></label>
                            <input type="number" id="stok" class="form-control form-control-sm"
                                value="0" min="0" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select id="statusReward" class="form-control form-control-sm">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                        </select>
                    </div>

                    <div id="alertMessage" class="alert" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveRewardItem()">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const ROOT_URL = '<?= base_url ?>';

    function loadRewardItems() {
        const search = document.getElementById('searchReward').value;
        const status = document.getElementById('filterStatus').value;

        fetch(`<?= base_url ?>classes/PointRewardSystem.php?f=get_reward_items&search=${search}&status=${status}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    displayRewardItems(res.data);
                } else {
                    showAlert('error', res.message);
                }
            })
            .catch(e => console.error('Fetch error:', e));
    }

    function displayRewardItems(data) {
        const tbody = document.getElementById('tableRewardBody');
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Tidak ada data</td></tr>';
            return;
        }

        let html = '';
        data.forEach((item, idx) => {
            const statusBadge = item.status === 'active' ?
                '<span class="badge badge-success">Aktif</span>' :
                '<span class="badge badge-warning">Tidak Aktif</span>';

            html += `<tr>
            <td>${idx + 1}</td>
            <td><strong>${item.nama_reward}</strong></td>
            <td>${item.deskripsi || '-'}</td>
            <td><span class="badge badge-info">${item.poin_dibutuhkan}</span></td>
            <td>${item.stok}</td>
            <td>${statusBadge}</td>
            <td>
                <button class="btn btn-warning btn-xs" onclick="editRewardItem(${item.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-xs" onclick="deleteRewardItem(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
        });

        tbody.innerHTML = html;
    }

    function editRewardItem(id) {
        // Fetch and populate form
        const tableBody = document.getElementById('tableRewardBody');
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            if (row.querySelector(`button[onclick*="${id}"]`)) {
                const cells = row.querySelectorAll('td');
                const namaReward = cells[1].textContent.trim();
                const deskripsi = cells[2].textContent.trim();
                const poin = cells[3].textContent.trim();
                const stok = cells[4].textContent.trim();

                document.getElementById('rewardId').value = id;
                document.getElementById('namaReward').value = namaReward;
                document.getElementById('deskripsi').value = deskripsi === '-' ? '' : deskripsi;
                document.getElementById('poinDibutuhkan').value = poin;
                document.getElementById('stok').value = stok;
                document.getElementById('modalTitle').textContent = 'Edit Reward Item';

                $('#formRewardModal').modal('show');
            }
        });
    }

    function saveRewardItem() {
        const id = document.getElementById('rewardId').value;
        const data = {
            f: id ? 'update_reward_item' : 'add_reward_item',
            nama_reward: document.getElementById('namaReward').value,
            deskripsi: document.getElementById('deskripsi').value,
            poin_dibutuhkan: document.getElementById('poinDibutuhkan').value,
            stok: document.getElementById('stok').value,
            status: document.getElementById('statusReward').value
        };

        if (id) {
            data.id = id;
        }

        fetch(`<?= base_url ?>classes/PointRewardSystem.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data)
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    showAlert('success', res.message);
                    $('#formRewardModal').modal('hide');
                    resetForm();
                    loadRewardItems();
                } else {
                    showAlert('error', res.message);
                }
            })
            .catch(e => {
                console.error(e);
                showAlert('error', 'Terjadi kesalahan');
            });
    }

    function deleteRewardItem(id) {
        Swal.fire({
            title: 'Hapus Reward?',
            text: 'Reward ini akan dihapus dari sistem',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Hapus'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`<?= base_url ?>classes/PointRewardSystem.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `f=delete_reward_item&id=${id}`
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            showAlert('success', 'Reward berhasil dihapus');
                            loadRewardItems();
                        } else {
                            showAlert('error', res.message);
                        }
                    });
            }
        });
    }

    function resetForm() {
        document.getElementById('rewardId').value = '';
        document.getElementById('formReward').reset();
        document.getElementById('modalTitle').textContent = 'Tambah Reward Item';
    }

    function showAlert(type, message) {
        Swal.fire({
            icon: type,
            title: type === 'success' ? 'Berhasil' : 'Error',
            text: message,
            timer: 2000
        });
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        loadRewardItems();
    });
</script>

<?php include '../inc/footer.php'; ?>
</body>

</html>