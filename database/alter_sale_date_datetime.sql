-- Simpan tanggal dan jam transaksi kasir/penjualan.
ALTER TABLE `sale`
    MODIFY `sale_date` datetime DEFAULT NULL COMMENT 'tanggal dan waktu faktur';
