-- Samakan seluruh kode pelanggan non-member menjadi SL + nomor urut 4 digit.
-- Urutan lama dipertahankan berdasarkan primary key pelanggan.id.

-- Simpan pemetaan lama satu kali agar kode awal dapat diaudit/dipulihkan.
CREATE TABLE IF NOT EXISTS pelanggan_kode_backup_20260811 (
    pelanggan_id INT NOT NULL PRIMARY KEY,
    kode_lama VARCHAR(20) NULL,
    nama VARCHAR(50) NOT NULL,
    backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO pelanggan_kode_backup_20260811 (pelanggan_id, kode_lama, nama)
SELECT id, kode, nama
FROM pelanggan
WHERE UPPER(COALESCE(type_customer, '')) = 'PELANGGAN'
  AND (kode IS NULL OR kode NOT LIKE 'MB%');

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_pelanggan_sl_codes (
    id INT NOT NULL PRIMARY KEY,
    new_code VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO tmp_pelanggan_sl_codes (id, new_code)
SELECT numbered.id,
       CONCAT(
           'SL',
           RIGHT(
               CONCAT('0000', numbered.sequence_number),
               GREATEST(4, CHAR_LENGTH(numbered.sequence_number))
           )
       ) AS new_code
FROM (
    SELECT id, ROW_NUMBER() OVER (ORDER BY id ASC) AS sequence_number
    FROM pelanggan
    WHERE UPPER(COALESCE(type_customer, '')) = 'PELANGGAN'
      AND (kode IS NULL OR kode NOT LIKE 'MB%')
) AS numbered;

-- Gunakan kode sementara agar penomoran ulang tetap aman jika kelak ada unique index.
UPDATE pelanggan AS p
INNER JOIN tmp_pelanggan_sl_codes AS mapping ON mapping.id = p.id
SET p.kode = CONCAT('__TMP_SL_', p.id);

UPDATE pelanggan AS p
INNER JOIN tmp_pelanggan_sl_codes AS mapping ON mapping.id = p.id
SET p.kode = mapping.new_code;

DROP TEMPORARY TABLE tmp_pelanggan_sl_codes;

COMMIT;
