-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Product location extrafields
--

CREATE TABLE llx_wareloc_product_location_extrafields(
    rowid      INTEGER AUTO_INCREMENT PRIMARY KEY,
    tms        TIMESTAMP,
    fk_object  INTEGER NOT NULL,
    import_key VARCHAR(14)
) ENGINE=innodb;
