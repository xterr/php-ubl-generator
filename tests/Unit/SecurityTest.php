<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xterr\UBL\Exception\DeserializationException;
use Xterr\UBL\Exception\GeneratorException;
use Xterr\UBL\Exception\SchemaParseException;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\InvoiceFixture;
use Xterr\UBL\Xml\XmlDeserializer;
use Xterr\UBL\Generator\Xsd\SchemaLoader;

final class SecurityTest extends TestCase
{
    private XmlDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new XmlDeserializer();
    }

    /**
     * Test XXE attack via entity declaration in XML.
     * The deserializer should strip DOCTYPE and reject entity declarations.
     */
    public function testXxeEntityDeclarationAttack(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><ID>&xxe;</ID></Invoice>';

        $this->expectException(DeserializationException::class);

        $this->deserializer->deserialize($xml, InvoiceFixture::class);
    }

    /**
     * Test XXE billion laughs attack (exponential entity expansion).
     * The deserializer should reject documents with entity definitions.
     */
    public function testXxeBillionLaughsAttack(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;">]><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><ID>&lol2;</ID></Invoice>';

        $this->expectException(DeserializationException::class);

        $this->deserializer->deserialize($xml, InvoiceFixture::class);
    }

    /**
     * Test XML size limit enforcement.
     * Deserializer should reject XML exceeding maxXmlSize.
     */
    public function testXmlSizeLimitExceeded(): void
    {
        $deserializer = new XmlDeserializer(maxXmlSize: 100);
        $xml = str_repeat('<a>', 200); // Exceeds 100 bytes

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessageMatches('/exceeds maximum size/i');

        $deserializer->deserialize($xml, InvoiceFixture::class);
    }

    /**
     * Test invalid PHP identifier in GeneratorConfig class_name_overrides.
     * Should throw GeneratorException for invalid identifiers.
     */
    public function testInvalidPhpIdentifierInClassNameOverrides(): void
    {
        $config = [
            'schema_version' => '2.4',
            'schema_dir' => null,
            'output_dir' => '/tmp',
            'namespace' => 'App\Ubl',
            'namespaces' => [
                'cbc' => 'App\Ubl\Cbc',
                'cac' => 'App\Ubl\Cac',
                'doc' => 'App\Ubl\Doc',
                'enum' => 'App\Ubl\Enum',
            ],
            'include' => [],
            'exclude' => [],
            'type_overrides' => [],
            'class_name_overrides' => ['FooType' => 'Invalid Class Name'], // Invalid: contains space
            'property_name_overrides' => [],
            'include_documentation' => false,
            'generate_validation' => true,
            'generate_validator_attributes' => false,
            'include_generated_tag' => true,
        ];

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessageMatches('/Invalid PHP identifier/i');

        GeneratorConfig::fromArray($config);
    }

    /**
     * Test invalid PHP identifier in GeneratorConfig property_name_overrides.
     * Should throw GeneratorException for invalid identifiers.
     */
    public function testInvalidPhpIdentifierInPropertyNameOverrides(): void
    {
        $config = [
            'schema_version' => '2.4',
            'schema_dir' => null,
            'output_dir' => '/tmp',
            'namespace' => 'App\Ubl',
            'namespaces' => [
                'cbc' => 'App\Ubl\Cbc',
                'cac' => 'App\Ubl\Cac',
                'doc' => 'App\Ubl\Doc',
                'enum' => 'App\Ubl\Enum',
            ],
            'include' => [],
            'exclude' => [],
            'type_overrides' => [],
            'class_name_overrides' => [],
            'property_name_overrides' => ['someField' => '123invalid'], // Invalid: starts with digit
            'include_documentation' => false,
            'generate_validation' => true,
            'generate_validator_attributes' => false,
            'include_generated_tag' => true,
        ];

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessageMatches('/Invalid PHP identifier/i');

        GeneratorConfig::fromArray($config);
    }

    /**
     * Test SchemaLoader path traversal protection.
     * Should reject paths outside the allowed base directory.
     */
    public function testSchemaLoaderPathTraversalBlocked(): void
    {
        $loader = new SchemaLoader();

        $this->expectException(SchemaParseException::class);
        $this->expectExceptionMessageMatches('/outside the allowed directory/i');

        $loader->loadFile('/etc/passwd', allowedBaseDir: '/some/schema/dir');
    }

    /**
     * Test valid PHP identifiers are accepted in GeneratorConfig.
     */
    public function testValidPhpIdentifiersAccepted(): void
    {
        $config = [
            'schema_version' => '2.4',
            'schema_dir' => null,
            'output_dir' => '/tmp',
            'namespace' => 'App\Ubl',
            'namespaces' => [
                'cbc' => 'App\Ubl\Cbc',
                'cac' => 'App\Ubl\Cac',
                'doc' => 'App\Ubl\Doc',
                'enum' => 'App\Ubl\Enum',
            ],
            'include' => [],
            'exclude' => [],
            'type_overrides' => [],
            'class_name_overrides' => ['FooType' => 'ValidClassName'],
            'property_name_overrides' => ['someField' => 'validPropertyName'],
            'include_documentation' => false,
            'generate_validation' => true,
            'generate_validator_attributes' => false,
            'include_generated_tag' => true,
        ];

        $generatorConfig = GeneratorConfig::fromArray($config);

        $this->assertSame('ValidClassName', $generatorConfig->classNameOverrides['FooType']);
        $this->assertSame('validPropertyName', $generatorConfig->propertyNameOverrides['someField']);
    }
}
