-- Copyright (C) 2026 Zachary Melo
--
-- Binloc: optional predefined values per warehouse level (Phase 2)
--

CREATE TABLE IF NOT EXISTS llx_binloc_level_options (
	rowid           INTEGER         NOT NULL AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER         NOT NULL DEFAULT 1,
	fk_entrepot     INTEGER         NOT NULL,
	level_num       SMALLINT        NOT NULL,
	option_value    VARCHAR(64)     NOT NULL,
	position        INTEGER         NOT NULL DEFAULT 0,
	active          TINYINT         NOT NULL DEFAULT 1,
	date_creation   DATETIME        NOT NULL,
	tms             TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat   INTEGER         NOT NULL
) ENGINE=InnoDB;
