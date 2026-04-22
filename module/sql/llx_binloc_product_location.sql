-- Copyright (C) 2026 Zachary Melo
--
-- Binloc: product location assignments (one row per product per warehouse,
-- or per product-lot per warehouse for serialized/batch products)
--

CREATE TABLE IF NOT EXISTS llx_binloc_product_location (
	rowid           INTEGER         NOT NULL AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER         NOT NULL DEFAULT 1,
	fk_product      INTEGER         NOT NULL,
	fk_entrepot     INTEGER         NOT NULL,
	fk_product_lot  INTEGER         DEFAULT NULL,
	level1_value    VARCHAR(64)     DEFAULT NULL,
	level2_value    VARCHAR(64)     DEFAULT NULL,
	level3_value    VARCHAR(64)     DEFAULT NULL,
	level4_value    VARCHAR(64)     DEFAULT NULL,
	level5_value    VARCHAR(64)     DEFAULT NULL,
	level6_value    VARCHAR(64)     DEFAULT NULL,
	note            VARCHAR(255)    DEFAULT NULL,
	date_creation   DATETIME        NOT NULL,
	tms             TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat   INTEGER         NOT NULL,
	fk_user_modif   INTEGER         DEFAULT NULL
) ENGINE=InnoDB;
