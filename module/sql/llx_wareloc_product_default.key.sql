-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Product default location indexes
--

ALTER TABLE llx_wareloc_product_default ADD UNIQUE INDEX uk_wareloc_proddefault (fk_product, fk_entrepot, entity);
ALTER TABLE llx_wareloc_product_default ADD INDEX idx_wareloc_proddefault_product (fk_product);
ALTER TABLE llx_wareloc_product_default ADD INDEX idx_wareloc_proddefault_entrepot (fk_entrepot);
