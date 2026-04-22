-- Copyright (C) 2026 Zachary Melo
--
-- Binloc: level options indexes and constraints
--

ALTER TABLE llx_binloc_level_options ADD UNIQUE INDEX uk_binloc_lvl_opt (entity, fk_entrepot, level_num, option_value);
ALTER TABLE llx_binloc_level_options ADD INDEX idx_binloc_lvl_opt_entrepot (fk_entrepot);
ALTER TABLE llx_binloc_level_options ADD CONSTRAINT fk_binloc_lvl_opt_entrepot FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot (rowid) ON DELETE CASCADE;
