<div class="content-wrapper py-3 px-3">
    <div class="row">
        <!-- Kriteria Profile Matching -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Kriteria Profile Matching</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal_criteria">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Kriteria
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle me-1"></i>Tentang Profile Matching:</strong><br>
                        Algoritma Profile Matching menghitung kesesuaian profil pelanggan dengan profil ideal berdasarkan kriteria yang ditentukan.
                        <ul class="mb-0 mt-2">
                            <li><strong>Core Factor (60%):</strong> Kriteria utama yang sangat penting</li>
                            <li><strong>Secondary Factor (40%):</strong> Kriteria pendukung</li>
                            <li><strong>GAP:</strong> Selisih antara nilai aktual dengan nilai ideal</li>
                            <li><strong>Skor Akhir:</strong> (60% × NCF) + (40% × NSF)</li>
                        </ul>
                    </div>

                    <div class="table-responsive">
                        <table id="table_criteria" class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kriteria</th>
                                    <th>Tipe</th>
                                    <th>Bobot</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Ideal</th>
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

        <!-- GAP Mapping -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Tabel Bobot GAP</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Tabel konversi selisih (GAP) ke bobot nilai:</p>
                    <table class="table table-sm table-bordered">
                        <thead class="table-secondary">
                            <tr>
                                <th class="text-center">GAP</th>
                                <th class="text-center">Bobot</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center">0</td>
                                <td class="text-center text-success"><strong>5.00</strong></td>
                                <td>Ideal (kompetensi sesuai)</td>
                            </tr>
                            <tr>
                                <td class="text-center">1</td>
                                <td class="text-center">4.50</td>
                                <td>Kelebihan 1 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">-1</td>
                                <td class="text-center">4.00</td>
                                <td>Kekurangan 1 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">2</td>
                                <td class="text-center">3.50</td>
                                <td>Kelebihan 2 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">-2</td>
                                <td class="text-center">3.00</td>
                                <td>Kekurangan 2 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">3</td>
                                <td class="text-center">2.50</td>
                                <td>Kelebihan 3 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">-3</td>
                                <td class="text-center">2.00</td>
                                <td>Kekurangan 3 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">4</td>
                                <td class="text-center">1.50</td>
                                <td>Kelebihan 4 tingkat</td>
                            </tr>
                            <tr>
                                <td class="text-center">-4</td>
                                <td class="text-center text-danger">1.00</td>
                                <td>Kekurangan 4 tingkat</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Rumus Perhitungan</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>1. Konversi ke Skala (1-5):</strong>
                        <code class="d-block bg-light p-2 mt-1">
                            Skala = ((Nilai - Min) / (Max - Min)) × 4 + 1
                        </code>
                    </div>
                    <div class="mb-3">
                        <strong>2. Hitung GAP:</strong>
                        <code class="d-block bg-light p-2 mt-1">
                            GAP = Skala Aktual - Skala Ideal
                        </code>
                    </div>
                    <div class="mb-3">
                        <strong>3. NCF (Nilai Core Factor):</strong>
                        <code class="d-block bg-light p-2 mt-1">
                            NCF = Σ(Bobot Core) / Jumlah Core
                        </code>
                    </div>
                    <div class="mb-3">
                        <strong>4. NSF (Nilai Secondary Factor):</strong>
                        <code class="d-block bg-light p-2 mt-1">
                            NSF = Σ(Bobot Secondary) / Jumlah Secondary
                        </code>
                    </div>
                    <div>
                        <strong>5. Skor Akhir:</strong>
                        <code class="d-block bg-light p-2 mt-1">
                            Skor = (60% × NCF) + (40% × NSF)
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Criteria -->
<div class="modal fade" id="modal_criteria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_criteria">
                <input type="hidden" name="id" id="criteria_id">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kriteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kriteria <span class="text-danger">*</span></label>
                        <input type="text" name="criteria_name" id="criteria_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipe Faktor</label>
                        <select name="criteria_type" id="criteria_type" class="form-select">
                            <option value="CORE">Core Factor (Utama)</option>
                            <option value="SECONDARY">Secondary Factor (Pendukung)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bobot (%)</label>
                        <input type="number" name="weight" id="weight" class="form-control" step="0.01" value="50">
                        <small class="text-muted">Total bobot Core + Secondary harus = 100%</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Minimum</label>
                            <input type="number" name="min_value" id="min_value" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Maximum</label>
                            <input type="number" name="max_value" id="max_value" class="form-control" step="0.01" value="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Ideal</label>
                            <input type="number" name="ideal_value" id="ideal_value" class="form-control" step="0.01" value="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" id="criteria_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="criteria_active" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="criteria_active">Aktif</label>
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

    // Load criteria
    function loadCriteria() {
        $.get(rewardApi + '?f=get_reward_catalog', function(response) {
            // We need a separate endpoint for criteria, use direct query
        }, 'json');

        // For now, display existing criteria from database
        $.ajax({
            url: rewardApi,
            type: 'POST',
            data: {
                action: 'get_criteria'
            },
            dataType: 'json',
            success: function(response) {
                renderCriteria(response.data || []);
            },
            error: function() {
                // Fallback - load from table directly
                loadCriteriaFallback();
            }
        });
    }

    function loadCriteriaFallback() {
        // Default criteria display
        const defaultCriteria = [{
                criteria_name: 'total_transaksi',
                criteria_type: 'CORE',
                weight: 60,
                min_value: 0,
                max_value: 100000000,
                ideal_value: 5000000,
                is_active: 1,
                description: 'Total nominal transaksi bulanan (Rp)'
            },
            {
                criteria_name: 'frekuensi_belanja',
                criteria_type: 'SECONDARY',
                weight: 40,
                min_value: 0,
                max_value: 100,
                ideal_value: 10,
                is_active: 1,
                description: 'Jumlah transaksi per bulan'
            }
        ];
        renderCriteria(defaultCriteria);
    }

    function renderCriteria(data) {
        let html = '';
        data.forEach(c => {
            const typeClass = c.criteria_type === 'CORE' ? 'bg-primary' : 'bg-secondary';
            html += `
            <tr>
                <td><strong>${c.criteria_name}</strong><br><small class="text-muted">${c.description || ''}</small></td>
                <td><span class="badge ${typeClass}">${c.criteria_type}</span></td>
                <td>${c.weight}%</td>
                <td>${Number(c.min_value).toLocaleString('id-ID')}</td>
                <td>${Number(c.max_value).toLocaleString('id-ID')}</td>
                <td><strong>${Number(c.ideal_value).toLocaleString('id-ID')}</strong></td>
                <td>${c.is_active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>'}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editCriteria(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>
        `;
        });
        if (!html) {
            html = '<tr><td colspan="8" class="text-center text-muted">Belum ada kriteria</td></tr>';
        }
        $('#table_criteria tbody').html(html);
    }

    function editCriteria(data) {
        $('#criteria_id').val(data.id || '');
        $('#criteria_name').val(data.criteria_name);
        $('#criteria_type').val(data.criteria_type);
        $('#weight').val(data.weight);
        $('#min_value').val(data.min_value);
        $('#max_value').val(data.max_value);
        $('#ideal_value').val(data.ideal_value);
        $('#criteria_description').val(data.description);
        $('#criteria_active').prop('checked', data.is_active == 1);
        $('#modal_criteria').modal('show');
    }

    $('#form_criteria').on('submit', function(e) {
        e.preventDefault();

        $.post(rewardApi, $(this).serialize() + '&action=save_criteria', function(response) {
            if (response.status === 'success') {
                Swal.fire('Berhasil', response.msg, 'success');
                $('#modal_criteria').modal('hide');
                loadCriteria();
            } else {
                Swal.fire('Error', response.msg, 'error');
            }
        }, 'json');
    });

    $(document).ready(function() {
        loadCriteriaFallback();
    });
</script>