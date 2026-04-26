# Integrations — Changelog

## [Unreleased]

### Changed
- Restructured `Integrations/` to a self-contained-module convention. Each integration now lives in its own subdirectory (`Integrations/<Provider>/`) with `Module.php` as the entry point, leaf classes under typed subdirectories (`DynamicTags/`, future `Widgets/` / `Controls/`), and its own four-doc set. The first migration: Elementor (was `Integrations/Elementor.php` + `Integrations/Elementor/PropertyGalleryDynamicTag.php`, now `Integrations/Elementor/Module.php` + `Integrations/Elementor/DynamicTags/PropertyGalleryDynamicTag.php`). Future integrations (Bricks, WPML, etc.) follow the same layout.

## [0.1.0] — 2026-04-26

### Added
- `Elementor` integration — registers a dynamic-tag in the gallery category for the Pro Gallery widget. Two controls: Property (SELECT2 with "Current page" default) and Gallery slug (optional).
- `Elementor/PropertyGalleryDynamicTag` — `Data_Tag` subclass returning `[{id, url}]` for the selected property + gallery.
