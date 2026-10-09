ALTER TABLE `log_aktivitas`
  MODIFY `aksi` varchar(50) NOT NULL DEFAULT 'OTHER';

UPDATE `log_aktivitas`
SET `aksi` = 'OTHER'
WHERE `aksi` IS NULL OR `aksi` = '';
