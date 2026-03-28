-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Product location extrafields indexes
--

ALTER TABLE llx_wareloc_product_location_extrafields ADD INDEX idx_wareloc_pl_extrafields_fk_object (fk_object);
