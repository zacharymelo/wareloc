-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Default location per product per warehouse
--

CREATE TABLE llx_wareloc_product_default(
    rowid           INTEGER       AUTO_INCREMENT PRIMARY KEY,
    entity          INTEGER       NOT NULL DEFAULT 1,
    fk_product      INTEGER       NOT NULL,
    fk_entrepot     INTEGER       NOT NULL,
    level_1         VARCHAR(128),
    level_2         VARCHAR(128),
    level_3         VARCHAR(128),
    level_4         VARCHAR(128),
    level_5         VARCHAR(128),
    level_6         VARCHAR(128),
    date_creation   DATETIME      NOT NULL,
    fk_user_creat   INTEGER,
    tms             TIMESTAMP,
    import_key      VARCHAR(14)
) ENGINE=innodb;
