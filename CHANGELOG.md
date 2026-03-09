# Changelog

All notable changes to `laravel-model-diff` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
