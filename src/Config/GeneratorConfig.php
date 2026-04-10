<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Config;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;
use Xterr\UBL\Generator\Exception\GeneratorException;

final readonly class GeneratorConfig
{
    /**
     * @param list<string>          $include
     * @param list<string>          $exclude
     * @param array<string, string> $typeOverrides
     * @param array<string, string> $classNameOverrides
     * @param array<string, string> $propertyNameOverrides
     */
    public function __construct(
        public string $schemaVersion,
        public ?string $schemaDir,
        public string $outputDir,
        public string $namespace,
        public string $cbcNamespace,
        public string $cacNamespace,
        public string $docNamespace,
        public string $enumNamespace,
        public array $include,
        public array $exclude,
        public array $typeOverrides,
        public array $classNameOverrides,
        public array $propertyNameOverrides,
        public bool $includeDocumentation,
        public bool $generateValidation,
        public bool $generateValidatorAttributes,
        public bool $includeGeneratedTag,
    ) {
    }

    /**
     * Create config from a processed (merged + validated) array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        /** @var list<string> $include */
        $include = $config['include'];
        /** @var list<string> $exclude */
        $exclude = $config['exclude'];
        /** @var array<string, string> $typeOverrides */
        $typeOverrides = $config['type_overrides'];
        /** @var array<string, string> $classNameOverrides */
        $classNameOverrides = $config['class_name_overrides'];
        /** @var array<string, string> $propertyNameOverrides */
        $propertyNameOverrides = $config['property_name_overrides'];

        self::validateIdentifiers($classNameOverrides, 'class_name_overrides');
        self::validateIdentifiers($propertyNameOverrides, 'property_name_overrides');
        /** @var array{cbc: string, cac: string, doc: string, enum: string} $namespaces */
        $namespaces = $config['namespaces'];

        return new self(
            schemaVersion: (string) $config['schema_version'],
            schemaDir: isset($config['schema_dir']) ? (string) $config['schema_dir'] : null,
            outputDir: (string) $config['output_dir'],
            namespace: (string) $config['namespace'],
            cbcNamespace: $namespaces['cbc'],
            cacNamespace: $namespaces['cac'],
            docNamespace: $namespaces['doc'],
            enumNamespace: $namespaces['enum'],
            include: $include,
            exclude: $exclude,
            typeOverrides: $typeOverrides,
            classNameOverrides: $classNameOverrides,
            propertyNameOverrides: $propertyNameOverrides,
            includeDocumentation: (bool) $config['include_documentation'],
            generateValidation: (bool) $config['generate_validation'],
            generateValidatorAttributes: (bool) $config['generate_validator_attributes'],
            includeGeneratedTag: (bool) $config['include_generated_tag'],
        );
    }

    /**
     * Load config from a YAML file path, process through TreeBuilder.
     */
    public static function fromYaml(string $path): self
    {
        /** @var array<string, mixed>|null $yaml */
        $yaml = Yaml::parseFile($path);
        $processor = new Processor();
        $config = $processor->processConfiguration(new ConfigDefinition(), [$yaml ?? []]);

        return self::fromArray($config);
    }

    /**
     * Create default config (all defaults from TreeBuilder).
     */
    public static function defaults(): self
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new ConfigDefinition(), [[]]);

        return self::fromArray($config);
    }

    /**
     * Resolve the actual schema directory path.
     * Falls back to bundled schemas if schemaDir is null.
     */
    public function resolveSchemaDir(): string
    {
        if ($this->schemaDir !== null) {
            return rtrim($this->schemaDir, '/');
        }

        return dirname(__DIR__, 2) . '/resources/schemas/' . $this->schemaVersion . '/xsd';
    }

    /**
     * Create a new config with non-null overrides replacing the current values.
     * Used for the 4-level cascade (defaults → yaml → env → CLI).
     *
     * @param array<string, mixed> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self(
            schemaVersion: isset($overrides['schema_version']) && is_string($overrides['schema_version'])
                ? $overrides['schema_version'] : $this->schemaVersion,
            schemaDir: array_key_exists('schema_dir', $overrides)
                ? (is_string($overrides['schema_dir']) ? $overrides['schema_dir'] : null) : $this->schemaDir,
            outputDir: isset($overrides['output_dir']) && is_string($overrides['output_dir'])
                ? $overrides['output_dir'] : $this->outputDir,
            namespace: isset($overrides['namespace']) && is_string($overrides['namespace'])
                ? $overrides['namespace'] : $this->namespace,
            cbcNamespace: isset($overrides['cbc_namespace']) && is_string($overrides['cbc_namespace'])
                ? $overrides['cbc_namespace'] : $this->cbcNamespace,
            cacNamespace: isset($overrides['cac_namespace']) && is_string($overrides['cac_namespace'])
                ? $overrides['cac_namespace'] : $this->cacNamespace,
            docNamespace: isset($overrides['doc_namespace']) && is_string($overrides['doc_namespace'])
                ? $overrides['doc_namespace'] : $this->docNamespace,
            enumNamespace: isset($overrides['enum_namespace']) && is_string($overrides['enum_namespace'])
                ? $overrides['enum_namespace'] : $this->enumNamespace,
            include: isset($overrides['include']) && is_array($overrides['include'])
                ? array_values(array_filter($overrides['include'], 'is_string')) : $this->include,
            exclude: isset($overrides['exclude']) && is_array($overrides['exclude'])
                ? array_values(array_filter($overrides['exclude'], 'is_string')) : $this->exclude,
            typeOverrides: isset($overrides['type_overrides']) && is_array($overrides['type_overrides'])
                ? $overrides['type_overrides'] : $this->typeOverrides,
            classNameOverrides: isset($overrides['class_name_overrides']) && is_array($overrides['class_name_overrides'])
                ? $overrides['class_name_overrides'] : $this->classNameOverrides,
            propertyNameOverrides: isset($overrides['property_name_overrides']) && is_array($overrides['property_name_overrides'])
                ? $overrides['property_name_overrides'] : $this->propertyNameOverrides,
            includeDocumentation: isset($overrides['include_documentation']) && is_bool($overrides['include_documentation'])
                ? $overrides['include_documentation'] : $this->includeDocumentation,
            generateValidation: isset($overrides['generate_validation']) && is_bool($overrides['generate_validation'])
                ? $overrides['generate_validation'] : $this->generateValidation,
            generateValidatorAttributes: isset($overrides['generate_validator_attributes']) && is_bool($overrides['generate_validator_attributes'])
                ? $overrides['generate_validator_attributes'] : $this->generateValidatorAttributes,
            includeGeneratedTag: isset($overrides['include_generated_tag']) && is_bool($overrides['include_generated_tag'])
                ? $overrides['include_generated_tag'] : $this->includeGeneratedTag,
        );
    }

    /**
     * @param array<string, string> $identifiers
     */
    private static function validateIdentifiers(array $identifiers, string $context): void
    {
        foreach ($identifiers as $key => $value) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $value)) {
                throw new GeneratorException(
                    sprintf("Invalid PHP identifier '%s' for key '%s' in %s.", $value, $key, $context),
                );
            }
        }
    }
}
