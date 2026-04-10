<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Resolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Resolver\CbcTypeResolver;
use Xterr\UBL\Generator\Resolver\TypeResolver;
use Xterr\UBL\Generator\Xsd\SchemaLoader;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;

final class TypeResolverTest extends TestCase
{
    private const XSD_DIR = __DIR__ . '/../../Fixtures/Xsd';
    private const XSD_TYPES_CONFIG = __DIR__ . '/../../../resources/config/xsd_types.yaml';

    private static TypeResolver $resolver;

    public static function setUpBeforeClass(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        $registry = new UblTypeRegistry();
        $registry->populate($schemas);

        $cbcResolver = new CbcTypeResolver($registry, self::XSD_TYPES_CONFIG);

        self::$resolver = new TypeResolver($cbcResolver, self::XSD_TYPES_CONFIG);
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
}
