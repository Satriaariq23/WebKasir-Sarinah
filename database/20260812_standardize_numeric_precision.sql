-- Standardisasi presisi angka transaksi (schema-only, non-destructive).
-- Target: MariaDB 10.4+.
--
-- Prinsip migrasi:
--   * Tidak mengubah nilai historis.
--   * Tidak menambah CHECK constraint karena data legacy masih memiliki anomali.
--   * Quantity diperlebar ke DECIMAL(20,6).
--   * Harga satuan diperlebar ke DECIMAL(20,6).
--   * Nilai uang/total diperlebar ke DECIMAL(20,2).
--   * Persentase diperlebar ke DECIMAL(24,4). Presisi besar ini sengaja dipilih
--     agar kolom sale DECIMAL(20,0) tidak kehilangan kapasitas integer lama.
--   * Kolom legacy sale_retur_det.ppn tetap dipertahankan agar kode lama tetap
--     berjalan. Kode baru membaca COALESCE(is_ppn, ppn, 0) selama masa transisi.
--
-- PENTING: Jalankan bagian PREFLIGHT terlebih dahulu dan review hasilnya.

-- ============================================================================
-- PREFLIGHT 1: kapasitas target (semua hasil seharusnya 0)
-- ============================================================================

SELECT 'qty_melebihi_decimal_20_6' AS pemeriksaan, COUNT(*) AS jumlah
FROM (
    SELECT stok AS nilai FROM produk
    UNION ALL SELECT mqty2 FROM produk
    UNION ALL SELECT mqty3 FROM produk
    UNION ALL SELECT mqty4 FROM produk
    UNION ALL SELECT minimal_stok FROM produk
    UNION ALL SELECT purc_qty FROM purc_det
    UNION ALL SELECT retur_qty FROM purc_retur_det
    UNION ALL SELECT sale_qty FROM sale_det
    UNION ALL SELECT retur_qty FROM sale_retur_det
    UNION ALL SELECT stok_sistem FROM stok_opname_det
    UNION ALL SELECT stok_fisik FROM stok_opname_det
) q
WHERE ABS(q.nilai) > 99999999999999.999999;

SELECT 'harga_satuan_melebihi_decimal_20_6' AS pemeriksaan, COUNT(*) AS jumlah
FROM (
    SELECT harga_beli AS nilai FROM produk
    UNION ALL SELECT harga_newmodal FROM produk
    UNION ALL SELECT harga_sales FROM produk
    UNION ALL SELECT harga_kasir FROM produk
    UNION ALL SELECT P2 FROM produk
    UNION ALL SELECT P3 FROM produk
    UNION ALL SELECT P4 FROM produk
    UNION ALL SELECT harga_p2 FROM produk
    UNION ALL SELECT purc_harga FROM purc_det
    UNION ALL SELECT retur_harga FROM purc_retur_det
    UNION ALL SELECT sale_harga FROM sale_det
    UNION ALL SELECT sale_capital FROM sale_det
    UNION ALL SELECT sale_purchase_cost FROM sale_det
    UNION ALL SELECT sale_harga FROM sale_retur_det
    UNION ALL SELECT sale_capital FROM sale_retur_det
    UNION ALL SELECT sale_purchase_cost FROM sale_retur_det
) h
WHERE ABS(h.nilai) > 99999999999999.999999;

SELECT 'uang_melebihi_decimal_20_2' AS pemeriksaan, COUNT(*) AS jumlah
FROM (
    SELECT purc_disc AS nilai FROM purc
    UNION ALL SELECT ppn FROM purc
    UNION ALL SELECT purc_total FROM purc
    UNION ALL SELECT bayar FROM purc
    UNION ALL SELECT amount_paid FROM purc
    UNION ALL SELECT change_amount FROM purc
    UNION ALL SELECT ppn FROM purc_det
    UNION ALL SELECT retur_total FROM purc_retur
    UNION ALL SELECT retur_disc FROM purc_retur
    UNION ALL SELECT ppn FROM purc_retur
    UNION ALL SELECT ppn FROM purc_retur_det
    UNION ALL SELECT sale_total FROM sale
    UNION ALL SELECT sale_credit FROM sale
    UNION ALL SELECT sale_disc FROM sale
    UNION ALL SELECT sale_ppn FROM sale
    UNION ALL SELECT bayar FROM sale
    UNION ALL SELECT amount_paid FROM sale
    UNION ALL SELECT change_amount FROM sale
    UNION ALL SELECT sale_pdisc FROM sale_det
    UNION ALL SELECT ppn_item FROM sale_det
    UNION ALL SELECT retur_total FROM sale_retur
    UNION ALL SELECT retur_ppn FROM sale_retur
    UNION ALL SELECT sale_pdisc FROM sale_retur_det
    UNION ALL SELECT total_hutang FROM hutang_supplier
    UNION ALL SELECT total_dibayar FROM hutang_supplier
    UNION ALL SELECT sisa_hutang FROM hutang_supplier
    UNION ALL SELECT jumlah_bayar FROM pembayaran_hutang
    UNION ALL SELECT total_piutang FROM piutang_pelanggan
    UNION ALL SELECT total_diterima FROM piutang_pelanggan
    UNION ALL SELECT sisa_piutang FROM piutang_pelanggan
    UNION ALL SELECT jumlah_penerimaan FROM penerimaan_piutang
    UNION ALL SELECT total_transaksi FROM setoran_kasir
    UNION ALL SELECT jumlah_setoran FROM setoran_kasir
    UNION ALL SELECT selisih FROM setoran_kasir
    UNION ALL SELECT transaction_amount FROM customer_points
    UNION ALL SELECT total_transaction_amount FROM customer_reward_scoring
    UNION ALL SELECT total_transaksi FROM profile_matching_results
) u
WHERE ABS(u.nilai) > 999999999999999999.99;

-- Persentase di luar 0..100 tidak menghalangi widening, tetapi harus direview
-- sebelum CHECK constraint ditambahkan dalam migrasi terpisah.
SELECT sumber, COUNT(*) AS jumlah_di_luar_0_100
FROM (
    SELECT 'produk.disc_max' AS sumber, disc_max AS nilai FROM produk
    UNION ALL SELECT 'produk.margin_min', margin_min FROM produk
    UNION ALL SELECT 'purc_retur.discount_perc', discount_perc FROM purc_retur
    UNION ALL SELECT 'purc_retur.tax_perc', tax_perc FROM purc_retur
    UNION ALL SELECT 'sale.discount_perc', discount_perc FROM sale
    UNION ALL SELECT 'sale.tax_perc', tax_perc FROM sale
) p
WHERE p.nilai IS NOT NULL AND (p.nilai < 0 OR p.nilai > 100)
GROUP BY sumber;

-- ============================================================================
-- PREFLIGHT 2: ringkasan anomali legacy (informatif; tidak diubah migrasi ini)
-- ============================================================================

SELECT
    SUM(stok < 0) AS produk_stok_negatif,
    SUM(harga_beli < 0 OR harga_newmodal < 0 OR harga_sales < 0 OR harga_kasir < 0)
        AS produk_harga_negatif,
    SUM(harga_newmodal < harga_beli) AS newmodal_di_bawah_harga_beli,
    SUM(harga_sales < harga_newmodal) AS harga_sales_di_bawah_newmodal,
    SUM(harga_kasir < harga_newmodal) AS harga_kasir_di_bawah_newmodal
FROM produk;

SELECT
    SUM(purc_qty <= 0) AS qty_pembelian_nonpositif,
    SUM(purc_harga < 0) AS harga_pembelian_negatif,
    SUM(isi IS NULL OR isi <= 0) AS isi_pembelian_invalid
FROM purc_det;

SELECT
    SUM(retur_qty <= 0) AS qty_retur_pembelian_nonpositif,
    SUM(retur_harga < 0) AS harga_retur_pembelian_negatif,
    SUM(isi <= 0) AS isi_retur_pembelian_invalid
FROM purc_retur_det;

SELECT
    SUM(sale_qty = 0) AS qty_penjualan_nol,
    SUM(sale_qty < 0) AS qty_penjualan_minus_terotorisasi,
    SUM(sale_harga < 0) AS harga_penjualan_negatif,
    SUM(isi <= 0) AS isi_penjualan_invalid
FROM sale_det;

-- Rekonsiliasi pembelian: expected_total = subtotal - diskon + PPN detail.
SELECT
    COUNT(*) AS jumlah_pembelian,
    SUM(ABS(a.ppn_header - a.ppn_detail) > 0.01) AS ppn_tidak_sama,
    SUM(ABS(a.total_header - (a.subtotal - a.diskon + a.ppn_detail)) > 0.01)
        AS total_tidak_sama
FROM (
    SELECT
        p.purcid,
        p.ppn AS ppn_header,
        p.purc_total AS total_header,
        p.purc_disc AS diskon,
        COALESCE(SUM(d.purc_qty * d.purc_harga), 0) AS subtotal,
        COALESCE(SUM(d.ppn), 0) AS ppn_detail
    FROM purc p
    LEFT JOIN purc_det d ON d.purc_id = p.purcid
    GROUP BY p.purcid
) a;

-- Rekonsiliasi retur pembelian.
SELECT
    COUNT(*) AS jumlah_retur,
    SUM(ABS(a.ppn_header - a.ppn_detail) > 0.01) AS ppn_tidak_sama,
    SUM(ABS(a.total_header - (a.subtotal - a.diskon + a.ppn_detail)) > 0.01)
        AS total_tidak_sama
FROM (
    SELECT
        r.returid,
        r.ppn AS ppn_header,
        r.retur_total AS total_header,
        r.retur_disc AS diskon,
        COALESCE(SUM(d.retur_qty * d.retur_harga), 0) AS subtotal,
        COALESCE(SUM(d.ppn), 0) AS ppn_detail
    FROM purc_retur r
    LEFT JOIN purc_retur_det d ON d.retur_id = r.returid
    GROUP BY r.returid
) a;

-- Retur aktif tidak boleh melebihi quantity detail pembelian asal.
SELECT COUNT(*) AS detail_pembelian_dengan_retur_berlebih
FROM (
    SELECT
        pd.noid,
        pd.purc_qty,
        COALESCE(SUM(CASE WHEN pr.status = 'ACTIVE' THEN prd.retur_qty ELSE 0 END), 0)
            AS qty_retur_aktif
    FROM purc_det pd
    LEFT JOIN purc_retur_det prd ON prd.purc_detail_id = pd.noid
    LEFT JOIN purc_retur pr ON pr.returid = prd.retur_id
    GROUP BY pd.noid
) a
WHERE a.qty_retur_aktif > a.purc_qty + 0.000001;

-- Rekonsiliasi penjualan mengikuti aturan server saat ini:
-- diskon item/faktur adalah nominal; qty minus menambah diskon menuju nol.
SELECT
    COUNT(*) AS jumlah_penjualan,
    SUM(a.jumlah_detail = 0) AS penjualan_tanpa_detail,
    SUM(ABS(a.sale_ppn - a.ppn_detail) > 1.00) AS ppn_header_tidak_sama,
    SUM(
        ABS(
            a.sale_total -
            CASE
                WHEN a.total_detail < 0 THEN a.total_detail + a.sale_disc
                ELSE a.total_detail - a.sale_disc
            END
        ) > 1.00
    ) AS total_header_tidak_sama
FROM (
    SELECT
        s.saleid,
        s.sale_total,
        s.sale_disc,
        s.sale_ppn,
        COUNT(d.noid) AS jumlah_detail,
        COALESCE(SUM(
            CASE
                WHEN d.sale_qty * d.sale_harga < 0
                    THEN (d.sale_qty * d.sale_harga) + COALESCE(d.sale_pdisc, 0)
                ELSE (d.sale_qty * d.sale_harga) - COALESCE(d.sale_pdisc, 0)
            END
        ), 0) AS total_detail,
        COALESCE(SUM(d.ppn_item), 0) AS ppn_detail
    FROM sale s
    LEFT JOIN sale_det d ON d.sale_id = s.saleid
    GROUP BY s.saleid
) a;

-- Rekonsiliasi saldo hutang/piutang. Status jatuh tempo sengaja tidak diubah.
SELECT
    SUM(ABS(sisa_hutang - (total_hutang - total_dibayar)) > 0.01)
        AS saldo_hutang_tidak_sama
FROM hutang_supplier;

SELECT COUNT(*) AS hutang_dengan_total_pembayaran_tidak_sama
FROM (
    SELECT
        h.id,
        h.total_dibayar,
        COALESCE(SUM(p.jumlah_bayar), 0) AS jumlah_ledger
    FROM hutang_supplier h
    LEFT JOIN pembayaran_hutang p ON p.hutang_id = h.id
    GROUP BY h.id
) a
WHERE ABS(a.total_dibayar - a.jumlah_ledger) > 0.01;

SELECT
    SUM(ABS(sisa_piutang - (total_piutang - total_diterima)) > 0.01)
        AS saldo_piutang_tidak_sama
FROM piutang_pelanggan;

SELECT COUNT(*) AS piutang_dengan_total_penerimaan_tidak_sama
FROM (
    SELECT
        p.id,
        p.total_diterima,
        COALESCE(SUM(r.jumlah_penerimaan), 0) AS jumlah_ledger
    FROM piutang_pelanggan p
    LEFT JOIN penerimaan_piutang r ON r.piutang_id = p.id
    GROUP BY p.id
) a
WHERE ABS(a.total_diterima - a.jumlah_ledger) > 0.01;

-- ============================================================================
-- MIGRASI SCHEMA: widening saja, tanpa UPDATE data historis
-- ============================================================================

ALTER TABLE produk
    MODIFY COLUMN harga_beli DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN harga_newmodal DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN harga_sales DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN harga_kasir DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN P2 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN mqty2 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN P3 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN mqty3 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN P4 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN mqty4 DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN harga_p2 DECIMAL(20,6) NULL DEFAULT 0.000000,
    MODIFY COLUMN stok DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN minimal_stok DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN disc_max DECIMAL(24,4) NULL DEFAULT 0.0000,
    MODIFY COLUMN margin_min DECIMAL(24,4) NULL DEFAULT 0.0000;

ALTER TABLE purc
    MODIFY COLUMN purc_disc DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN ppn DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN purc_total DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN bayar DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN amount_paid DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN change_amount DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE purc_det
    MODIFY COLUMN purc_qty DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN purc_harga DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN ppn DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE purc_retur
    MODIFY COLUMN retur_total DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN retur_disc DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN ppn DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN discount_perc DECIMAL(24,4) NOT NULL DEFAULT 0.0000,
    MODIFY COLUMN tax_perc DECIMAL(24,4) NOT NULL;

ALTER TABLE purc_retur_det
    MODIFY COLUMN retur_qty DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN retur_harga DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN ppn DECIMAL(20,2) NOT NULL DEFAULT 0.00;

ALTER TABLE sale
    MODIFY COLUMN sale_total DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sale_credit DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sale_disc DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sale_ppn DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN discount_perc DECIMAL(24,4) NULL DEFAULT 0.0000,
    MODIFY COLUMN tax_perc DECIMAL(24,4) NULL DEFAULT 0.0000,
    MODIFY COLUMN bayar DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN amount_paid DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN change_amount DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE sale_det
    MODIFY COLUMN sale_qty DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_pdisc DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sale_harga DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_capital DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_purchase_cost DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN ppn_item DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE sale_retur
    MODIFY COLUMN retur_total DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN retur_ppn DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE sale_retur_det
    MODIFY COLUMN retur_qty DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_capital DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_purchase_cost DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_harga DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    MODIFY COLUMN sale_pdisc DECIMAL(20,2) NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS is_ppn TINYINT(1) NULL DEFAULT NULL
        COMMENT 'Flag PPN kanonis, fallback legacy: COALESCE(is_ppn, ppn, 0)'
        AFTER ppn;

ALTER TABLE stok_opname_det
    MODIFY COLUMN stok_sistem DECIMAL(20,6) NULL DEFAULT 0.000000,
    MODIFY COLUMN stok_fisik DECIMAL(20,6) NULL DEFAULT 0.000000;

ALTER TABLE hutang_supplier
    MODIFY COLUMN total_hutang DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN total_dibayar DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sisa_hutang DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE pembayaran_hutang
    MODIFY COLUMN jumlah_bayar DECIMAL(20,2) NOT NULL;

ALTER TABLE piutang_pelanggan
    MODIFY COLUMN total_piutang DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN total_diterima DECIMAL(20,2) NULL DEFAULT 0.00,
    MODIFY COLUMN sisa_piutang DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE penerimaan_piutang
    MODIFY COLUMN jumlah_penerimaan DECIMAL(20,2) NOT NULL;

ALTER TABLE setoran_kasir
    MODIFY COLUMN total_transaksi DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN jumlah_setoran DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN selisih DECIMAL(20,2) NOT NULL DEFAULT 0.00;

ALTER TABLE customer_points
    MODIFY COLUMN transaction_amount DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE customer_reward_scoring
    MODIFY COLUMN total_transaction_amount DECIMAL(20,2) NULL DEFAULT 0.00;

ALTER TABLE profile_matching_results
    MODIFY COLUMN total_transaksi DECIMAL(20,2) NULL DEFAULT 0.00;

-- Tidak ada UPDATE data, backfill, DELETE, CHECK, atau perubahan status di sini.
-- Constraint dan pembersihan histori harus dibuat dalam migrasi terpisah setelah
-- setiap anomali memiliki sumber kebenaran serta backup yang disetujui.
