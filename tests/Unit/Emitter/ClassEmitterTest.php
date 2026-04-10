<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Emitter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Emitter\ClassEmitter;
use Xterr\UBL\Generator\Emitter\ResolvedProperty;
use Xterr\UBL\Generator\Resolver\NamingResolver;
use Xterr\UBL\Generator\Resolver\ResolvedAttribute;
use Xterr\UBL\Generator\Resolver\ResolvedLeafType;
use Xterr\UBL\Xml\Mapping\XmlNamespace as Ns;

final class ClassEmitterTest extends TestCase
{
    private ClassEmitter $emitter;

    protected function setUp(): void
    {
        $config = GeneratorConfig::defaults();
        $this->emitter = new ClassEmitter($config, new NamingResolver($config));
    }

    #[Test]
    public function emitLeafClassProducesValidAmountClass(): void
    {
        $leafType = new ResolvedLeafType(
            className: 'Amount',
            valuePhpType: 'string',
            attributes: [
                new ResolvedAttribute(
                    xmlName: 'currencyID',
                    phpName: 'currencyId',
                    phpType: 'string',
                    required: false,
                ),
                new ResolvedAttribute(
                    xmlName: 'currencyCodeListVersionID',
                    phpName: 'currencyCodeListVersionId',
                    phpType: 'string',
                    required: false,
                ),
            ],
            cbcElementNames: ['Amount', 'TaxAmount', 'LineExtensionAmount'],
        );

        $output = $this->emitter->emitLeafClass($leafType);

        self::assertStringContainsString('final class Amount', $output);
        self::assertStringContainsString('declare(strict_types=1)', $output);

        self::assertStringContainsString('#[XmlType(', $output);
        self::assertStringContainsString("localName: 'AmountType'", $output);
        self::assertStringContainsString('namespace: Ns::CBC', $output);

        self::assertStringContainsString('#[XmlValue]', $output);
        self::assertStringContainsString('private ?string $value = null', $output);

        self::assertStringContainsString('function getValue(): ?string', $output);
        self::assertStringContainsString('function setValue(?string $value = null): self', $output);

        self::assertStringContainsString('function __toString(): string', $output);
        self::assertStringContainsString('return (string) $this->value', $output);

        self::assertStringContainsString('#[XmlAttribute(', $output);
        self::assertStringContainsString("name: 'currencyID'", $output);
        self::assertStringContainsString('private ?string $currencyId = null', $output);
        self::assertStringContainsString('function getCurrencyId(): ?string', $output);
        self::assertStringContainsString('function setCurrencyId(?string $currencyId = null): self', $output);

        self::assertStringContainsString("name: 'currencyCodeListVersionID'", $output);
        self::assertStringContainsString('private ?string $currencyCodeListVersionId = null', $output);
        self::assertStringContainsString('function getCurrencyCodeListVersionId(): ?string', $output);
    }

    #[Test]
    public function emitComplexClassProducesValidCacClass(): void
    {
        $defaultConfig = GeneratorConfig::defaults();
        $cbcFqcn = $defaultConfig->namespace . '\\' . $defaultConfig->cbcNamespace . '\\Amount';
        $cacFqcn = $defaultConfig->namespace . '\\' . $defaultConfig->cacNamespace . '\\TaxCategory';

        $properties = [
            new ResolvedProperty(
                phpName: 'id',
                phpType: $cbcFqcn,
                isNullable: true,
                isArray: false,
                xmlElementName: 'ID',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: false,
            ),
            new ResolvedProperty(
                phpName: 'taxAmount',
                phpType: $cbcFqcn,
                isNullable: true,
                isArray: false,
                xmlElementName: 'TaxAmount',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
            new ResolvedProperty(
                phpName: 'taxCategories',
                phpType: 'array',
                isNullable: false,
                isArray: true,
                xmlElementName: 'TaxCategory',
                xmlNamespace: Ns::CAC,
                innerType: $cacFqcn,
                choiceGroup: null,
                documentation: null,
                required: false,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TaxTotal',
            xsdTypeName: 'TaxTotalType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('final class TaxTotal', $output);
        self::assertStringContainsString('declare(strict_types=1)', $output);

        self::assertStringContainsString('#[XmlType(', $output);
        self::assertStringContainsString("localName: 'TaxTotalType'", $output);
        self::assertStringContainsString('namespace: Ns::CAC', $output);

        self::assertStringContainsString('#[XmlElement(', $output);
        self::assertStringContainsString("name: 'ID'", $output);
        self::assertStringContainsString("name: 'TaxAmount'", $output);
        self::assertStringContainsString("name: 'TaxCategory'", $output);

        self::assertStringContainsString('required: true', $output);

        self::assertStringContainsString('private ?Amount $id = null', $output);
        self::assertStringContainsString('function getId(): ?Amount', $output);
        self::assertStringContainsString('function setId(?Amount $id = null): self', $output);

        self::assertStringContainsString('@var list<TaxCategory>', $output);
        self::assertStringContainsString('private array $taxCategories = []', $output);
        self::assertStringContainsString('type: TaxCategory::class', $output);
        self::assertStringContainsString('function getTaxCategories(): array', $output);
        self::assertStringContainsString('function setTaxCategories(array $taxCategories): self', $output);
        self::assertStringContainsString('function addToTaxCategories(TaxCategory $item): self', $output);

        self::assertStringContainsString('function validateTaxCategories(array $values): void', $output);
    }

    #[Test]
    public function emitComplexClassWithDocumentRootIncludesXmlRoot(): void
    {
        $output = $this->emitter->emitComplexClass(
            className: 'Invoice',
            xsdTypeName: 'InvoiceType',
            xsdNamespace: Ns::CAC,
            properties: [],
            isDocumentRoot: true,
            rootNamespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            rootLocalName: 'Invoice',
        );

        self::assertStringContainsString('#[XmlRoot(', $output);
        self::assertStringContainsString("localName: 'Invoice'", $output);
        self::assertStringContainsString("namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2'", $output);
    }

    #[Test]
    public function emitLeafClassIncludesGeneratedTag(): void
    {
        $leafType = new ResolvedLeafType(
            className: 'Code',
            valuePhpType: 'string',
            attributes: [],
            cbcElementNames: ['Code'],
        );

        $output = $this->emitter->emitLeafClass($leafType);

        self::assertStringContainsString('@generated by php-ubl code generator', $output);
    }

    #[Test]
    public function emitComplexClassWithDocumentation(): void
    {
        $config = GeneratorConfig::defaults();
        $emitter = new ClassEmitter($config, new NamingResolver($config));

        $output = $emitter->emitComplexClass(
            className: 'Address',
            xsdTypeName: 'AddressType',
            xsdNamespace: Ns::CAC,
            properties: [],
            documentation: 'A class to define common address information.',
        );

        self::assertStringContainsString('A class to define common address information.', $output);
    }

    #[Test]
    public function emitComplexClassWithChoiceGroup(): void
    {
        $defaultConfig = GeneratorConfig::defaults();
        $cbcFqcn = $defaultConfig->namespace . '\\' . $defaultConfig->cbcNamespace . '\\Amount';

        $properties = [
            new ResolvedProperty(
                phpName: 'debitAmount',
                phpType: $cbcFqcn,
                isNullable: true,
                isArray: false,
                xmlElementName: 'DebitAmount',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: 'amount-choice',
                documentation: null,
                required: false,
            ),
            new ResolvedProperty(
                phpName: 'creditAmount',
                phpType: $cbcFqcn,
                isNullable: true,
                isArray: false,
                xmlElementName: 'CreditAmount',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: 'amount-choice',
                documentation: null,
                required: false,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'MonetaryTotal',
            xsdTypeName: 'MonetaryTotalType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString("choiceGroup: 'amount-choice'", $output);
    }

    #[Test]
    public function emitLeafClassUsesConfigNamespace(): void
    {
        $leafType = new ResolvedLeafType(
            className: 'Text',
            valuePhpType: 'string',
            attributes: [],
            cbcElementNames: ['Text'],
        );

        $output = $this->emitter->emitLeafClass($leafType);

        $config = GeneratorConfig::defaults();
        $expectedNs = $config->namespace . '\\' . $config->cbcNamespace;
        self::assertStringContainsString('namespace ' . $expectedNs . ';', $output);
    }
}
