# Integrations — Changelog

## [0.1.0] — 2026-04-26

### Added
- `Elementor` integration — registers a dynamic-tag in the gallery category for the Pro Gallery widget. Two controls: Property (SELECT2 with "Current page" default) and Gallery slug (optional).
- `Elementor/PropertyGalleryDynamicTag` — `Data_Tag` subclass returning `[{id, url}]` for the selected property + gallery.
