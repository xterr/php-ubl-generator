<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Resolver;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Resolver\NamingResolver;

final class NamingResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('propertyNameProvider')]
    public function toPropertyNameConvertsCorrectly(string $xmlName, bool $isArray, string $expected): void
    {
        $resolver = new NamingResolver(GeneratorConfig::defaults());

        self::assertSame($expected, $resolver->toPropertyName($xmlName, $isArray));
    }

    /**
     * @return Generator<string, array{string, bool, string}>
     */
    public static function propertyNameProvider(): Generator
    {
        yield 'ID → id' => ['ID', false, 'id'];
        yield 'UUID → uuid' => ['UUID', false, 'uuid'];
        yield 'URI → uri' => ['URI', false, 'uri'];
        yield 'UBLVersionID → ublVersionID' => ['UBLVersionID', false, 'ublVersionID'];
        yield 'URICode → uriCode' => ['URICode', false, 'uriCode'];
        yield 'StreetName → streetName' => ['StreetName', false, 'streetName'];
        yield 'AddressTypeCode → addressTypeCode' => ['AddressTypeCode', false, 'addressTypeCode'];
        yield 'InvoiceLine (array) → invoiceLines' => ['InvoiceLine', true, 'invoiceLines'];
        yield 'Party (array) → parties' => ['Party', true, 'parties'];
        yield 'Country (array) → countries' => ['Country', true, 'countries'];
        yield 'Address (array) → addresses' => ['Address', true, 'addresses'];
        yield 'list → _list' => ['list', false, '_list'];
        yield 'match → _match' => ['match', false, '_match'];
        yield '__CLASS__ → ___CLASS__' => ['__CLASS__', false, '___CLASS__'];
    }

    #[Test]
    #[DataProvider('classNameProvider')]
    public function toClassNameConvertsCorrectly(string $xsdTypeName, string $expected): void
    {
        $resolver = new NamingResolver(GeneratorConfig::defaults());

        self::assertSame($expected, $resolver->toClassName($xsdTypeName));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function classNameProvider(): Generator
    {
        yield 'AddressType → Address' => ['AddressType', 'Address'];
        yield 'InvoiceLineType → InvoiceLine' => ['InvoiceLineType', 'InvoiceLine'];
    }

    #[Test]
    public function toClassNameUsesConfigOverride(): void
    {
        $config = GeneratorConfig::defaults()->withOverrides([
            'class_name_overrides' => ['FooType' => 'CustomFoo'],
        ]);
        $resolver = new NamingResolver($config);

        self::assertSame('CustomFoo', $resolver->toClassName('FooType'));
    }

    #[Test]
    public function toPropertyNameUsesConfigOverride(): void
    {
        $config = GeneratorConfig::defaults()->withOverrides([
            'property_name_overrides' => ['SomeName' => 'customProp'],
        ]);
        $resolver = new NamingResolver($config);

        self::assertSame('customProp', $resolver->toPropertyName('SomeName'));
    }
}
