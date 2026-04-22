# Changelog

## [1.6.1] - 2026-04-22

### Changed
- Settings page uses Dolibarr's AJAX on/off switch (`ajax_constantonoff`) instead of form-submitted checkboxes — matches the toggle pattern used in the other modules. Values persist immediately; no Save button needed.

## [1.6.0] - 2026-04-15

Initial published release of Binloc.

### Added
- Per-warehouse location hierarchy — each warehouse defines its own level names (Row/Bay/Shelf/Bin, Case/Drawer/Bin, etc.)
- Tabs on product, warehouse, manufacturing order, and reception cards for viewing and assigning locations
- Bulk-assign page for setting locations across many products at once
- Admin setup page for level-name configuration

---

> **History note:** this repository previously hosted a different module called Wareloc (a warehouse-nesting tree builder, versions up to 2.1.2). That codebase was abandoned in favour of Binloc, a ground-up rewrite with a different architecture. The v2.1.3 release tagged against the old Wareloc code was a mistake and has been reverted. If you need the old Wareloc code, check out commit `06f5363`.
