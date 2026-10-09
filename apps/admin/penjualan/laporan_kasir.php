<link rel="stylesheet" href="<?php echo base_url ?>assets/css/print-menu.css">

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <!--begin::Header-->
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Laporan Kasir</span>
                        <span class="text-muted mt-1 fw-bold fs-7">Daftar / Riwayat Transaksi Kasir</span>
                    </h3>
                    <div class="card-toolbar" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover" title="Input Transaksi Kasir Baru">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if ($_settings->set_userdata('user_type') < 3): ?>
                                <div class="form-group col-auto pt-5">
                                    <a class="btn btn-icon btn-success btn-sm" href="<?php echo base_url . '/admin?page=penjualan/kasir' ?>">
                                        <i class="bi bi-file-earmark-plus fs-3"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>
                <!--end::Header-->
                <div class="card-body pt-2 pb-0">
                    <div class="table-responsive">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tgl_awal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tgl_akhir">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis Transaksi</label>
                                <select id="jenis" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="tunai">Tunai</option>
                                    <option value="kredit">Kredit</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pelanggan</label>
                                <select id="pelanggan_id" name="pelanggan_id" class="form-select" style="width: 100%;"></select>
                            </div>
                        </div>

                        <div class="text-end mb-2">
                            <button class="btn btn-sm btn-primary" id="filter-btn"><i class="bi bi-funnel"></i> Filter</button>
                            <button class="btn btn-sm btn-secondary" id="reset-btn"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                        </div>
                        <table id="table" class="table table-hover table-bordered align-middle w-100">
                            <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                <tr>
                                    <th class="px-2 text-center" style="width: 5%;">No.</th>
                                    <th class="text-center">Tanggal</th>
                                    <th class="text-center">No. Invoice/No. Faktur</th>
                                    <th class="text-center">Jenis Transaksi</th>
                                    <th class="text-center">Pelanggan</th>
                                    <th class="text-center">Grand Total</th>
                                    <th class="px-2 text-center">Aksi</th>
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
    // Helper escape HTML untuk cegah XSS
    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    let kasirDT;

    function load_data() {
        kasirDT = $('#table').DataTable({
            processing: true,
            serverSide: true,
            stateSave: true,
            ajax: {
                url: _base_url_ + "classes/Penjualan.php?f=get_data_penjualan",
                type: "POST",
                data: function(d) {
                    d.tgl_awal = $('#tgl_awal').val();
                    d.tgl_akhir = $('#tgl_akhir').val();
                    d.tipe_transaksi = 'kasir'; // FILTER: Hanya tampilkan Kasir (KSR)
                    d.jenis = $('#jenis').val();
                    d.pelanggan_id = $('#pelanggan_id').val();
                },
                dataSrc: function(json) {
                    return json.data || [];
                }
            },
            columns: [{
                    data: "no",
                    className: "text-center fw-bold"
                },
                {
                    data: "sale_date",
                    className: "text-center"
                },
                {
                    data: null,
                    className: "text-center",
                    render: row => `
                        <div class="fw-bold">${escapeHtml(row.sale_invoice)}</div>
                        <small class="text-muted">${escapeHtml(row.sale_nofak || '')}</small>
                    `
                },
                {
                    data: "jenis_transaksi",
                    className: "text-center",
                    render: d => `<span class="badge bg-success">${d}</span>`
                },
                {
                    data: null,
                    className: "text-start",
                    render: function(data, type, row) {
                        const nama = escapeHtml(row.pelanggan_name || '-');
                        const alamat = escapeHtml(row.pelanggan_alamat || '');
                        return `
                            <div class="d-flex align-items-start">
                                <div class="symbol symbol-30px me-2">
                                    <i class="fa fa-user text-success fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">${nama}</div>
                                    ${alamat ? `<div class="text-muted small">${alamat}</div>` : ''}
                                </div>
                            </div>
                        `;
                    }
                },
                {
                    data: "sale_total",
                    className: "text-end fw-bold",
                    render: d => 'Rp ' + d
                },
                {
                    data: null,
                    orderable: false,
                    className: "text-center",
                    render: row => actionButtons(row)
                }
            ],
            order: [
                [0, 'desc']
            ]
        });
    }

    // tombol filter & reset
    $('#filter-btn').click(() => kasirDT.ajax.reload());
    $('#reset-btn').click(() => {
        $('#tgl_awal, #tgl_akhir, #jenis, #pelanggan_id').val('');
        kasirDT.ajax.reload();
    });

    // Tombol aksi
    function actionButtons(row) {
        return `
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-dark btn-sm dropdown-toggle" 
                    data-bs-toggle="dropdown" aria-expanded="false" title="Print">
                    <i class="fa fa-print"></i>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item print-nota-browser" href="#" data-id="${row.saleid}">
                            <i class="bi bi-window me-2"></i> Nota (In-Browser/Thermal)
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item print-invoice-browser" href="#" data-id="${row.saleid}">
                            <i class="bi bi-file-earmark-text me-2"></i> Invoice (In-Browser/A4)
                        </a>
                    </li>
                </ul>
            </div>
            <button class="btn btn-danger btn-sm delete_data" 
                data-id="${row.saleid}" data-invoice="${escapeHtml(row.sale_invoice)}" title="Hapus Data">
                <i class="fa fa-trash"></i>
            </button>
        `;
    }

    // Redraw DataTable
    window.draw_data = function(resetPaging = false) {
        if (kasirDT && $.fn.dataTable.isDataTable('#table')) {
            kasirDT.ajax.reload(null, !!resetPaging);
        } else {
            load_data();
        }
    };

    // Delete Data
    $(document).on('click', '.delete_data', function() {
        const id = $(this).data('id');
        const invoice = $(this).data('invoice');

        Swal.fire({
            title: 'Konfirmasi Hapus',
            html: `Apakah Anda yakin ingin menghapus transaksi<br><strong>${invoice}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                delete_data(id);
            }
        });
    });

    function delete_data(id) {
        start_loader();
        $.ajax({
            url: _base_url_ + "classes/Penjualan.php?f=delete_penjualan",
            method: "POST",
            data: {
                saleid: id
            },
            dataType: "json",
            error: function(err) {
                console.error("AJAX Error:", err);
                alert_toast("Terjadi kesalahan koneksi ke server.", "error");
                end_loader();
            },
            success: function(resp) {
                if (resp && resp.status === "success") {
                    showMessage("Data berhasil dihapus", "primary");
                    draw_data(true);
                } else {
                    showMessage(resp.msg || "Gagal menghapus data", "danger");
                }
                end_loader();
            }
        });
    }

    // Helper tampilkan pesan
    function showMessage(message, type = "primary") {
        let _el = $('<div>')
            .hide()
            .addClass(`alert alert-${type} alert_msg`)
            .text(message);
        $("#msg").append(_el);
        _el.show("slow");
        setTimeout(() => {
            _el.hide("slow").remove();
        }, 2500);
    }

    $(document).ready(function() {
        load_data();
    });

    // Search Pelanggan Filter
    $(document).ready(function() {
        // Inisialisasi Select2 untuk pelanggan
        $('#pelanggan_id').select2({
            placeholder: 'Cari pelanggan...',
            allowClear: true,
            width: 'resolve',
            ajax: {
                url: _base_url_ + 'classes/Penjualan.php?f=search_pelanggan',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term || ''
                    };
                },
                processResults: function(data) {
                    return {
                        results: $.map(data, function(item) {
                            return {
                                id: item.id,
                                text: item.nama + (item.alamat ? ' — ' + item.alamat : '')
                            };
                        })
                    };
                },
                cache: true
            },
            language: {
                noResults: function() {
                    return 'Tidak ada pelanggan ditemukan';
                },
                searching: function() {
                    return 'Mencari...';
                },
                inputTooShort: function() {
                    return 'Ketik minimal 2 huruf';
                }
            },
            minimumInputLength: 2
        });
    });

    // Event handler untuk print menu
    $(document).on('click', '.print-nota-browser', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        window.open(_base_url_ + 'admin/penjualan/print_kasir.php?id=' + id, '_blank', 'width=800,height=600');
    });

    $(document).on('click', '.print-invoice-browser', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        window.open(_base_url_ + 'admin/penjualan/print_kasir_invoice.php?id=' + id, '_blank', 'width=900,height=700');
    });
</script>
