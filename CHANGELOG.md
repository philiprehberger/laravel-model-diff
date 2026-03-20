# Changelog

All notable changes to `laravel-model-diff` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.3] - 2026-03-20

### Changed
- Standardize README structure to match template guide

## [1.1.2] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.1.1] - 2026-03-16

### Changed
- Standardize composer.json: add homepage, scripts
- Add Development section to README

## [1.1.0] - 2026-03-12

### Added
- Documentation for unit enum, timestamp, decimal:N, and collection cast types
- Note about order-insensitive associative array comparison
- 15 new tests covering integer/float casts, null transitions, collection/timestamp/decimal casts, malformed JSON, ignoring() immutability, attribute changes, and fromDirty with numeric casts

## [1.0.0] - 2026-03-09

### Added
- `ModelDiff::compare()` to diff two persisted model instances.
- `ModelDiff::fromDirty()` to diff an unsaved model against its original DB values.
- `ModelDiff::ignoring()` to exclude additional attributes at call-site.
- `DiffResult` value object with `hasChanges()`, `changedAttributes()`, `toArray()`, and `toHumanReadable()`.
- `AttributeChange` value object exposing `attribute`, `old`, `new`, and `label`.
- `HasDiffLabels` trait for models to declare human-readable attribute labels.
- Cast-aware comparison for dates, JSON/arrays, booleans, integers, floats, and backed enums.
- Configurable ignored attributes (defaults: `id`, `created_at`, `updated_at`).
- Configurable date format for human-readable output.
- `ModelDiff` facade for ergonomic access.
- Auto-discovery via Laravel package discovery.
- Publishable config file (`model-diff.php`).
