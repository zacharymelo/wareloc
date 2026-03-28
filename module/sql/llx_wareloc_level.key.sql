-- Copyright (C) 2026 Zachary Melo
--
-- Wareloc module - Hierarchy level indexes
--

ALTER TABLE llx_wareloc_level ADD UNIQUE INDEX uk_wareloc_level_pos (entity, position);
ALTER TABLE llx_wareloc_level ADD UNIQUE INDEX uk_wareloc_level_code (entity, code);
