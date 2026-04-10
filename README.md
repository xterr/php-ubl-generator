# PHP UBL Generator

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![CI](https://github.com/xterr/php-ubl-generator/actions/workflows/ci.yml/badge.svg)](https://github.com/xterr/php-ubl-generator/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/xterr/php-ubl-generator)](https://packagist.org/packages/xterr/php-ubl-generator)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org/)

XSD-to-PHP code generator for UBL 2.x documents. Reads OASIS UBL schemas and emits typed PHP 8.2+ classes with XML mapping attributes, optional codelist enums from Genericode files, and configurable property bindings.

## Features

- **UBL 2.x Schemas** — Supports versions 2.1, 2.2, 2.3, 2.4
- **All UBL Namespaces** — CBC, CAC, EXT, SBC, SIG, SAC with universal resolution
- **Typed Classes** — `final class` with private properties, typed getters, fluent setters
- **PHP 8 Attributes** — `#[XmlRoot]`, `#[XmlType]`, `#[XmlElement]`, `#[XmlAttribute]`, `#[XmlValue]`
- **Codelist Enums** — Parse OASIS Genericode (`.gc`) files into `string`-backed PHP enums with `#[CodelistMeta]`
- **Union Type Bindings** — Bind multiple codelist enums to a single property as PHP union types
- **Setter Validation** — Inline type checks, patterns, and enumeration validation
- **Validator Attributes** — Optional `symfony/validator` `#[Assert\*]` attribute generation
- **Configurable** — YAML config with namespace overrides, include/exclude filters, naming customization
- **Bring Your Own Schemas** — Download UBL schemas from [OASIS](http://docs.oasis-open.org/ubl/os-UBL-2.4/) and point the generator at them

## Installation

```bash
composer require --dev xterr/php-ubl-generator
```

Generated classes depend on the runtime package:

```bash
composer require xterr/php-ubl
```

**Requirements:**
- PHP 8.2 or higher
- `ext-dom`
- `ext-libxml`
- `ext-mbstring`

## Quick Start

### Generate classes from UBL schemas

```bash
# Dry-run (show what would be generated)
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd

# Generate files
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd --force

# With codelist enums
php vendor/bin/php-ubl-generator ubl:generate \
  --schema-dir=path/to/UBL-2.4/xsd \
  --codelist-dir=path/to/codelists/gc \
  --force

# From YAML config
php vendor/bin/php-ubl-generator ubl:generate --config=ubl-generator.yaml --force

# Override namespace and output
php vendor/bin/php-ubl-generator ubl:generate \
  --schema-dir=path/to/UBL-2.4/xsd \
  --namespace='App\Ubl' \
  --output-dir=src/Ubl \
  --force
```

### Generated output structure

```
src/
├── Cbc/            # Leaf types (Amount, Code, Text, Identifier, ...)
├── Cac/            # Aggregate types (Party, Address, TaxTotal, ...)
├── Doc/            # Document roots (Invoice, CreditNote, ...)
├── Enum/           # XSD restriction enumerations
└── Codelist/       # Codelist enums from .gc files (when configured)
```

## Configuration

Copy the default config and customize:

```bash
cp vendor/xterr/php-ubl-generator/resources/config/ubl-generator.yaml.dist ubl-generator.yaml
```

```yaml
schema_version: '2.4'
schema_dir: '/path/to/UBL-2.4/xsd'
output_dir: 'src'
namespace: 'App\UBL'

namespaces:
  cbc: 'Cbc'
  cac: 'Cac'
  doc: 'Doc'
  enum: 'Enum'

include: []                    # Glob patterns to include (empty = all)
exclude: []                    # Glob patterns to exclude

type_overrides: {}             # XSD type → PHP type
class_name_overrides: {}       # XSD type name → PHP class name
property_name_overrides: {}    # XSD element name → PHP property name

include_documentation: true
generate_validation: true
generate_validator_attributes: false
include_generated_tag: true

codelists:
  dir: ~                       # Path to .gc files (null = disabled)
  namespace: 'Codelist'
  name_overrides: {}           # listID → custom enum class name
  bindings: {}                 # XsdType.Element → listID or [listID1, listID2]
```

## Codelist Support

Point the generator at a directory of OASIS Genericode (`.gc`) files to generate `string`-backed PHP enums:

```yaml
codelists:
  dir: 'resources/codelists/gc'
  namespace: 'Codelist'
  name_overrides:
    'http://publications.europa.eu/resource/authority/country': 'CountryCode'
```

Generated enum:

```php
use Xterr\UBL\Xml\Mapping\CodelistMeta;

#[CodelistMeta(listID: 'criterion-element-type', listAgencyID: 'OP', listVersionID: '4.1.0')]
enum CriterionElementType: string
{
    case QUESTION = 'QUESTION';
    case REQUIREMENT = 'REQUIREMENT';
    case CAPTION = 'CAPTION';
    // ...
}
```

### Property Bindings

Bind codelist enums to specific XSD properties, replacing the default CBC `Code` type:

```yaml
codelists:
  dir: 'resources/codelists/gc'
  bindings:
    # Single binding — property typed as one enum
    'TenderingCriterionPropertyType.TypeCode': 'criterion-element-type'
    'CountryType.IdentificationCode': 'http://publications.europa.eu/resource/authority/country'

    # Union binding — property accepts multiple enums
    'TenderingCriterionPropertyType.ExpectedCode':
      - 'boolean-gui-control-type'
      - 'financial-ratio-type'
      - 'http://publications.europa.eu/resource/authority/occupation'
```

Single bindings produce:

```php
private ?CriterionElementType $typeCode = null;
```

Union bindings produce PHP union types:

```php
private BooleanGUIControl|FinancialRatio|OccupationCode|null $expectedCode = null;

public function getExpectedCode(): BooleanGUIControl|FinancialRatio|OccupationCode|null
{
    return $this->expectedCode;
}

public function setExpectedCode(BooleanGUIControl|FinancialRatio|OccupationCode|null $expectedCode = null): self
{
    $this->expectedCode = $expectedCode;
    return $this;
}
```

## Namespace Handling

All standard UBL 2.x namespaces are supported with universal resolution:

| Namespace | Prefix | Output | Description |
|-----------|--------|--------|-------------|
| CommonBasicComponents | `cbc` | `Cbc/` | Leaf types (value + attributes) |
| SignatureBasicComponents | `sbc` | `Cbc/` | Signature leaf types (same pattern) |
| CommonExtensionComponents | `ext` | `Cbc/` or `Cac/` | Leaf types → `Cbc/`, aggregates → `Cac/` |
| CommonAggregateComponents | `cac` | `Cac/` | Aggregate types (child elements) |
| SignatureAggregateComponents | `sac` | `Cac/` | Signature aggregate types |
| CommonSignatureComponents | `sig` | `Cac/` | Signature components |

Types are classified structurally: complex types with `simpleContent` extensions become leaf classes in `Cbc/`, complex types with child element sequences become aggregate classes in `Cac/`.

## CLI Options

| Option | Description |
|--------|-------------|
| `--config`, `-c` | Path to YAML configuration file |
| `--schema-dir`, `-s` | Path to XSD schema directory |
| `--schema-version` | UBL schema version (2.1, 2.2, 2.3, 2.4) |
| `--output-dir`, `-o` | Output directory for generated classes |
| `--namespace` | Root PHP namespace |
| `--codelist-dir` | Path to directory containing `.gc` codelist files |
| `--force`, `-f` | Actually generate files (without this, dry-run only) |

## Architecture

Two packages work together:

| Package | Role | Install |
|---------|------|---------|
| **[xterr/php-ubl-generator](https://github.com/xterr/php-ubl-generator)** (this) | Dev-time code generator | `composer require --dev` |
| **[xterr/php-ubl](https://github.com/xterr/php-ubl)** | Runtime XML mapping & serialization | `composer require` |

The generator reads XSD schemas and Genericode files, then emits PHP classes that use attributes from `xterr/php-ubl`. At runtime, only the lightweight `xterr/php-ubl` package is needed for XML serialization and deserialization.

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyze
```

### CI

Tests run on PHP 8.2, 8.3, and 8.4 via GitHub Actions. Tagged releases are automatically notified to Packagist.

## License

[MIT](LICENSE) — Copyright (c) 2026 Ceana Razvan
