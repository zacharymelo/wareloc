-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Hierarchy level indexes
--

ALTER TABLE llx_wareloc_level ADD INDEX idx_wareloc_level_entrepot (fk_entrepot);
