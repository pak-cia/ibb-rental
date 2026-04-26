# Changelog

All notable changes to IBB Rentals are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Plugin bootstrap, PSR-4 autoloader, service container.
- HPOS and Cart/Checkout-Blocks compatibility declarations.
- Activation lifecycle: requirements check, schema migrations, secret generation, default settings, recurring job registration.
- Custom post type `ibb_property` with taxonomies (amenities, locations, property types).
- Custom DB tables: `ibb_blocks`, `ibb_rates`, `ibb_bookings`, `ibb_ical_feeds`.
- Logger and centralised hook-name constants.
- Uninstall handler with opt-in data purge.

## [0.1.0] - 2026-04-26

Initial scaffold release.
