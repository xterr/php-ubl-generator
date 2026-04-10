<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Resolver\CbcTypeResolver;
use Xterr\UBL\Generator\Xsd\SchemaLoader;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;

final class CbcTypeResolverTest extends TestCase
{
    private const XSD_DIR = __DIR__ . '/../../../resources/schemas/2.4/xsd';
    private const XSD_TYPES_CONFIG = __DIR__ . '/../../../resources/config/xsd_types.yaml';

    private static CbcTypeResolver $resolver;

    public static function setUpBeforeClass(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        $registry = new UblTypeRegistry();
        $registry->populate($schemas);

        self::$resolver = new CbcTypeResolver($registry, self::XSD_TYPES_CONFIG);
        self::$resolver->resolve();
    }

    #[Test]
    public function amountElementsResolveToSameLeafType(): void
    {
        $taxAmount = self::$resolver->leafTypeForElement('TaxAmount');
        $lineExtensionAmount = self::$resolver->leafTypeForElement('LineExtensionAmount');
        $payableAmount = self::$resolver->leafTypeForElement('PayableAmount');

        self::assertNotNull($taxAmount);
        self::assertNotNull($lineExtensionAmount);
        self::assertNotNull($payableAmount);

        self::assertSame($taxAmount, $lineExtensionAmount);
        self::assertSame($taxAmount, $payableAmount);
        self::assertSame('Amount', $taxAmount->className);
    }

    #[Test]
    public function amountLeafTypeHasCurrencyIdAttribute(): void
    {
        $amount = self::$resolver->leafTypeForElement('TaxAmount');

        self::assertNotNull($amount);

        $attrNames = array_map(static fn ($a) => $a->xmlName, $amount->attributes);

        self::assertContains('currencyID', $attrNames);
    }

    #[Test]
    public function amountLeafTypeValuePhpTypeIsString(): void
    {
        $amount = self::$resolver->leafTypeForElement('TaxAmount');

        self::assertNotNull($amount);
        self::assertSame('string', $amount->valuePhpType);
    }

    #[Test]
    public function indicatorBasedElementsResolveToBoolPrimitive(): void
    {
        $phpType = self::$resolver->phpTypeForElement('CopyIndicator');

        self::assertSame('bool', $phpType);
        self::assertTrue(self::$resolver->isPrimitiveMapping('CopyIndicator'));
        self::assertNull(self::$resolver->leafTypeForElement('CopyIndicator'));
    }

    #[Test]
    public function leafTypesContainsExpectedClassNames(): void
    {
        $leafTypes = self::$resolver->leafTypes();
        $classNames = array_keys($leafTypes);

        self::assertContains('Amount', $classNames);
        self::assertContains('Code', $classNames);
        self::assertContains('Identifier', $classNames);
        self::assertContains('Text', $classNames);
        self::assertContains('Measure', $classNames);
        self::assertContains('Quantity', $classNames);
        self::assertContains('Numeric', $classNames);
    }

    #[Test]
    public function leafTypesCountIsBetween7And15(): void
    {
        $leafTypes = self::$resolver->leafTypes();

        self::assertGreaterThanOrEqual(7, count($leafTypes));
        self::assertLessThanOrEqual(15, count($leafTypes));
    }

    #[Test]
    public function allLeafTypesHaveNonEmptyCbcElementNames(): void
    {
        foreach (self::$resolver->leafTypes() as $leafType) {
            self::assertNotEmpty(
                $leafType->cbcElementNames,
                "Leaf type {$leafType->className} has no CBC element names",
            );
        }
    }

    #[Test]
    public function codeLeafTypeHasListIdAttribute(): void
    {
        $leafTypes = self::$resolver->leafTypes();
        $code = $leafTypes['Code'] ?? null;

        self::assertNotNull($code);

        $attrNames = array_map(static fn ($a) => $a->xmlName, $code->attributes);

        self::assertContains('listID', $attrNames);
    }

    #[Test]
    public function identifierLeafTypeHasSchemeIdAttribute(): void
    {
        $leafTypes = self::$resolver->leafTypes();
        $identifier = $leafTypes['Identifier'] ?? null;

        self::assertNotNull($identifier);

        $attrNames = array_map(static fn ($a) => $a->xmlName, $identifier->attributes);

        self::assertContains('schemeID', $attrNames);
    }

    #[Test]
    public function unknownElementReturnsNull(): void
    {
        self::assertNull(self::$resolver->phpTypeForElement('NonExistentElement'));
        self::assertNull(self::$resolver->leafTypeForElement('NonExistentElement'));
    }

    #[Test]
    public function multipleResolvesAreIdempotent(): void
    {
        $beforeCount = count(self::$resolver->leafTypes());

        self::$resolver->resolve();
        self::$resolver->resolve();

        self::assertCount($beforeCount, self::$resolver->leafTypes());
    }
}
