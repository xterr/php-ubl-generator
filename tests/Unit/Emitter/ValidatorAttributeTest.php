<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Emitter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Emitter\ClassEmitter;
use Xterr\UBL\Generator\Emitter\ResolvedProperty;
use Xterr\UBL\Generator\Resolver\NamingResolver;
use Xterr\UBL\Xml\Mapping\XmlNamespace as Ns;

final class ValidatorAttributeTest extends TestCase
{
    private ClassEmitter $emitter;
    private GeneratorConfig $config;

    protected function setUp(): void
    {
        $this->config = GeneratorConfig::defaults()->withOverrides([
            'generate_validator_attributes' => true,
        ]);
        $this->emitter = new ClassEmitter($this->config, new NamingResolver($this->config));
    }

    private function cbcFqcn(string $type): string
    {
        return $this->config->namespace . '\\' . $this->config->cbcNamespace . '\\' . $type;
    }

    private function cacFqcn(string $type): string
    {
        return $this->config->namespace . '\\' . $this->config->cacNamespace . '\\' . $type;
    }

    #[Test]
    public function requiredScalarPropertyGetsNotBlank(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'id',
                phpType: $this->cbcFqcn('Identifier'),
                isNullable: true,
                isArray: false,
                xmlElementName: 'ID',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\NotBlank]', $output);
    }

    #[Test]
    public function requiredArrayPropertyGetsCountConstraint(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'invoiceLines',
                phpType: 'array',
                isNullable: false,
                isArray: true,
                xmlElementName: 'InvoiceLine',
                xmlNamespace: Ns::CAC,
                innerType: $this->cacFqcn('InvoiceLine'),
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\Count(min: 1)]', $output);
    }

    #[Test]
    public function complexObjectPropertyGetsValid(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'party',
                phpType: $this->cacFqcn('Party'),
                isNullable: true,
                isArray: false,
                xmlElementName: 'Party',
                xmlNamespace: Ns::CAC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: false,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\Valid]', $output);
    }

    #[Test]
    public function complexArrayPropertyGetsValid(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'items',
                phpType: 'array',
                isNullable: false,
                isArray: true,
                xmlElementName: 'Item',
                xmlNamespace: Ns::CAC,
                innerType: $this->cacFqcn('Item'),
                choiceGroup: null,
                documentation: null,
                required: false,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\Valid]', $output);
    }

    #[Test]
    public function noValidatorAttributesWhenDisabled(): void
    {
        $config = GeneratorConfig::defaults();
        $emitter = new ClassEmitter($config, new NamingResolver($config));

        $properties = [
            new ResolvedProperty(
                phpName: 'id',
                phpType: $config->namespace . '\\' . $config->cbcNamespace . '\\Identifier',
                isNullable: true,
                isArray: false,
                xmlElementName: 'ID',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringNotContainsString('Assert\\', $output);
        self::assertStringNotContainsString('Symfony\Component\Validator', $output);
    }

    #[Test]
    public function useStatementForAssertIsAdded(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'name',
                phpType: $this->cbcFqcn('Text'),
                isNullable: true,
                isArray: false,
                xmlElementName: 'Name',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('use Symfony\Component\Validator\Constraints as Assert;', $output);
    }

    #[Test]
    public function choiceGroupPropertyGetsChoiceGroupConstraint(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'debitAmount',
                phpType: $this->cbcFqcn('Amount'),
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
                phpType: $this->cbcFqcn('Amount'),
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

        self::assertStringContainsString("ChoiceGroupConstraint(group: 'amount-choice')", $output);
        self::assertStringContainsString('use Xterr\UBL\Validation\ChoiceGroupConstraint;', $output);
    }

    #[Test]
    public function requiredComplexPropertyGetsBothNotBlankAndValid(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'party',
                phpType: $this->cacFqcn('Party'),
                isNullable: true,
                isArray: false,
                xmlElementName: 'AccountingSupplierParty',
                xmlNamespace: Ns::CAC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\NotBlank]', $output);
        self::assertStringContainsString('#[Assert\Valid]', $output);
    }

    #[Test]
    public function requiredComplexArrayGetsBothValidAndCount(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'invoiceLines',
                phpType: 'array',
                isNullable: false,
                isArray: true,
                xmlElementName: 'InvoiceLine',
                xmlNamespace: Ns::CAC,
                innerType: $this->cacFqcn('InvoiceLine'),
                choiceGroup: null,
                documentation: null,
                required: true,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringContainsString('#[Assert\Valid]', $output);
        self::assertStringContainsString('#[Assert\Count(min: 1)]', $output);
    }

    #[Test]
    public function optionalPropertyDoesNotGetNotBlankOrCount(): void
    {
        $properties = [
            new ResolvedProperty(
                phpName: 'note',
                phpType: $this->cbcFqcn('Text'),
                isNullable: true,
                isArray: false,
                xmlElementName: 'Note',
                xmlNamespace: Ns::CBC,
                innerType: null,
                choiceGroup: null,
                documentation: null,
                required: false,
            ),
        ];

        $output = $this->emitter->emitComplexClass(
            className: 'TestClass',
            xsdTypeName: 'TestClassType',
            xsdNamespace: Ns::CAC,
            properties: $properties,
        );

        self::assertStringNotContainsString('#[Assert\NotBlank]', $output);
        self::assertStringNotContainsString('#[Assert\Count', $output);
        self::assertStringContainsString('#[Assert\Valid]', $output);
    }
}
