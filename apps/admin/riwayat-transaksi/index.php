<?php
$petugasOptions = [];
if (isset($conn) && $conn instanceof mysqli) {
    $users = $conn->query("SELECT id, firstname, username, level_id FROM users WHERE status = 1 ORDER BY firstname ASC, username ASC");
    if ($users) {
        while ($user = $users->fetch_assoc()) {
            $label = trim($user['firstname'] ?: $user['username']);
            $role = get_role_name((int)$user['level_id']);
            $petugasOptions[] = [
                'id' => (int)$user['id'],
                'label' => $label !== '' ? $label : ('User #' . (int)$user['id']),
                'role' => $role
            ];
        }
    }
}
?>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Riwayat Transaksi</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Seluruh transaksi Sales, Sales Online, dan Kasir</span>
                    </h3>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <div class="card-body pt-4 pb-0">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-7 text-gray-600">Jumlah Transaksi</div>
                                <div class="fs-2 fw-bolder text-dark" id="summary_count">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-7 text-gray-600">Total Nominal</div>
                                <div class="fs-2 fw-bolder text-primary" id="summary_total">Rp 0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-7 text-gray-600">Transaksi Sales</div>
                                <div class="fs-2 fw-bolder text-success" id="summary_sales">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-7 text-gray-600">Transaksi Kasir</div>
                                <div class="fs-2 fw-bolder text-info" id="summary_kasir">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-7 text-gray-600">Transaksi Sales Online</div>
                                <div class="fs-2 fw-bolder text-warning" id="summary_online">0</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <label class="form-label">Sumber</label>
                            <select id="filter_source" class="form-select">
                                <option value="all">Semua</option>
                                <option value="sales">Sales</option>
                                <option value="online">Sales Online</option>
                                <option value="kasir">Kasir</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Awal</label>
                            <input type="date" class="form-control" id="filter_tgl_awal" value="<?php echo date('Y-01-01') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="filter_tgl_akhir" value="<?php echo date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Jenis Bayar</label>
                            <select id="filter_bayar" class="form-select">
                                <option value="">Semua</option>
                                <option value="1">Tunai</option>
                                <option value="2">Kredit</option>
                                <option value="3">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select id="filter_status" class="form-select">
                                <option value="">Aktif & Void</option>
                                <option value="ACTIVE">Aktif</option>
                                <option value="VOID">Void</option>
                                <option value="DELETED">Dihapus</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Metode</label>
                            <select id="filter_payment_method" class="form-select">
                                <option value="">Semua</option>
                                <option value="cash">Cash</option>
                                <option value="transfer">Transfer</option>
                                <option value="qris">QRIS</option>
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                                <option value="piutang">Piutang</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Pelanggan / Member</label>
                            <select id="filter_pelanggan" class="form-select" style="width:100%"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Petugas</label>
                            <select id="filter_petugas" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($petugasOptions as $petugas): ?>
                                    <option value="<?php echo $petugas['id'] ?>">
                                        <?php echo htmlspecialchars($petugas['label'] . ' - ' . $petugas['role']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btn_filter">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" id="btn_reset">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="table_riwayat" class="table table-hover table-bordered align-middle w-100">
                            <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                <tr>
                                    <th class="text-center" style="width:5%">No</th>
                                    <th class="text-center">Sumber</th>
                                    <th class="text-center">Tanggal</th>
                                    <th>No. Invoice</th>
                                    <th>Pelanggan</th>
                                    <th>Petugas</th>
                                    <th class="text-center">Bayar</th>
                                    <th class="text-center">Metode</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width:7%">Aksi</th>
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

<script>
    const riwayatBaseUrl = "<?php echo base_url ?>classes/Penjualan.php";
    let riwayatTable;

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function formatRupiah(value) {
        return 'Rp ' + (Number(value) || 0).toLocaleString('id-ID');
    }

    function paymentMethodLabel(method) {
        const labels = {
            cash: 'Tunai',
            transfer: 'Transfer',
            qris: 'QRIS',
            debit: 'Debit',
            debit_card: 'Kartu Debit',
            credit: 'Credit',
            credit_card: 'Kartu Kredit',
            e_wallet: 'E-Wallet',
            piutang: 'Piutang'
        };
        const key = (method || '').toString().toLowerCase();
        return labels[key] || (method || '-').toString().toUpperCase();
    }

    function updateSummary(summary) {
        summary = summary || {};
        $('#summary_count').text((Number(summary.jumlah_transaksi) || 0).toLocaleString('id-ID'));
        $('#summary_total').text(formatRupiah(summary.total_nominal));
        $('#summary_sales').text((Number(summary.total_sales) || 0).toLocaleString('id-ID'));
        $('#summary_online').text((Number(summary.total_online) || 0).toLocaleString('id-ID'));
        $('#summary_kasir').text((Number(summary.total_kasir) || 0).toLocaleString('id-ID'));
    }

    function sourceBadge(source) {
        if (source === 'KASIR') {
            return '<span class="badge bg-info">Kasir</span>';
        }
        return '<span class="badge bg-success">Sales</span>';
    }

    function statusBadge(status) {
        if (status === 'VOID') {
            return '<span class="badge bg-danger">Void</span>';
        }
        if (status === 'DELETED') {
            return '<span class="badge bg-secondary">Dihapus</span>';
        }
        return '<span class="badge bg-success">Aktif</span>';
    }

    function bayarBadge(label) {
        const normalized = String(label).toLowerCase();
        const color = normalized === 'kredit' ? 'warning' : (normalized === 'tunai' ? 'primary' : 'secondary');
        return `<span class="badge bg-${color}">${escapeHtml(label)}</span>`;
    }

    function actionButtons(row) {
        const isDeleted = row.sale_status === 'DELETED';
        const isKasir = row.sumber_transaksi === 'KASIR';
        const invoice = escapeHtml(row.sale_invoice || '');
        const editButton = isKasir
            ? `<button type="button" class="btn btn-icon btn-light-warning btn-sm edit_kasir_data"
                    data-id="${row.saleid}" title="Edit transaksi kasir">
                    <i class="bi bi-pencil-square"></i>
               </button>`
            : `<button type="button" class="btn btn-icon btn-light-warning btn-sm edit_sales_data"
                    data-id="${row.saleid}" title="Edit transaksi">
                    <i class="bi bi-pencil-square"></i>
               </button>`;

        return `
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-icon btn-light-primary btn-sm print_data"
                    data-id="${row.saleid}" title="Lihat / Print">
                    <i class="bi bi-printer"></i>
                </button>
                ${isDeleted ? '' : editButton}
                ${isDeleted ? '' : `
                    <button type="button" class="btn btn-icon btn-light-danger btn-sm delete_data"
                        data-id="${row.saleid}" data-invoice="${invoice}" title="Hapus transaksi">
                        <i class="bi bi-trash"></i>
                    </button>
                `}
            </div>
        `;
    }

    function filterPayload(d) {
        d.source = $('#filter_source').val();
        d.tgl_awal = $('#filter_tgl_awal').val();
        d.tgl_akhir = $('#filter_tgl_akhir').val();
        d.bayar_id = $('#filter_bayar').val();
        d.status = $('#filter_status').val();
        d.payment_method = $('#filter_payment_method').val();
        d.pelanggan_id = $('#filter_pelanggan').val();
        d.petugas_id = $('#filter_petugas').val();
        return d;
    }

    function initPelangganFilter() {
        $('#filter_pelanggan').select2({
            placeholder: 'Semua pelanggan',
            allowClear: true,
            width: '100%',
            ajax: {
                url: riwayatBaseUrl + '?f=search_pelanggan',
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term || '' }),
                processResults: function(data) {
                    return {
                        results: $.map(data || [], function(item) {
                            if (!item.id) return null;
                            return {
                                id: item.id,
                                text: item.nama + (item.kode ? ' (' + item.kode + ')' : '')
                            };
                        }).filter(Boolean)
                    };
                },
                cache: true
            }
        });
    }

    function initDataTable() {
        riwayatTable = $('#table_riwayat').DataTable({
            processing: true,
            serverSide: true,
            stateSave: false,
            ajax: {
                url: riwayatBaseUrl + '?f=get_riwayat_transaksi',
                type: 'POST',
                data: filterPayload,
                dataSrc: function(json) {
                    updateSummary(json.summary);
                    if (json.error) {
                        showMessage(json.error, 'danger');
                    }
                    return json.data || [];
                },
                error: function(xhr) {
                    updateSummary(null);
                    const message = xhr.responseJSON?.error || 'Terjadi kesalahan saat memuat riwayat transaksi';
                    showMessage(message, 'danger');
                }
            },
            columns: [
                { data: 'no', className: 'text-center fw-bold', orderable: false, searchable: false },
                {
                    data: 'sumber_transaksi',
                    className: 'text-center',
                    render: data => sourceBadge(data)
                },
                { data: 'tanggal_display', className: 'text-center' },
                {
                    data: null,
                    render: function(row) {
                        return `
                            <div class="fw-bold">${escapeHtml(row.sale_invoice)}</div>
                            ${row.sale_nofak ? `<small class="text-muted">${escapeHtml(row.sale_nofak)}</small>` : ''}
                        `;
                    }
                },
                {
                    data: null,
                    render: function(row) {
                        return `
                            <div class="fw-bold">${escapeHtml(row.pelanggan_display)}</div>
                            ${row.pelanggan_kode ? `<small class="text-muted">${escapeHtml(row.pelanggan_kode)}</small>` : ''}
                        `;
                    }
                },
                { data: 'petugas_name', render: data => escapeHtml(data || '-') },
                {
                    data: 'jenis_bayar',
                    className: 'text-center',
                    render: data => bayarBadge(data || 'Tunai')
                },
                {
                    data: 'payment_method',
                    className: 'text-center',
                    render: data => escapeHtml(paymentMethodLabel(data))
                },
                {
                    data: 'sale_total',
                    className: 'text-end fw-bold',
                    render: data => formatRupiah(data)
                },
                {
                    data: 'sale_status',
                    className: 'text-center',
                    render: data => statusBadge(data)
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: row => actionButtons(row)
                }
            ],
            order: [[2, 'desc']],
            responsive: true,
            scrollX: true,
            language: {
                processing: "<div class='spinner-border spinner-border-sm me-2'></div> Memproses...",
                lengthMenu: "Tampilkan _MENU_ data",
                zeroRecords: "Tidak ada riwayat transaksi",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });
    }

    function showMessage(message, type = 'primary') {
        $('.alert_msg').remove();
        const alert = $('<div>')
            .hide()
            .addClass('alert alert-' + type + ' alert_msg')
            .text(message);
        $('#msg').append(alert);
        alert.show('slow');
        setTimeout(() => alert.hide('slow').remove(), 3000);
    }

    $(document).ready(function() {
        initPelangganFilter();
        initDataTable();

        $('#btn_filter').on('click', function() {
            riwayatTable.ajax.reload();
        });

        $('#btn_reset').on('click', function() {
            $('#filter_source').val('all');
            $('#filter_tgl_awal').val('<?php echo date('Y-01-01') ?>');
            $('#filter_tgl_akhir').val('<?php echo date('Y-m-d') ?>');
            $('#filter_bayar').val('');
            $('#filter_status').val('');
            $('#filter_payment_method').val('');
            $('#filter_petugas').val('');
            $('#filter_pelanggan').val(null).trigger('change');
            riwayatTable.search('');
            riwayatTable.ajax.reload();
        });

        $('#filter_source, #filter_bayar, #filter_status, #filter_payment_method, #filter_petugas').on('change', function() {
            riwayatTable.ajax.reload();
        });

        $(document).on('click', '.print_data', function() {
            const id = $(this).data('id');
            if (!id) {
                showMessage('ID transaksi tidak valid', 'danger');
                return;
            }
            window.open('<?php echo base_url ?>admin?page=penjualan/print_invoice&id=' + encodeURIComponent(id), '_blank');
        });

        $(document).on('click', '.edit_sales_data', function() {
            const id = $(this).data('id');
            if (!id) {
                showMessage('ID transaksi tidak valid', 'danger');
                return;
            }
            window.location.href = '<?php echo base_url ?>admin/?page=penjualan&edit=' + encodeURIComponent(id);
        });

        $(document).on('click', '.edit_kasir_data', function() {
            const id = $(this).data('id');
            if (!id) {
                showMessage('ID transaksi tidak valid', 'danger');
                return;
            }
            window.location.href = '<?php echo base_url ?>admin/?page=penjualan/kasir&edit=' + encodeURIComponent(id);
        });

        $(document).on('click', '.delete_data', function() {
            const id = $(this).data('id');
            const invoice = $(this).data('invoice') || '';
            if (!id) {
                showMessage('ID transaksi tidak valid', 'danger');
                return;
            }

            Swal.fire({
                title: 'Hapus Transaksi?',
                html: `Transaksi <b>${escapeHtml(invoice)}</b> akan ditandai sebagai dihapus.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(result => {
                if (!result.isConfirmed) return;

                start_loader();
                $.ajax({
                    url: riwayatBaseUrl + '?f=delete_penjualan',
                    method: 'POST',
                    dataType: 'json',
                    data: { saleid: id },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            showMessage(resp.msg || 'Transaksi berhasil dihapus', 'success');
                            riwayatTable.ajax.reload(null, false);
                        } else {
                            showMessage(resp.msg || 'Gagal menghapus transaksi', 'danger');
                        }
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.msg || 'Terjadi kesalahan saat menghapus transaksi';
                        showMessage(message, 'danger');
                    },
                    complete: function() {
                        end_loader();
                    }
                });
            });
        });
    });
</script>
