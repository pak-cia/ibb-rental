# Integrations / Elementor — Changelog

## [Unreleased]

### Changed
- Restructured into a self-contained module under `Integrations/Elementor/`. Entry point is `Module.php` (was `Integrations/Elementor.php`); leaf classes live under subdirectories (`DynamicTags/`, future `Widgets/` / `Controls/`). Every integration in the plugin will follow this pattern.

## [0.1.0] — 2026-04-26

### Added
- `Module` (originally `Elementor` at `Integrations/Elementor.php`) — registers a dynamic-tag group + tag classes via `elementor/dynamic_tags/register`. Hosts `property_options()` helper used by tag controls.
- `DynamicTags/PropertyGalleryDynamicTag` — `Data_Tag` returning `[{id, url}]` for the gallery dynamic-tag category. Two controls: Property (SELECT2 with "Current page" default) and Gallery slug.
