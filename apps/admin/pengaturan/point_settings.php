<?php
include '../inc/sess_auth.php';
include $_SERVER['DOCUMENT_ROOT'] . '/sarinah-apps/apps/classes/PointRewardSystem.php';

// Check permission
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access Denied');
}

$reward = new PointRewardSystem();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Pengaturan Point System - Sarinah Admin</title>
    <link rel="stylesheet" href="<?= base_url ?>../theme/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url ?>theme/plugins/jquery-ui/jquery-ui.min.css">
    <style>
        .setting-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .setting-card .key {
            font-weight: bold;
            color: #495057;
        }

        .setting-card .value-edit {
            display: none;
        }

        .setting-card.editing {
            background: #e7f3ff;
            border-left-color: #0056b3;
        }

        .setting-card.editing .value-edit {
            display: block;
        }

        .setting-card.editing .value-display {
            display: none;
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../inc/header.php'; ?>
    <?php include '../inc/topbarnav.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cog"></i> Pengaturan Point System
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="settingsContainer">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Loading settings...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h6>
                    </div>
                    <div class="card-body small">
                        <p><strong>min_belanja_point:</strong> Minimum nilai pembelian untuk mendapatkan poin (Rp)</p>
                        <p><strong>point_per_belanja:</strong> Jumlah poin untuk setiap Rp 1.000</p>
                        <p><strong>frekuensi_point:</strong> Frekuensi pemberian poin (daily, weekly, monthly)</p>
                        <p><strong>max_poin_per_transaksi:</strong> Batas maksimal poin per transaksi</p>
                        <p><strong>point_nilai_tukar:</strong> Berapa poin = 1 barang reward</p>
                        <p><strong>status_poin_system:</strong> Status aktivasi sistem poin</p>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Total Members</small>
                            <h4 id="totalMembers">-</h4>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Total Points in System</small>
                            <h4 id="totalPoints">-</h4>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Redeemed Rewards</small>
                            <h4 id="rewardRedeemed">-</h4>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Active Rewards</small>
                            <h4 id="activeRewards">-</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= base_url ?>theme/plugins/jquery/jquery.min.js"></script>
    <script src="<?= base_url ?>theme/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url ?>theme/plugins/sweetalert2/sweetalert2.min.js"></script>

    <script>
        const ROOT_URL = '<?= base_url ?>';

        function loadPointSettings() {
            fetch(`${ROOT_URL}classes/PointRewardSystem.php?f=get_point_settings`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        displaySettings(res.data);
                    } else {
                        showAlert('error', res.message);
                    }
                })
                .catch(e => console.error('Fetch error:', e));
        }

        function displaySettings(settings) {
            const container = document.getElementById('settingsContainer');
            let html = '';

            for (const [key, setting] of Object.entries(settings)) {
                const value = setting.setting_value;
                const deskripsi = setting.deskripsi;

                html += `<div class="setting-card" id="setting-${key}">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="key">${key}</div>
                    <small class="text-muted">${deskripsi}</small>
                </div>
                <div class="ml-2">
                    <div class="value-display">
                        <strong class="h5">${value}</strong>
                        <button class="btn btn-sm btn-outline-primary ml-2" onclick="editSetting('${key}', '${value}')">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <div class="value-edit">
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" class="form-control" id="input-${key}" value="${value}">
                            <div class="input-group-append">
                                <button class="btn btn-success" type="button" onclick="saveSetting('${key}')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-secondary" type="button" onclick="cancelEdit('${key}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
            }

            container.innerHTML = html;
        }

        function editSetting(key, value) {
            const card = document.getElementById(`setting-${key}`);
            card.classList.add('editing');
            document.getElementById(`input-${key}`).focus();
        }

        function cancelEdit(key) {
            const card = document.getElementById(`setting-${key}`);
            card.classList.remove('editing');
        }

        function saveSetting(key) {
            const newValue = document.getElementById(`input-${key}`).value;

            fetch(`${ROOT_URL}classes/PointRewardSystem.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `f=update_point_setting&setting_key=${key}&setting_value=${newValue}`
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        showAlert('success', 'Pengaturan berhasil disimpan');
                        cancelEdit(key);
                        loadPointSettings();
                    } else {
                        showAlert('error', res.message);
                    }
                })
                .catch(e => {
                    console.error(e);
                    showAlert('error', 'Terjadi kesalahan');
                });
        }

        function loadSummary() {
            // Load member and reward stats
            fetch(`<?= base_url ?>classes/Laporan.php?f=get_rekap_point_member&limit=1`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        document.getElementById('totalMembers').textContent = res.data.length;
                        let totalPoin = 0;
                        res.data.forEach(m => totalPoin += parseInt(m.total_poin || 0));
                        document.getElementById('totalPoints').textContent = totalPoin;
                    }
                });

            // Load reward stats (simplified - would need DB query)
            fetch(`${ROOT_URL}classes/PointRewardSystem.php?f=get_reward_items`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        document.getElementById('activeRewards').textContent = res.data.filter(r => r.status === 'active').length;
                    }
                });
        }

        function showAlert(type, message) {
            Swal.fire({
                icon: type,
                title: type === 'success' ? 'Berhasil' : 'Error',
                text: message,
                timer: 1500
            });
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            loadPointSettings();
            loadSummary();
        });
    </script>

    <?php include '../inc/footer.php'; ?>
</body>

</html>
