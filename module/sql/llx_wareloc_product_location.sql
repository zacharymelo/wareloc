-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Product location assignments (main business object)
--

CREATE TABLE llx_wareloc_product_location(
    rowid           INTEGER       AUTO_INCREMENT PRIMARY KEY,
    ref             VARCHAR(30)   DEFAULT NULL,
    entity          INTEGER       NOT NULL DEFAULT 1,
    fk_product      INTEGER       NOT NULL,
    fk_entrepot     INTEGER       NOT NULL,
    level_1         VARCHAR(128),
    level_2         VARCHAR(128),
    level_3         VARCHAR(128),
    level_4         VARCHAR(128),
    level_5         VARCHAR(128),
    level_6         VARCHAR(128),
    qty             DOUBLE        DEFAULT 0,
    is_default      SMALLINT      NOT NULL DEFAULT 0,
    fk_reception    INTEGER,
    note_private    TEXT,
    note_public     TEXT,
    status          INTEGER       NOT NULL DEFAULT 0,
    date_creation   DATETIME      NOT NULL,
    date_validation DATETIME,
    tms             TIMESTAMP,
    fk_user_creat   INTEGER,
    fk_user_valid   INTEGER,
    fk_user_modif   INTEGER,
    import_key      VARCHAR(14)
) ENGINE=innodb;
