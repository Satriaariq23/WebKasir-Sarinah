<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-xl-stretch mb-5 mb-xl-8">
                <div class="card-header align-items-center border-0 mt-4">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="text-dark fw-bolder">Data Stok</span>
                    </h3>
                    <div class="card-toolbar">
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <!-- Filter Kategori -->
                            <div class="col-md-12 me-2">
                                <label for="filter_kategori" class="form-label fw-bold fs-7 mb-0">Filter Kategori</label>
                                <select id="filter_kategori" class="form-select fs-7">
                                    <option value="">Semua</option>
                                    <?php
                                    // Ambil kategori secara aman (no user input)
                                    $stmt = $db->prepare("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    while ($row = $res->fetch_assoc()):
                                    ?>
                                        <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['nama_kategori']) ?></option>
                                    <?php endwhile;
                                    $stmt->close();
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12" id="msg"></div>
                </div>
                <div class="separator"></div>

                <div class="card mb-2 mb-xxl-8">
                    <div class="card-body pt-2 pb-0">
                        <div class="table-responsive">
                            <table id="table-stok" class="table table-hover table-bordered align-middle" style="width:100%">
                                <thead class="bg-light text-gray-600 fw-bolder text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No</th>
                                        <th>Barcode</th>
                                        <th>Nama Produk</th>
                                        <th>Satuan</th>
                                        <th class="text-end">Awal</th>
                                        <th class="text-end">Masuk</th>
                                        <th class="text-end">Keluar</th>
                                        <th class="text-end">Sisa</th>
                                        <th class="text-end">Nilai Asset</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>


<script>
/** Utility: escape HTML to avoid XSS in client rendering */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return $('<div>').text(text).html();
}

function stockNumber(value) {
    const rounded = Math.round(SarinahNumbers.fromApi(value) * 10000) / 10000;
    return Object.is(rounded, -0) ? 0 : rounded;
}

function formatStockQuantity(value) {
    return SarinahNumbers.formatQuantity(stockNumber(value), {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4
    });
}

let stokDT;
const baseUrl = "../classes/Stok.php"; // Pastikan path benar

function load_data_stok() {
    if (stokDT) {
        stokDT.destroy();
        $('#table-stok tbody').empty();
    }

    stokDT = $('#table-stok').DataTable({
        processing: true,
        serverSide: true,
        stateSave: true,
        autoWidth: false,
        ajax: {
            url: baseUrl + "?f=get_data_stok",
            type: "POST",
            data: function(d) {
                // DataTables sudah mengirim draw/start/length/order/search
                d.filter_kategori = $('#filter_kategori').val() || '';
            },
            dataSrc: function(json) {
                if (json.error) {
                    console.error('Server error:', json.error);
                    // tampilkan pesan kecil
                    $('#msg').html('<div class="alert alert-danger small mb-0">Terjadi kesalahan server. Cek console.</div>');
                    return [];
                }
                // pastikan data ada
                return json.data || [];
            },
            error: function(xhr, status, err) {
                console.error("AJAX Error:", xhr.responseText || status);
                $('#msg').html('<div class="alert alert-danger small mb-0">Gagal memuat data.</div>');
            }
        },
        columns: [
            { data: "no", className: "text-center fw-bold" },
            { data: "barcode", render: data => `<div>${escapeHtml(data)}</div>` },
            {
                data: "nama_produk",
                className: "text-start",
                render: function(data, type, row) {
                    return `
                        <div class="d-flex flex-column">
                            <div class="fw-bold text-dark">${escapeHtml(data)}</div>
                            <div class="text-muted small mt-1">${escapeHtml(row.nama_kategori || '-')}</div>
                        </div>
                    `;
                }
            },
            { data: "satuan", className: "text-center", render: d => escapeHtml(d || '-') },
            { 
                data: "awal",
                className: "text-end fw-bold text-success",
                render: (data, type) => type === 'display' ? formatStockQuantity(data) : stockNumber(data)
            },            
            {
                data: "masuk",
                className: "text-end fw-bold text-primary",
                render: (data, type) => type === 'display' ? formatStockQuantity(data) : stockNumber(data)
            },
            {
                data: "keluar",
                className: "text-end fw-bold text-danger",
                render: (data, type) => type === 'display' ? formatStockQuantity(data) : stockNumber(data)
            },
            {
                data: "sisa",
                className: "text-end fw-bold",
                render: function(data, type) {
                    const val = stockNumber(data);
                    if (type !== 'display') return val;
                    const formatted = formatStockQuantity(val);
                    if (val <= 0) {
                        return `<span class="text-danger">${formatted} <i class="fa fa-exclamation-triangle warning-blink ms-1" title="Stok kosong/negatif"></i></span>`;
                    }
                    return `<span class="text-dark">${formatted}</span>`;
                }
            },
            {
                data: "nilai_asset",
                className: "text-end fw-bold text-success",
                render: data => 'Rp ' + Number.parseFloat(data || 0).toLocaleString('id-ID', {minimumFractionDigits: 2})
            }
        ],
        order: [[2, 'asc']], // default order on nama_produk
        lengthMenu: [[10,25,50],[10,25,50]],
        initComplete: function() {
            $('#table-stok td, #table-stok th').addClass('py-2 px-3');
        },
        language: {
            oPaginate: {
                sNext: '<i class="fa fa-angle-right"></i>',
                sPrevious: '<i class="fa fa-angle-left"></i>',
                sFirst: '<i class="fa fa-step-backward"></i>',
                sLast: '<i class="fa fa-step-forward"></i>'
            },
            sEmptyTable: "Tidak ada data stok tersedia",
            sProcessing: "Memproses data..."
        }
    });

    // reload on kategori change
    $('#filter_kategori').on('change', function() {
        stokDT.ajax.reload();
    });
}

$(document).ready(function() {
    load_data_stok();
});
</script>


