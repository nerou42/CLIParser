# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/nerou42/CLIParser/compare/v0.2.1...master)

### Added

- Support for options provided multiple times each for array input

### Removed

- Support for PHP 8.0
- composer.lock from .gitignore


## [v0.2.1](https://github.com/nerou42/CLIParser/compare/v0.2.0...v0.2.1) - 2026-01-25

### Fixed

- Flag validation if value of `null` is not allowed


## [v0.2.0](https://github.com/nerou42/CLIParser/compare/v0.1.3...v0.2.0) - 2025-12-10

### Added

- Usage documentation generation


## [v0.1.3](https://github.com/nerou42/CLIParser/compare/v0.1.2...v0.1.3) - 2025-12-10

### Added

- PHP 8.5 to CI

### Fixed

- Default value being ignored if it is no string


## [v0.1.2](https://github.com/nerou42/CLIParser/compare/v0.1.1...v0.1.2) - 2025-11-06

### Added

- Rector to CI

### Fixed

- Deprecations in tests


## [v0.1.1](https://github.com/nerou42/CLIParser/compare/v0.1...v0.1.1) - 2024-03-06

### Added

- Polyfill for PHP 8.0

### Changed

- Update CI actions


## [v0.1](https://github.com/nerou42/CLIParser/releases/tag/v0.1) - 2023-12-18

### Added

- Basic CLI argument parser
