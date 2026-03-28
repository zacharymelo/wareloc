-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Product location indexes
--

ALTER TABLE llx_wareloc_product_location ADD INDEX idx_wareloc_pl_product (fk_product);
ALTER TABLE llx_wareloc_product_location ADD INDEX idx_wareloc_pl_entrepot (fk_entrepot);
ALTER TABLE llx_wareloc_product_location ADD INDEX idx_wareloc_pl_status (status);
ALTER TABLE llx_wareloc_product_location ADD INDEX idx_wareloc_pl_default (fk_product, fk_entrepot, is_default);
ALTER TABLE llx_wareloc_product_location ADD INDEX idx_wareloc_pl_reception (fk_reception);
