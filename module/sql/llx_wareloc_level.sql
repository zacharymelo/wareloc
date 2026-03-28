-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Hierarchy level configuration
--

CREATE TABLE llx_wareloc_level(
    rowid           INTEGER       AUTO_INCREMENT PRIMARY KEY,
    entity          INTEGER       NOT NULL DEFAULT 1,
    position        SMALLINT      NOT NULL,
    code            VARCHAR(30)   NOT NULL,
    label           VARCHAR(128)  NOT NULL,
    datatype        VARCHAR(20)   NOT NULL DEFAULT 'freetext',
    list_values     TEXT,
    required        SMALLINT      NOT NULL DEFAULT 0,
    active          SMALLINT      NOT NULL DEFAULT 1,
    date_creation   DATETIME,
    tms             TIMESTAMP,
    fk_user_creat   INTEGER,
    import_key      VARCHAR(14)
) ENGINE=innodb;
