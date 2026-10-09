-- Hubungkan retur pembelian ke header dan detail pembelian asal.
-- Relasi ini diperlukan untuk menghitung qty yang masih boleh diretur.

ALTER TABLE purc_retur
    ADD COLUMN purc_id BIGINT(20) NULL AFTER returid,
    ADD INDEX idx_purc_retur_purc_id (purc_id),
    ADD CONSTRAINT fk_purc_retur_purchase
        FOREIGN KEY (purc_id) REFERENCES purc (purcid);

ALTER TABLE purc_retur_det
    ADD COLUMN purc_detail_id BIGINT(20) NULL AFTER retur_id,
    MODIFY COLUMN ppn DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ADD INDEX idx_purc_retur_det_purchase_detail (purc_detail_id),
    ADD CONSTRAINT fk_purc_retur_det_purchase_detail
        FOREIGN KEY (purc_detail_id) REFERENCES purc_det (noid);

ALTER TABLE purc_retur
    ADD UNIQUE INDEX uq_purc_retur_invoice (retur_invoice);
