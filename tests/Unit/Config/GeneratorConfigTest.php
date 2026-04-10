<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Exception\GeneratorException;

final class GeneratorConfigTest extends TestCase
{
    #[Test]
    public function defaultsReturnsCorrectDefaultValues(): void
    {
        $config = GeneratorConfig::defaults();

        self::assertSame('2.4', $config->schemaVersion);
        self::assertNull($config->schemaDir);
        self::assertSame('src', $config->outputDir);
        self::assertSame('Xterr\\UBL', $config->namespace);
        self::assertSame('Cbc', $config->cbcNamespace);
        self::assertSame('Cac', $config->cacNamespace);
        self::assertSame('Doc', $config->docNamespace);
        self::assertSame('Enum', $config->enumNamespace);
        self::assertSame([], $config->include);
        self::assertSame([], $config->exclude);
        self::assertSame([], $config->typeOverrides);
        self::assertSame([], $config->classNameOverrides);
        self::assertSame([], $config->propertyNameOverrides);
        self::assertTrue($config->includeDocumentation);
        self::assertTrue($config->generateValidation);
        self::assertFalse($config->generateValidatorAttributes);
        self::assertTrue($config->includeGeneratedTag);
    }

    #[Test]
    public function fromArrayWithFullCustomConfig(): void
    {
        $data = [
            'schema_version' => '2.1',
            'schema_dir' => '/custom/schemas',
            'output_dir' => 'generated',
            'namespace' => 'App\\UBL',
            'namespaces' => [
                'cbc' => 'BasicComponents',
                'cac' => 'AggregateComponents',
                'doc' => 'Documents',
                'enum' => 'Enumerations',
            ],
            'include' => ['Invoice*', 'CreditNote*'],
            'exclude' => ['*SignatureType'],
            'type_overrides' => ['xsd:decimal' => 'float'],
            'class_name_overrides' => ['InvoiceType' => 'Invoice'],
            'property_name_overrides' => ['UBLVersionID' => 'ublVersion'],
            'include_documentation' => false,
            'generate_validation' => false,
            'generate_validator_attributes' => true,
            'include_generated_tag' => false,
        ];

        $config = GeneratorConfig::fromArray($data);

        self::assertSame('2.1', $config->schemaVersion);
        self::assertSame('/custom/schemas', $config->schemaDir);
        self::assertSame('generated', $config->outputDir);
        self::assertSame('App\\UBL', $config->namespace);
        self::assertSame('BasicComponents', $config->cbcNamespace);
        self::assertSame('AggregateComponents', $config->cacNamespace);
        self::assertSame('Documents', $config->docNamespace);
        self::assertSame('Enumerations', $config->enumNamespace);
        self::assertSame(['Invoice*', 'CreditNote*'], $config->include);
        self::assertSame(['*SignatureType'], $config->exclude);
        self::assertSame(['xsd:decimal' => 'float'], $config->typeOverrides);
        self::assertSame(['InvoiceType' => 'Invoice'], $config->classNameOverrides);
        self::assertSame(['UBLVersionID' => 'ublVersion'], $config->propertyNameOverrides);
        self::assertFalse($config->includeDocumentation);
        self::assertFalse($config->generateValidation);
        self::assertTrue($config->generateValidatorAttributes);
        self::assertFalse($config->includeGeneratedTag);
    }

    #[Test]
    public function fromYamlLoadsDistFile(): void
    {
        $distPath = dirname(__DIR__, 3) . '/resources/config/ubl-generator.yaml.dist';
        $config = GeneratorConfig::fromYaml($distPath);

        self::assertSame('2.4', $config->schemaVersion);
        self::assertNull($config->schemaDir);
        self::assertSame('src', $config->outputDir);
        self::assertSame('Xterr\\UBL', $config->namespace);
        self::assertSame('Cbc', $config->cbcNamespace);
        self::assertSame('Cac', $config->cacNamespace);
        self::assertSame('Doc', $config->docNamespace);
        self::assertSame('Enum', $config->enumNamespace);
        self::assertTrue($config->includeDocumentation);
        self::assertTrue($config->generateValidation);
        self::assertFalse($config->generateValidatorAttributes);
        self::assertTrue($config->includeGeneratedTag);
    }

    #[Test]
    public function resolveSchemaDirWithNullSchemaDirThrowsException(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('schema_dir is required');

        $config = GeneratorConfig::defaults();
        $config->resolveSchemaDir();
    }

    #[Test]
    public function resolveSchemaDirWithCustomSchemaDir(): void
    {
        $config = GeneratorConfig::fromArray([
            'schema_version' => '2.4',
            'schema_dir' => '/custom/schemas/',
            'output_dir' => 'src',
            'namespace' => 'Xterr\\UBL',
            'namespaces' => ['cbc' => 'Cbc', 'cac' => 'Cac', 'doc' => 'Doc', 'enum' => 'Enum'],
            'include' => [],
            'exclude' => [],
            'type_overrides' => [],
            'class_name_overrides' => [],
            'property_name_overrides' => [],
            'include_documentation' => true,
            'generate_validation' => true,
            'generate_validator_attributes' => false,
            'include_generated_tag' => true,
        ]);

        self::assertSame('/custom/schemas', $config->resolveSchemaDir());
    }

    #[Test]
    public function invalidConfigKeyThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $yaml = ['nonexistent_option' => 'value'];
        $processor = new \Symfony\Component\Config\Definition\Processor();
        $processor->processConfiguration(
            new \Xterr\UBL\Generator\Config\ConfigDefinition(),
            [$yaml],
        );
    }

    #[Test]
    public function invalidConfigTypeThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $yaml = ['include_documentation' => 'not_a_boolean'];
        $processor = new \Symfony\Component\Config\Definition\Processor();
        $processor->processConfiguration(
            new \Xterr\UBL\Generator\Config\ConfigDefinition(),
            [$yaml],
        );
    }

    #[Test]
    public function withOverridesReplacesSpecifiedValues(): void
    {
        $config = GeneratorConfig::defaults();

        $overridden = $config->withOverrides([
            'schema_version' => '2.1',
            'output_dir' => 'build/generated',
            'include_documentation' => false,
        ]);

        self::assertSame('2.1', $overridden->schemaVersion);
        self::assertSame('build/generated', $overridden->outputDir);
        self::assertFalse($overridden->includeDocumentation);
        // Unchanged values
        self::assertSame('Xterr\\UBL', $overridden->namespace);
        self::assertTrue($overridden->generateValidation);
        self::assertNull($overridden->schemaDir);
    }

    #[Test]
    public function withOverridesPreservesUnspecifiedValues(): void
    {
        $config = GeneratorConfig::defaults();
        $overridden = $config->withOverrides([]);

        self::assertSame($config->schemaVersion, $overridden->schemaVersion);
        self::assertSame($config->schemaDir, $overridden->schemaDir);
        self::assertSame($config->outputDir, $overridden->outputDir);
        self::assertSame($config->namespace, $overridden->namespace);
        self::assertSame($config->includeDocumentation, $overridden->includeDocumentation);
        self::assertSame($config->generateValidation, $overridden->generateValidation);
    }
}
