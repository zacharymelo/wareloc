-- Copyright (C) 2026 Zachary Melo
--
-- Binloc: per-warehouse level configuration
--

CREATE TABLE IF NOT EXISTS llx_binloc_warehouse_levels (
	rowid           INTEGER         NOT NULL AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER         NOT NULL DEFAULT 1,
	fk_entrepot     INTEGER         NOT NULL,
	level_num       SMALLINT        NOT NULL,
	label           VARCHAR(64)     NOT NULL,
	datatype        VARCHAR(16)     NOT NULL DEFAULT 'text',
	list_values     VARCHAR(1024)   DEFAULT NULL,
	active          TINYINT         NOT NULL DEFAULT 1,
	date_creation   DATETIME        NOT NULL,
	tms             TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat   INTEGER         NOT NULL,
	fk_user_modif   INTEGER         DEFAULT NULL
) ENGINE=InnoDB;
