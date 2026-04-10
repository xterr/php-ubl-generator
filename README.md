# PHP UBL Generator

Generate typed PHP 8.4+ classes from OASIS UBL 2.x XSD schemas, with optional codelist enum support from OASIS Genericode files.

## What It Does

- Reads UBL XSD schemas (2.1, 2.2, 2.3, 2.4)
- Generates `final class` PHP files with private properties, typed getters, fluent setters
- Adds PHP 8 attributes for XML mapping (`#[XmlElement]`, `#[XmlRoot]`, `#[XmlType]`, etc.)
- Parses OASIS Genericode (`.gc`) files and generates `string`-backed PHP enums with `#[CodelistMeta]`
- Supports union type bindings — a single property can accept multiple codelist enums
- Covers all UBL namespaces: CBC, CAC, EXT, SBC, SIG, SAC
- Inline setter validation (type checks, patterns, enumerations)
- Configurable via YAML (namespace, include/exclude filters, naming overrides, codelist bindings)
- Bring your own UBL XSD schemas (download from [OASIS](http://docs.oasis-open.org/ubl/os-UBL-2.4/))

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
# Generate all UBL 2.4 types (schema-dir is required)
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd --force

# Dry-run (show what would be generated)
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd

# With codelist enums
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd --codelist-dir=path/to/codelists/gc --force

# Custom config (schema_dir and codelists can be set in the YAML file)
php vendor/bin/php-ubl-generator ubl:generate --config=ubl-generator.yaml --force

# Override namespace and output
php vendor/bin/php-ubl-generator ubl:generate --schema-dir=path/to/UBL-2.4/xsd --namespace='App\Ubl' --output-dir=src/Ubl --force
```

## Configuration

Copy the default config:

```bash
cp vendor/xterr/php-ubl-generator/resources/config/ubl-generator.yaml.dist ubl-generator.yaml
```

Key options:

| Option | Description |
|---|---|
| `schema_dir` | Path to UBL XSD schemas directory (required) |
| `schema_version` | UBL version (`2.1`, `2.2`, `2.3`, `2.4`) |
| `namespace` | Root PHP namespace |
| `output_dir` | Where to write generated files |
| `include` / `exclude` | Glob patterns to filter types |
| `class_name_overrides` | Map XSD type names to custom PHP class names |
| `property_name_overrides` | Map XSD element names to custom PHP property names |
| `generate_validation` | Enable/disable setter validation |
| `generate_validator_attributes` | Emit `symfony/validator` `#[Assert\*]` attributes |
| `codelists.dir` | Path to `.gc` Genericode codelist files |
| `codelists.namespace` | Sub-namespace for generated codelist enums (default: `Codelist`) |
| `codelists.name_overrides` | Map codelist `listID` to custom enum class names |
| `codelists.bindings` | Bind codelist enums to XSD properties |

## Codelist Support

Point the generator at a directory of OASIS Genericode (`.gc`) files to generate `string`-backed PHP enums:

```yaml
codelists:
  dir: 'resources/codelists/gc'
  namespace: 'Codelist'
```

This generates enums like:

```php
#[CodelistMeta(listID: 'criterion-element-type', listAgencyID: 'OP', listVersionID: '4.1.0')]
enum CriterionElementType: string
{
    case QUESTION = 'QUESTION';
    case REQUIREMENT = 'REQUIREMENT';
    case CAPTION = 'CAPTION';
    // ...
}
```

### Binding Enums to Properties

Use `bindings` to replace CBC `Code` properties with codelist enum types:

```yaml
codelists:
  dir: 'resources/codelists/gc'
  bindings:
    # Single codelist binding
    'TenderingCriterionPropertyType.TypeCode': 'criterion-element-type'
    'CountryType.IdentificationCode': 'http://publications.europa.eu/resource/authority/country'

    # Union binding — property accepts multiple codelist enums
    'TenderingCriterionPropertyType.ExpectedCode':
      - 'boolean-gui-control-type'
      - 'financial-ratio-type'
      - 'http://publications.europa.eu/resource/authority/occupation'
```

Single bindings generate:

```php
private ?CriterionElementType $typeCode = null;
```

Union bindings generate PHP union types:

```php
private BooleanGUIControl|FinancialRatio|OccupationCode|null $expectedCode = null;
```

## Generated Output Structure

```
src/
  Cbc/            # Leaf types (Amount, Code, Text, Identifier, ...)
  Cac/            # Aggregate types (Party, Address, TaxTotal, ...)
  Doc/            # Document roots (Invoice, CreditNote, ...)
  Enum/           # XSD enumerations
  Codelist/       # Codelist enums from .gc files (when configured)
```

All UBL namespaces are supported:

| Namespace | Output | Description |
|---|---|---|
| CBC | `Cbc/` | Common Basic Components (leaf types: value + attributes) |
| SBC | `Cbc/` | Signature Basic Components (same leaf pattern) |
| EXT | `Cbc/` or `Cac/` | Extension Components (leaf types → `Cbc/`, aggregates → `Cac/`) |
| CAC | `Cac/` | Common Aggregate Components |
| SAC | `Cac/` | Signature Aggregate Components |
| SIG | `Cac/` | Common Signature Components |

## Architecture

Two packages:

- **xterr/php-ubl-generator** (this) — Dev-time tool that reads XSD + Genericode and emits PHP
- **[xterr/php-ubl](https://github.com/xterr/php-ubl)** — Runtime (XML mapping attributes, serializer/deserializer)

## Requirements

- PHP ^8.2
- ext-dom, ext-libxml, ext-mbstring

## License

[MIT](LICENSE)
