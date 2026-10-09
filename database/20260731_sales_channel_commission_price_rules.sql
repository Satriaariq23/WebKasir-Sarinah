-- Penertiban harga, kanal transaksi, dan persentase profit per sales.
-- Aman dijalankan ulang pada MariaDB 10.4+.

ALTER TABLE `sale`
    ADD COLUMN IF NOT EXISTS `sale_channel` VARCHAR(10) NOT NULL DEFAULT 'sales'
    AFTER `sale_invoice`;

UPDATE `sale`
SET `sale_channel` = CASE
    WHEN `sale_invoice` LIKE 'KSR-%' THEN 'kasir'
    WHEN `sale_channel` = 'online' THEN 'online'
    ELSE 'sales'
END;

CREATE INDEX IF NOT EXISTS `idx_sale_channel_date`
    ON `sale` (`sale_channel`, `sale_date`);

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `sales_profit_percent` DECIMAL(5,2) NULL DEFAULT NULL
    AFTER `level_id`;

UPDATE `users`
SET `sales_profit_percent` = LEAST(
    100,
    GREATEST(
        0,
        COALESCE(
            (SELECT CAST(`meta_value` AS DECIMAL(5,2))
             FROM `system_info`
             WHERE `meta_field` = 'sales_profit_percent'
             LIMIT 1),
            100
        )
    )
)
WHERE `level_id` = 3
  AND `sales_profit_percent` IS NULL;

-- Simpan nilai lama terlebih dahulu agar normalisasi harga dapat diaudit/dipulihkan.
CREATE TABLE IF NOT EXISTS `produk_harga_cleanup_backup_20260731` (
    `produk_id` INT(11) NOT NULL,
    `harga_beli` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `harga_newmodal` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `harga_sales` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `harga_kasir` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `P2` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `P3` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `P4` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `backed_up_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`produk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `produk_harga_cleanup_backup_20260731` (
    `produk_id`, `harga_beli`, `harga_newmodal`, `harga_sales`, `harga_kasir`,
    `P2`, `P3`, `P4`
)
SELECT
    `id`, COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0),
    COALESCE(`harga_sales`, 0), COALESCE(`harga_kasir`, 0),
    COALESCE(`P2`, 0), COALESCE(`P3`, 0), COALESCE(`P4`, 0)
FROM `produk`
WHERE COALESCE(`harga_beli`, 0) < 0
   OR COALESCE(`harga_newmodal`, 0) < GREATEST(COALESCE(`harga_beli`, 0), 0)
   OR COALESCE(`harga_sales`, 0) < 0
   OR (COALESCE(`harga_sales`, 0) > 0 AND COALESCE(`harga_sales`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0))
   OR COALESCE(`harga_kasir`, 0) < 0
   OR (COALESCE(`harga_kasir`, 0) > 0 AND COALESCE(`harga_kasir`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0))
   OR COALESCE(`P2`, 0) < 0
   OR (COALESCE(`P2`, 0) > 0 AND COALESCE(`P2`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0))
   OR COALESCE(`P3`, 0) < 0
   OR (COALESCE(`P3`, 0) > 0 AND COALESCE(`P3`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0))
   OR COALESCE(`P4`, 0) < 0
   OR (COALESCE(`P4`, 0) > 0 AND COALESCE(`P4`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0));

UPDATE `produk`
SET
    `harga_beli` = GREATEST(COALESCE(`harga_beli`, 0), 0),
    `harga_newmodal` = GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0),
    `harga_sales` = CASE
        WHEN COALESCE(`harga_sales`, 0) < 0 THEN 0
        WHEN COALESCE(`harga_sales`, 0) > 0
             AND COALESCE(`harga_sales`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
            THEN GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
        ELSE COALESCE(`harga_sales`, 0)
    END,
    `harga_kasir` = CASE
        WHEN COALESCE(`harga_kasir`, 0) < 0 THEN 0
        WHEN COALESCE(`harga_kasir`, 0) > 0
             AND COALESCE(`harga_kasir`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
            THEN GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
        ELSE COALESCE(`harga_kasir`, 0)
    END,
    `P2` = CASE
        WHEN COALESCE(`P2`, 0) < 0 THEN 0
        WHEN COALESCE(`P2`, 0) > 0
             AND COALESCE(`P2`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
            THEN GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
        ELSE COALESCE(`P2`, 0)
    END,
    `P3` = CASE
        WHEN COALESCE(`P3`, 0) < 0 THEN 0
        WHEN COALESCE(`P3`, 0) > 0
             AND COALESCE(`P3`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
            THEN GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
        ELSE COALESCE(`P3`, 0)
    END,
    `P4` = CASE
        WHEN COALESCE(`P4`, 0) < 0 THEN 0
        WHEN COALESCE(`P4`, 0) > 0
             AND COALESCE(`P4`, 0) < GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
            THEN GREATEST(COALESCE(`harga_beli`, 0), COALESCE(`harga_newmodal`, 0), 0)
        ELSE COALESCE(`P4`, 0)
    END
WHERE `id` IN (SELECT `produk_id` FROM `produk_harga_cleanup_backup_20260731`);

DROP TRIGGER IF EXISTS `trg_produk_price_rules_bi`;
DROP TRIGGER IF EXISTS `trg_produk_price_rules_bu`;
DROP TRIGGER IF EXISTS `trg_users_sales_percent_bi`;
DROP TRIGGER IF EXISTS `trg_users_sales_percent_bu`;
DROP TRIGGER IF EXISTS `trg_sale_channel_bi`;
DROP TRIGGER IF EXISTS `trg_sale_channel_bu`;

DELIMITER $$

CREATE TRIGGER `trg_produk_price_rules_bi`
BEFORE INSERT ON `produk`
FOR EACH ROW
BEGIN
    IF COALESCE(NEW.harga_beli, 0) < 0
       OR COALESCE(NEW.harga_newmodal, 0) < COALESCE(NEW.harga_beli, 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New Modal tidak boleh kurang dari Harga Beli';
    END IF;
    IF COALESCE(NEW.harga_sales, 0) < 0
       OR COALESCE(NEW.harga_kasir, 0) < 0
       OR COALESCE(NEW.P2, 0) < 0
       OR COALESCE(NEW.P3, 0) < 0
       OR COALESCE(NEW.P4, 0) < 0
       OR (COALESCE(NEW.harga_sales, 0) > 0 AND NEW.harga_sales < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.harga_kasir, 0) > 0 AND NEW.harga_kasir < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P2, 0) > 0 AND NEW.P2 < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P3, 0) > 0 AND NEW.P3 < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P4, 0) > 0 AND NEW.P4 < COALESCE(NEW.harga_newmodal, 0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Harga jual aktif tidak boleh kurang dari New Modal';
    END IF;
END$$

CREATE TRIGGER `trg_produk_price_rules_bu`
BEFORE UPDATE ON `produk`
FOR EACH ROW
BEGIN
    IF COALESCE(NEW.harga_beli, 0) < 0
       OR COALESCE(NEW.harga_newmodal, 0) < COALESCE(NEW.harga_beli, 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New Modal tidak boleh kurang dari Harga Beli';
    END IF;
    IF COALESCE(NEW.harga_sales, 0) < 0
       OR COALESCE(NEW.harga_kasir, 0) < 0
       OR COALESCE(NEW.P2, 0) < 0
       OR COALESCE(NEW.P3, 0) < 0
       OR COALESCE(NEW.P4, 0) < 0
       OR (COALESCE(NEW.harga_sales, 0) > 0 AND NEW.harga_sales < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.harga_kasir, 0) > 0 AND NEW.harga_kasir < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P2, 0) > 0 AND NEW.P2 < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P3, 0) > 0 AND NEW.P3 < COALESCE(NEW.harga_newmodal, 0))
       OR (COALESCE(NEW.P4, 0) > 0 AND NEW.P4 < COALESCE(NEW.harga_newmodal, 0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Harga jual aktif tidak boleh kurang dari New Modal';
    END IF;
END$$

CREATE TRIGGER `trg_users_sales_percent_bi`
BEFORE INSERT ON `users`
FOR EACH ROW
BEGIN
    IF NEW.sales_profit_percent IS NOT NULL
       AND (NEW.sales_profit_percent < 0 OR NEW.sales_profit_percent > 100) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Persentase profit sales harus 0 sampai 100';
    END IF;
END$$

CREATE TRIGGER `trg_users_sales_percent_bu`
BEFORE UPDATE ON `users`
FOR EACH ROW
BEGIN
    IF NEW.sales_profit_percent IS NOT NULL
       AND (NEW.sales_profit_percent < 0 OR NEW.sales_profit_percent > 100) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Persentase profit sales harus 0 sampai 100';
    END IF;
END$$

CREATE TRIGGER `trg_sale_channel_bi`
BEFORE INSERT ON `sale`
FOR EACH ROW
BEGIN
    IF NEW.sale_invoice LIKE 'KSR-%' THEN
        SET NEW.sale_channel = 'kasir';
    ELSEIF NEW.sale_channel NOT IN ('sales', 'online') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kanal transaksi harus sales, online, atau kasir';
    END IF;
END$$

CREATE TRIGGER `trg_sale_channel_bu`
BEFORE UPDATE ON `sale`
FOR EACH ROW
BEGIN
    IF NEW.sale_invoice LIKE 'KSR-%' THEN
        SET NEW.sale_channel = 'kasir';
    ELSEIF NEW.sale_channel NOT IN ('sales', 'online') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kanal transaksi harus sales, online, atau kasir';
    END IF;
END$$

DELIMITER ;
