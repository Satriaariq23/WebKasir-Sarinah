<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Pengaturan Sistem Reward</h5>
                </div>
                <div class="card-body">
                    <form id="form_settings">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-coin me-1"></i>Pengaturan Poin</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Poin per Rupiah</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" name="point_per_rupiah" id="point_per_rupiah" class="form-control" value="10000">
                                                <span class="input-group-text">= 1 Poin</span>
                                            </div>
                                            <small class="text-muted">Setiap kelipatan nominal ini, pelanggan mendapat 1 poin</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Minimal Transaksi</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" name="min_transaction_point" id="min_transaction_point" class="form-control" value="50000">
                                            </div>
                                            <small class="text-muted">Transaksi di bawah nominal ini tidak mendapat poin</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Masa Berlaku Poin</label>
                                            <div class="input-group">
                                                <input type="number" name="point_expiry_months" id="point_expiry_months" class="form-control" value="12">
                                                <span class="input-group-text">Bulan</span>
                                            </div>
                                            <small class="text-muted">Poin akan expired setelah periode ini</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-toggles me-1"></i>Status Sistem</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="reward_active" id="reward_active" value="1">
                                                <label class="form-check-label" for="reward_active">
                                                    <strong>Aktifkan Sistem Reward</strong>
                                                </label>
                                            </div>
                                            <small class="text-muted">Jika dinonaktifkan, pelanggan tidak akan mendapat poin dari transaksi</small>
                                        </div>

                                        <hr>

                                        <div class="alert alert-warning mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            <strong>Perhatian:</strong> Perubahan pengaturan akan berlaku untuk transaksi baru. Poin yang sudah didapat tidak terpengaruh.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="?page=reward" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Simulasi Poin</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nominal Transaksi</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="sim_amount" class="form-control" value="500000">
                        </div>
                    </div>
                    <button class="btn btn-info w-100 mb-3" onclick="simulatePoints()">
                        <i class="bi bi-play me-1"></i>Hitung Poin
                    </button>
                    <div id="sim_result" class="alert alert-success d-none">
                        <div class="text-center">
                            <h3 class="mb-0" id="sim_points">0</h3>
                            <small>Poin yang akan didapat</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Panduan</h5>
                </div>
                <div class="card-body">
                    <h6>Cara Kerja Poin:</h6>
                    <ol class="small">
                        <li>Pelanggan melakukan transaksi</li>
                        <li>Jika nominal ≥ minimal transaksi, poin dihitung</li>
                        <li>Poin = Nominal ÷ Poin per Rupiah</li>
                        <li>Poin masuk ke akun pelanggan</li>
                        <li>Poin berlaku sesuai masa berlaku</li>
                    </ol>

                    <h6 class="mt-3">Cara Kerja Profile Matching:</h6>
                    <ol class="small">
                        <li>Setiap bulan, sistem menghitung statistik transaksi pelanggan</li>
                        <li>Statistik dibandingkan dengan kriteria ideal</li>
                        <li>Dihitung skor kesesuaian (0-5)</li>
                        <li>Pelanggan dengan skor tinggi berhak reward lebih baik</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const rewardApi = '../classes/RewardSystem.php';

    // Load settings
    function loadSettings() {
        $.get(rewardApi + '?f=get_settings', function(response) {
            if (response.status === 'success' && response.data) {
                const data = response.data;
                $('#point_per_rupiah').val(data.point_per_rupiah || 10000);
                $('#min_transaction_point').val(data.min_transaction_point || 50000);
                $('#point_expiry_months').val(data.point_expiry_months || 12);
                $('#reward_active').prop('checked', data.reward_active == '1');
            }
        }, 'json');
    }

    // Save settings
    $('#form_settings').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: 'save_settings',
            point_per_rupiah: $('#point_per_rupiah').val(),
            min_transaction_point: $('#min_transaction_point').val(),
            point_expiry_months: $('#point_expiry_months').val(),
            reward_active: $('#reward_active').is(':checked') ? '1' : '0'
        };

        $.post(rewardApi, formData, function(response) {
            if (response.status === 'success') {
                Swal.fire('Berhasil', response.msg, 'success');
            } else {
                Swal.fire('Error', response.msg || 'Terjadi kesalahan', 'error');
            }
        }, 'json');
    });

    // Simulate points calculation
    function simulatePoints() {
        const amount = parseFloat($('#sim_amount').val()) || 0;
        const perRupiah = parseFloat($('#point_per_rupiah').val()) || 10000;
        const minTransaction = parseFloat($('#min_transaction_point').val()) || 0;

        if (amount < minTransaction) {
            $('#sim_result').removeClass('d-none alert-success').addClass('alert-warning');
            $('#sim_points').text('0');
            $('#sim_result small').text('Transaksi di bawah minimal (Rp ' + minTransaction.toLocaleString('id-ID') + ')');
        } else {
            const points = Math.floor(amount / perRupiah);
            $('#sim_result').removeClass('d-none alert-warning').addClass('alert-success');
            $('#sim_points').text(points.toLocaleString('id-ID'));
            $('#sim_result small').text('Poin yang akan didapat');
        }
    }

    // Init
    $(document).ready(function() {
        loadSettings();

        // Auto simulate on input change
        $('#sim_amount, #point_per_rupiah, #min_transaction_point').on('input', function() {
            simulatePoints();
        });
    });
</script>