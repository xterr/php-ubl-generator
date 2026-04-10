# PHP UBL Generator

Generate typed PHP 8.4+ classes from OASIS UBL 2.x XSD schemas.

## What It Does

- Reads UBL XSD schemas (2.1, 2.2, 2.3, 2.4)
- Generates `final class` PHP files with private properties, typed getters, fluent setters
- Adds PHP 8 attributes for XML mapping (`#[XmlElement]`, `#[XmlRoot]`, etc.)
- Inline setter validation (type checks, patterns, enumerations)
- Configurable via YAML (namespace, include/exclude filters, naming overrides)
- Bundled UBL XSD schemas — no downloads needed

## Installation

```bash
composer require --dev xterr/php-ubl-generator
```

Generated classes depend on the runtime package:

```bash
composer require xterr/php-ubl
```

## Quick Start

```bash
# Generate all UBL 2.4 types with default config
php vendor/bin/console ubl:generate --force

# Dry-run (show what would be generated)
php vendor/bin/console ubl:generate

# Custom config
php vendor/bin/console ubl:generate --config=ubl-generator.yaml --force

# Override namespace
php vendor/bin/console ubl:generate --namespace='App\Ubl' --output-dir=src/Ubl --force
```

## Configuration

Copy the default config:

```bash
cp vendor/xterr/php-ubl-generator/resources/config/ubl-generator.yaml.dist ubl-generator.yaml
```

Key options:

- `schema_version` — UBL version (2.1, 2.2, 2.3, 2.4)
- `namespace` — Root PHP namespace
- `output_dir` — Where to write generated files
- `include` / `exclude` — Glob patterns to filter types
- `class_name_overrides` / `property_name_overrides` — Custom naming
- `generate_validation` — Enable/disable setter validation

## Architecture

Two packages:

- **xterr/php-ubl-generator** (this) — Dev tool that reads XSD and emits PHP
- **xterr/php-ubl** — Runtime (XML mapping attributes, serializer/deserializer)

## Requirements

- PHP ^8.4
- ext-dom, ext-libxml, ext-mbstring
