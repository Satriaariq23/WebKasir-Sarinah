<?php

/**
 * POINT SETTINGS PAGE
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
                        <i class="fa fa-cog me-3" style="color: #667eea;"></i>Konfigurasi Sistem Point & Reward
                    </h2>
                    <p style="color: #6c757d; margin: 0; font-size: 0.95rem;">
                        <i class="fa fa-info-circle me-2"></i>Kelola semua pengaturan sistem point dan reward pelanggan
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Total Members</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="statTotalMembers">-</h4>
                            </div>
                            <i class="fa fa-users" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Total Point</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="statTotalPoints">-</h4>
                            </div>
                            <i class="fa fa-star" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Active Rewards</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="statActiveRewards">-</h4>
                            </div>
                            <i class="fa fa-gift" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body p-3" style="color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small style="opacity: 0.9;">Settings</small>
                                <h4 class="mb-0" style="font-weight: 700;" id="statTotalSettings">5</h4>
                            </div>
                            <i class="fa fa-sliders-h" style="font-size: 2rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
        <div class="card-body p-4">
            <div id="settingsContainer">
                <div class="text-center p-5">
                    <i class="fa fa-spinner fa-spin text-primary" style="font-size: 2em;"></i>
                    <p class="text-muted mt-2">Memuat pengaturan...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .setting-group-title {
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 20px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        color: #495057;
    }

    .setting-group-title:first-child {
        margin-top: 0;
    }

    .setting-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .setting-card:hover {
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        border-color: #667eea;
    }

    .setting-card.editing {
        background: #f0f4ff;
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }

    .setting-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-right: 12px;
        flex-shrink: 0;
    }

    .setting-label {
        font-weight: 600;
        color: #212529;
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .setting-description {
        font-size: 0.85rem;
        color: #6c757d;
        line-height: 1.4;
        margin-bottom: 8px;
    }

    .setting-value {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .setting-value-display {
        font-size: 1.3rem;
        font-weight: 700;
        color: #667eea;
        min-width: 100px;
    }

    .setting-value-edit {
        display: none;
    }

    .setting-card.editing .value-edit {
        display: flex;
    }

    .setting-card.editing .value-display {
        display: none;
    }

    .btn-edit-setting {
        padding: 6px 10px;
        font-size: 0.9rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-edit-setting:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .input-group-sm .form-control {
        border-radius: 6px;
    }

    .input-group-sm .btn {
        border-radius: 6px;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    }

    /* Icon color by group */
    .icon-minimal {
        background-color: #e7f5ff;
        color: #0066ff;
    }

    .icon-frekuensi {
        background-color: #fff3bf;
        color: #ff9800;
    }

    .icon-reward {
        background-color: #f0fdf4;
        color: #22c55e;
    }

    .icon-status {
        background-color: #fce7f3;
        color: #ec4899;
    }
</style>

<script>
    const BASE_URL = '<?= base_url ?>';

    // Mapping untuk label lebih user-friendly
    const settingLabels = {
        'min_belanja_point': {
            label: 'Minimal Belanja untuk Mendapat Point',
            description: 'Jumlah minimal pembelian yang diperlukan untuk mendapatkan 1 point',
            icon: '💰',
            iconClass: 'icon-minimal'
        },
        'frekuensi_belanja': {
            label: 'Frekuensi Belanja',
            description: 'Berapa kali transaksi dalam sebulan untuk score 100% di Profile Matching',
            icon: '📊',
            iconClass: 'icon-frekuensi'
        },
        'max_poin_per_transaksi': {
            label: 'Maximum Point per Transaksi',
            description: 'Batasan maksimal poin yang bisa didapat dalam satu transaksi',
            icon: '⭐',
            iconClass: 'icon-reward'
        },
        'point_nilai_tukar': {
            label: 'Nilai Tukar Point',
            description: 'Referensi nilai nominal rupiah per 1 point',
            icon: '💎',
            iconClass: 'icon-reward'
        },
        'status_poin_system': {
            label: 'Status Sistem Point',
            description: 'Aktifkan atau nonaktifkan sistem point secara keseluruhan',
            icon: '🔄',
            iconClass: 'icon-status'
        }
    };

    function loadPointSettings() {
        $.ajax({
            url: 'pengaturan/api.php?action=get_point_settings',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    displaySettings(res.data);
                    updateStats();
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Gagal memuat settings: ' + error);
            }
        });
    }

    function updateStats() {
        $.ajax({
            url: 'pengaturan/api.php?action=get_summary',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#statTotalMembers').text(res.data.total_members.toLocaleString('id-ID'));
                    $('#statTotalPoints').text(res.data.total_points.toLocaleString('id-ID'));
                    $('#statActiveRewards').text(res.data.active_rewards);
                }
            }
        });
    }

    function displaySettings(settings) {
        const container = $('#settingsContainer');
        let html = '';

        // Group dan order settings
        const orderedKeys = [
            'min_belanja_point',
            'frekuensi_belanja',
            'max_poin_per_transaksi',
            'point_nilai_tukar',
            'status_poin_system'
        ];

        for (const key of orderedKeys) {
            if (!settings[key]) continue;

            const setting = settings[key];
            const value = setting.setting_value;
            const meta = settingLabels[key] || {
                label: key,
                description: setting.deskripsi,
                icon: '⚙️',
                iconClass: 'icon-status'
            };

            // Format value dengan separator untuk nominal
            let displayValue = value;
            if (key === 'min_belanja_point' || key === 'point_nilai_tukar') {
                displayValue = 'Rp ' + parseInt(value).toLocaleString('id-ID');
            }

            html += `
            <div class="setting-card" id="setting-${key}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1 d-flex">
                        <div class="setting-icon ${meta.iconClass}">
                            ${meta.icon}
                        </div>
                        <div style="flex: 1;">
                            <div class="setting-label">${meta.label}</div>
                            <div class="setting-description">${meta.description}</div>
                            <div class="setting-value">
                                <span class="setting-value-display" id="display-${key}">${displayValue}</span>
                                <div class="setting-value-edit">
                                    <div class="input-group input-group-sm" style="max-width: 250px;">
                                        <input type="text" class="form-control" id="input-${key}" value="${value}" placeholder="Masukkan nilai baru">
                                        <button class="btn btn-success btn-sm" type="button" onclick="saveSetting('${key}')" title="Simpan">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        <button class="btn btn-secondary btn-sm" type="button" onclick="cancelEdit('${key}')" title="Batal">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ml-3">
                        <button class="btn btn-sm btn-edit-setting btn-outline-primary" onclick="editSetting('${key}', '${value}')" title="Edit">
                            <i class="fa fa-edit me-1"></i> Edit
                        </button>
                    </div>
                </div>
            </div>`;
        }

        container.html(html);
    }

    function editSetting(key, value) {
        const card = $(`#setting-${key}`);
        card.addClass('editing');
        $(`#input-${key}`).focus().select();
    }

    function cancelEdit(key) {
        const card = $(`#setting-${key}`);
        card.removeClass('editing');
    }

    function saveSetting(key) {
        const newValue = $(`#input-${key}`).val().trim();

        if (!newValue) {
            showError('Nilai tidak boleh kosong');
            return;
        }

        $.ajax({
            url: 'pengaturan/api.php',
            type: 'POST',
            data: {
                action: 'update_point_setting',
                key: key,
                value: newValue
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    alert_toast('✅ Setting berhasil disimpan', 'success');
                    cancelEdit(key);
                    loadPointSettings();
                } else {
                    showError(res.message || 'Gagal menyimpan setting');
                }
            },
            error: function(xhr, status, error) {
                showError('Gagal menyimpan setting: ' + error);
            }
        });
    }

    function showError(message) {
        alert_toast(message, 'danger');
    }

    // Load on page ready
    $(document).ready(function() {
        loadPointSettings();

        // Refresh stats setiap 30 detik
        setInterval(updateStats, 30000);
    });
</script>