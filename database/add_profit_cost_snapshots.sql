-- Menyimpan harga modal pada saat transaksi agar laporan historis tidak berubah
-- ketika harga pada master produk diperbarui.

ALTER TABLE `sale_det`
    ADD COLUMN IF NOT EXISTS `sale_purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `sale_capital`;

ALTER TABLE `sale_det`
    ADD COLUMN IF NOT EXISTS `is_ppn` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `ppn_item`;

ALTER TABLE `sale_retur_det`
    ADD COLUMN IF NOT EXISTS `sale_purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `sale_capital`;

-- Data lama tidak mempunyai snapshot harga beli. Bekukan harga beli master saat
-- migrasi sebagai baseline terbaik yang tersedia.
UPDATE `sale_det` sd
LEFT JOIN `produk` p ON p.id = sd.produk_id
SET sd.sale_purchase_cost = COALESCE(
    NULLIF(p.harga_beli, 0),
    CASE
        WHEN COALESCE(p.harga_newmodal, 0) > 0
             AND ABS(sd.sale_capital) >= (ABS(p.harga_newmodal) * 50)
            THEN sd.sale_capital / 100
        ELSE sd.sale_capital
    END,
    0
)
WHERE COALESCE(sd.sale_purchase_cost, 0) = 0;

-- Bekukan status PPN barang pada detail transaksi. Nilai ppn_item menjadi
-- petunjuk historis utama; master produk dipakai sebagai fallback data lama.
UPDATE `sale_det` sd
LEFT JOIN `produk` p ON p.id = sd.produk_id
SET sd.is_ppn = CASE
    WHEN ABS(COALESCE(sd.ppn_item, 0)) > 0 THEN 1
    ELSE COALESCE(p.is_ppn, 0)
END;

INSERT INTO `system_info` (`meta_field`, `meta_type`, `meta_value`)
VALUES ('sales_profit_percent', 'number', '100')
ON DUPLICATE KEY UPDATE `meta_type` = VALUES(`meta_type`);

INSERT INTO `system_info` (`meta_field`, `meta_type`, `meta_value`)
VALUES ('ppn_rate', 'number', '11')
ON DUPLICATE KEY UPDATE `meta_type` = VALUES(`meta_type`);

-- Isi PPN detail lama yang masih nol. Harga jual penjualan disimpan sebagai
-- harga inklusif PPN; diskon faktur dialokasikan proporsional ke setiap baris.
UPDATE `sale_det` d
INNER JOIN `sale` s ON s.saleid = d.sale_id
LEFT JOIN `produk` p ON p.id = d.produk_id
LEFT JOIN (
    SELECT sale_id, SUM(sale_qty * sale_harga) AS detail_total
    FROM sale_det
    GROUP BY sale_id
) dt ON dt.sale_id = s.saleid
SET d.ppn_item = ROUND(
    (
        (
            (CASE
                WHEN ABS(COALESCE(s.sale_total, 0)) > 0
                     AND ABS(COALESCE(dt.detail_total, 0)) >= (ABS(s.sale_total) * 50)
                    THEN d.sale_harga / 100
                WHEN COALESCE(p.harga_kasir, 0) > 0
                     AND ABS(d.sale_harga) >= (ABS(p.harga_kasir) * 50)
                    THEN d.sale_harga / 100
                ELSE d.sale_harga
            END) * d.sale_qty
        ) - COALESCE(d.sale_pdisc, 0)
        - CASE
            WHEN COALESCE(s.sale_disc, 0) <> 0
                 AND ABS(COALESCE(s.sale_total, 0) + COALESCE(s.sale_disc, 0)) > 0
                THEN COALESCE(s.sale_disc, 0) * (
                    (
                        (
                            (CASE
                                WHEN ABS(COALESCE(s.sale_total, 0)) > 0
                                     AND ABS(COALESCE(dt.detail_total, 0)) >= (ABS(s.sale_total) * 50)
                                    THEN d.sale_harga / 100
                                WHEN COALESCE(p.harga_kasir, 0) > 0
                                     AND ABS(d.sale_harga) >= (ABS(p.harga_kasir) * 50)
                                    THEN d.sale_harga / 100
                                ELSE d.sale_harga
                            END) * d.sale_qty
                        ) - COALESCE(d.sale_pdisc, 0)
                    ) / (COALESCE(s.sale_total, 0) + COALESCE(s.sale_disc, 0))
                )
            ELSE 0
          END
    )
    -
    (
        (
            (
                (CASE
                    WHEN ABS(COALESCE(s.sale_total, 0)) > 0
                         AND ABS(COALESCE(dt.detail_total, 0)) >= (ABS(s.sale_total) * 50)
                        THEN d.sale_harga / 100
                    WHEN COALESCE(p.harga_kasir, 0) > 0
                         AND ABS(d.sale_harga) >= (ABS(p.harga_kasir) * 50)
                        THEN d.sale_harga / 100
                    ELSE d.sale_harga
                END) * d.sale_qty
            ) - COALESCE(d.sale_pdisc, 0)
            - CASE
                WHEN COALESCE(s.sale_disc, 0) <> 0
                     AND ABS(COALESCE(s.sale_total, 0) + COALESCE(s.sale_disc, 0)) > 0
                    THEN COALESCE(s.sale_disc, 0) * (
                        (
                            (
                                (CASE
                                    WHEN ABS(COALESCE(s.sale_total, 0)) > 0
                                         AND ABS(COALESCE(dt.detail_total, 0)) >= (ABS(s.sale_total) * 50)
                                        THEN d.sale_harga / 100
                                    WHEN COALESCE(p.harga_kasir, 0) > 0
                                         AND ABS(d.sale_harga) >= (ABS(p.harga_kasir) * 50)
                                        THEN d.sale_harga / 100
                                    ELSE d.sale_harga
                                END) * d.sale_qty
                            ) - COALESCE(d.sale_pdisc, 0)
                        ) / (COALESCE(s.sale_total, 0) + COALESCE(s.sale_disc, 0))
                    )
                ELSE 0
              END
        )
        /
        (
            1 + (
                LEAST(
                    100,
                    GREATEST(
                        0,
                        COALESCE(
                            (SELECT CAST(si.meta_value AS DECIMAL(10,4))
                             FROM system_info si
                             WHERE si.meta_field = 'ppn_rate'
                             LIMIT 1),
                            11
                        )
                    )
                ) / 100
            )
        )
    ),
    0
)
WHERE d.is_ppn = 1
  AND ABS(COALESCE(d.ppn_item, 0)) < 0.0001;

UPDATE `sale_det`
SET ppn_item = 0
WHERE is_ppn = 0
  AND ABS(COALESCE(ppn_item, 0)) > 0.0001;

-- Header PPN harus selalu sama dengan total PPN seluruh barang di faktur.
UPDATE `sale` s
LEFT JOIN (
    SELECT sale_id, ROUND(SUM(COALESCE(ppn_item, 0)), 0) AS total_ppn
    FROM sale_det
    GROUP BY sale_id
) x ON x.sale_id = s.saleid
SET s.sale_ppn = COALESCE(x.total_ppn, 0);
