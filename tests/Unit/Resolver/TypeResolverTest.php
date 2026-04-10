<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Resolver;

use GoetasWebservices\XML\XSDReader\Schema\Element\ElementDef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Resolver\CbcTypeResolver;
use Xterr\UBL\Generator\Resolver\TypeResolver;
use Xterr\UBL\Generator\Xsd\SchemaLoader;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class TypeResolverTest extends TestCase
{
    private const XSD_DIR = __DIR__ . '/../../Fixtures/Xsd';
    private const XSD_TYPES_CONFIG = __DIR__ . '/../../../resources/config/xsd_types.yaml';

    private static TypeResolver $resolver;
    private static UblTypeRegistry $registry;

    public static function setUpBeforeClass(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        self::$registry = new UblTypeRegistry();
        self::$registry->populate($schemas);

        $cbcResolver = new CbcTypeResolver(self::$registry, self::XSD_TYPES_CONFIG);

        self::$resolver = new TypeResolver($cbcResolver, self::XSD_TYPES_CONFIG);
    }

    private static function findElement(string $namespace, string $name): ?ElementDef
    {
        foreach (self::$registry->globalElementsInNamespace($namespace) as $el) {
            if ($el->getName() === $name) {
                return $el;
            }
        }
        return null;
    }

    #[Test]
    #[DataProvider('xsdPrimitiveMappingProvider')]
    public function xsdPrimitiveMapsToExpectedPhpType(string $xsdType, string $expectedPhpType): void
    {
        self::assertSame($expectedPhpType, self::$resolver->resolveXsdPrimitive($xsdType));
    }

    /** @return iterable<string, array{string, string}> */
    public static function xsdPrimitiveMappingProvider(): iterable
    {
        yield 'decimal → string' => ['decimal', 'string'];
        yield 'date → DateTimeImmutable' => ['date', '\DateTimeImmutable'];
        yield 'dateTime → DateTimeImmutable' => ['dateTime', '\DateTimeImmutable'];
        yield 'boolean → bool' => ['boolean', 'bool'];
        yield 'integer → int' => ['integer', 'int'];
        yield 'int → int' => ['int', 'int'];
        yield 'string → string' => ['string', 'string'];
        yield 'normalizedString → string' => ['normalizedString', 'string'];
        yield 'token → string' => ['token', 'string'];
        yield 'float → string' => ['float', 'string'];
        yield 'double → string' => ['double', 'string'];
        yield 'anyURI → string' => ['anyURI', 'string'];
        yield 'base64Binary → string' => ['base64Binary', 'string'];
        yield 'time → DateTimeImmutable' => ['time', '\DateTimeImmutable'];
        yield 'long → int' => ['long', 'int'];
    }

    #[Test]
    public function xsdPrimitiveWithXsdPrefixWorks(): void
    {
        self::assertSame('string', self::$resolver->resolveXsdPrimitive('xsd:decimal'));
        self::assertSame('bool', self::$resolver->resolveXsdPrimitive('xsd:boolean'));
    }

    #[Test]
    public function unknownXsdTypeFallsBackToString(): void
    {
        self::assertSame('string', self::$resolver->resolveXsdPrimitive('unknownType'));
        self::assertSame('string', self::$resolver->resolveXsdPrimitive('xsd:nonExistent'));
    }

    // --- Element resolution tests ---

    #[Test]
    public function cbcLeafElementResolvesToCollapsedLeafType(): void
    {
        $element = self::findElement(XmlNamespace::CBC, 'TaxAmount');
        self::assertNotNull($element, 'TaxAmount element not found in CBC');

        $resolved = self::$resolver->resolveElement($element);

        self::assertSame('Amount', $resolved->phpType);
        self::assertTrue($resolved->isLeafType);
        self::assertFalse($resolved->isPrimitive);
    }

    #[Test]
    public function cbcPrimitiveElementResolvesToPhpPrimitive(): void
    {
        $element = self::findElement(XmlNamespace::CBC, 'CopyIndicator');
        self::assertNotNull($element, 'CopyIndicator element not found in CBC');

        $resolved = self::$resolver->resolveElement($element);

        self::assertSame('bool', $resolved->phpType);
        self::assertTrue($resolved->isPrimitive);
        self::assertFalse($resolved->isLeafType);
    }

    #[Test]
    public function extLeafElementResolvesToCollapsedLeafType(): void
    {
        $element = self::findElement(XmlNamespace::EXT, 'ExtensionAgencyID');
        self::assertNotNull($element, 'ExtensionAgencyID element not found in EXT');

        $resolved = self::$resolver->resolveElement($element);

        // ExtensionAgencyIDType extends udt:IdentifierType → must collapse to 'Identifier'
        self::assertSame('Identifier', $resolved->phpType);
        self::assertTrue($resolved->isLeafType);
        self::assertFalse($resolved->isPrimitive);
    }

    #[Test]
    public function extTextLeafElementCollapsesToText(): void
    {
        $element = self::findElement(XmlNamespace::EXT, 'ExtensionReason');
        self::assertNotNull($element, 'ExtensionReason element not found in EXT');

        $resolved = self::$resolver->resolveElement($element);

        self::assertSame('Text', $resolved->phpType);
        self::assertTrue($resolved->isLeafType);
    }

    #[Test]
    public function extCodeLeafElementCollapsesToCode(): void
    {
        $element = self::findElement(XmlNamespace::EXT, 'ExtensionReasonCode');
        self::assertNotNull($element, 'ExtensionReasonCode element not found in EXT');

        $resolved = self::$resolver->resolveElement($element);

        self::assertSame('Code', $resolved->phpType);
        self::assertTrue($resolved->isLeafType);
    }

    #[Test]
    public function extAggregateElementResolvesAsComplexType(): void
    {
        $element = self::findElement(XmlNamespace::EXT, 'UBLExtensions');
        self::assertNotNull($element, 'UBLExtensions element not found in EXT');

        $resolved = self::$resolver->resolveElement($element);

        // UBLExtensionsType has xsd:sequence → NOT a leaf type
        self::assertSame('UBLExtensions', $resolved->phpType);
        self::assertFalse($resolved->isLeafType);
        self::assertFalse($resolved->isPrimitive);
    }

    #[Test]
    public function cacAggregateElementResolvesAsComplexType(): void
    {
        $element = self::findElement(XmlNamespace::CAC, 'Party');
        self::assertNotNull($element, 'Party element not found in CAC');

        $resolved = self::$resolver->resolveElement($element);

        self::assertSame('Party', $resolved->phpType);
        self::assertFalse($resolved->isLeafType);
        self::assertFalse($resolved->isPrimitive);
    }
}
