-- Menyatukan alur stok transaksi dan sumber pengaturan/saldo poin.
-- Aman dijalankan ulang pada MariaDB 10.4+.

ALTER TABLE `sale`
    ADD COLUMN IF NOT EXISTS `stock_applied` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `sale_status`;

CREATE INDEX IF NOT EXISTS `idx_sale_stock_applied`
    ON `sale` (`stock_applied`, `sale_status`);

CREATE UNIQUE INDEX IF NOT EXISTS `uk_piutang_ref_penjualan`
    ON `piutang_pelanggan` (`ref_penjualan`);

ALTER TABLE `customer_points`
    ADD COLUMN IF NOT EXISTS `reward_redemption_id` INT(11) NULL
    AFTER `sale_id`;

CREATE INDEX IF NOT EXISTS `idx_customer_points_redemption`
    ON `customer_points` (`reward_redemption_id`, `point_type`);

-- Implementasi Kasir lama memang sudah mengurangi stok. Transaksi Sales/Online
-- lama sengaja dibiarkan 0 karena belum ada bukti stoknya pernah dikurangi.
UPDATE `sale`
SET `stock_applied` = 1
WHERE `sale_invoice` LIKE 'KSR-%'
  AND `sale_status` = 'ACTIVE'
  AND `status` = 1
  AND `stock_applied` = 0;

-- customer_points menjadi ledger poin kanonis. Data legacy dipindahkan sekali
-- tanpa menggandakan transaksi yang sudah tercatat pada ledger baru.
INSERT INTO `customer_points` (
    `pelanggan_id`, `sale_id`, `point_type`, `points`,
    `transaction_amount`, `description`, `created_at`
)
SELECT
    mp.`pelanggan_id`, mp.`transaksi_id`, 'EARN', mp.`poin`,
    COALESCE(s.`sale_total`, 0),
    CONCAT('Migrasi poin transaksi #', mp.`transaksi_id`),
    COALESCE(mp.`created_at`, NOW())
FROM `member_point` mp
LEFT JOIN `sale` s ON s.`saleid` = mp.`transaksi_id`
WHERE NOT EXISTS (
    SELECT 1
    FROM `customer_points` cp
    WHERE cp.`pelanggan_id` = mp.`pelanggan_id`
      AND cp.`sale_id` = mp.`transaksi_id`
      AND cp.`point_type` = 'EARN'
);

-- Poin dari transaksi yang sudah void/dihapus tidak boleh tetap masuk saldo.
DELETE cp
FROM `customer_points` cp
LEFT JOIN `sale` s ON s.`saleid` = cp.`sale_id`
WHERE cp.`point_type` = 'EARN'
  AND cp.`sale_id` IS NOT NULL
  AND (s.`saleid` IS NULL OR s.`sale_status` <> 'ACTIVE');

-- Pengaturan pada reward_settings dipakai oleh semua kanal transaksi.
INSERT INTO `reward_settings` (`setting_key`, `setting_value`, `description`)
VALUES (
    'max_points_per_transaction',
    COALESCE((SELECT `setting_value` FROM `point_settings` WHERE `setting_key` = 'max_poin_per_transaksi' LIMIT 1), '1000'),
    'Batas maksimum poin dari satu transaksi'
)
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

INSERT INTO `point_settings` (`setting_key`, `setting_value`, `tipe`, `deskripsi`)
VALUES
    ('min_belanja_point', COALESCE((SELECT `setting_value` FROM `reward_settings` WHERE `setting_key` = 'min_transaction_point' LIMIT 1), '50000'), 'number', 'Minimum belanja untuk memperoleh poin'),
    ('point_nilai_tukar', COALESCE((SELECT `setting_value` FROM `reward_settings` WHERE `setting_key` = 'point_per_rupiah' LIMIT 1), '10000'), 'number', 'Nilai rupiah untuk memperoleh satu poin'),
    ('status_poin_system', CASE WHEN COALESCE((SELECT `setting_value` FROM `reward_settings` WHERE `setting_key` = 'reward_active' LIMIT 1), '1') = '1' THEN 'active' ELSE 'inactive' END, 'text', 'Status sistem poin')
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `tipe` = VALUES(`tipe`),
    `deskripsi` = VALUES(`deskripsi`);
