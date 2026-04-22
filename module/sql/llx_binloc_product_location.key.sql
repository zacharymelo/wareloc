-- Copyright (C) 2026 Zachary Melo
--
-- Binloc: product location indexes and constraints
-- Note: uniqueness for (product, warehouse, lot) is enforced in business logic
-- because MySQL treats NULL as distinct in unique indexes.
--

ALTER TABLE llx_binloc_product_location ADD INDEX idx_binloc_prod_loc (entity, fk_product, fk_entrepot, fk_product_lot);
ALTER TABLE llx_binloc_product_location ADD INDEX idx_binloc_prod_loc_product (fk_product);
ALTER TABLE llx_binloc_product_location ADD INDEX idx_binloc_prod_loc_entrepot (fk_entrepot);
ALTER TABLE llx_binloc_product_location ADD INDEX idx_binloc_prod_loc_lot (fk_product_lot);
ALTER TABLE llx_binloc_product_location ADD CONSTRAINT fk_binloc_prod_loc_product FOREIGN KEY (fk_product) REFERENCES llx_product (rowid) ON DELETE CASCADE;
ALTER TABLE llx_binloc_product_location ADD CONSTRAINT fk_binloc_prod_loc_entrepot FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot (rowid) ON DELETE CASCADE;
ALTER TABLE llx_binloc_product_location ADD CONSTRAINT fk_binloc_prod_loc_lot FOREIGN KEY (fk_product_lot) REFERENCES llx_product_lot (rowid) ON DELETE CASCADE;
