<?php

/**
 * ==========================================================
 *  FILE: admin/laporan/rekap_harian.php
 *  DESCRIPTION: Rekap Poin Harian
 * ==========================================================
 */
?>

<div class="content-wrapper py-3 px-3">
    <div class="card">
        <div class="card-header">
            <h3>Rekap Poin</h3>
        </div>
        <div class="card-body">
            <!-- Navigation Tabs -->
            <div class="mb-3">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url ?>admin/?page=laporan/rekap_harian">Rekap Harian</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url ?>admin/?page=laporan/ranking_point">Ranking Point</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url ?>admin/?page=laporan/rekap_point_member">Rekap Point Member</a>
                    </li>
                </ul>
            </div>
            <div class="mb-3">
                <label>Filter Tanggal</label>
                <input type="date" id="filter-date" class="form-control" value="<?= date('Y-m-d') ?>">
                <button class="btn btn-primary mt-2" onclick="load_rekap_poin()">Filter</button>
            </div>
            <table id="table_rekap" class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Total Poin Diberikan</th>
                        <th>Total Poin Digunakan</th>
                        <th>Transaksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script>
    let table_rekap;

    // Load rekap poin
    function load_rekap_poin() {
        const filter_date = $('#filter-date').val();

        if (table_rekap) table_rekap.destroy();

        table_rekap = $('#table_rekap').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "<?= base_url ?>classes/Laporan.php?f=get_rekap_poin",
                type: "POST",
                data: {
                    filter_date: filter_date
                }
            },
            columns: [{
                    data: 'no'
                },
                {
                    data: 'tanggal'
                },
                {
                    data: 'total_poin_diberikan'
                },
                {
                    data: 'total_poin_digunakan'
                },
                {
                    data: 'total_transaksi'
                }
            ]
        });
    }

    $(function() {
        load_rekap_poin();
    });
</script>