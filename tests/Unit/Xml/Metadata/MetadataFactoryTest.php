<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Xml\Metadata;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\SampleAmount;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\SampleInvoice;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\SampleLineItem;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\SampleOrder;
use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlValue;
use Xterr\UBL\Xml\Metadata\MetadataFactory;

final class MetadataFactoryTest extends TestCase
{
    private MetadataFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MetadataFactory();
    }

    #[Test]
    public function readsXmlTypeFromClassAttribute(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        self::assertNotNull($meta->xmlType);
        self::assertSame('AmountType', $meta->xmlType->localName);
        self::assertSame('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', $meta->xmlType->namespace);
    }

    #[Test]
    public function readsXmlRootFromClassAttribute(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        self::assertNotNull($meta->xmlRoot);
        self::assertSame('Invoice', $meta->xmlRoot->localName);
        self::assertSame('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', $meta->xmlRoot->namespace);
    }

    #[Test]
    public function classWithoutXmlRootHasNullRoot(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        self::assertNull($meta->xmlRoot);
    }

    #[Test]
    public function readsXmlValueProperty(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        $valueProp = $meta->getValueProperty();
        self::assertNotNull($valueProp);
        self::assertSame('value', $valueProp->name);
        self::assertTrue($valueProp->isValue());
        self::assertInstanceOf(XmlValue::class, $valueProp->xmlValue);
        self::assertSame('decimal', $valueProp->xmlValue->format);
    }

    #[Test]
    public function readsXmlAttributeProperties(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        $attrProps = $meta->getAttributeProperties();
        self::assertCount(2, $attrProps);

        $currencyId = $attrProps[0];
        self::assertSame('currencyID', $currencyId->name);
        self::assertTrue($currencyId->isAttribute());
        self::assertInstanceOf(XmlAttribute::class, $currencyId->xmlAttribute);
        self::assertSame('currencyID', $currencyId->xmlAttribute->name);
        self::assertTrue($currencyId->xmlAttribute->required);

        $versionId = $attrProps[1];
        self::assertSame('currencyCodeListVersionID', $versionId->name);
        self::assertFalse($versionId->xmlAttribute->required);
    }

    #[Test]
    public function readsXmlElementProperties(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $elements = $meta->getElementProperties();
        self::assertCount(4, $elements);

        $idProp = $elements[0];
        self::assertSame('id', $idProp->name);
        self::assertTrue($idProp->isElement());
        self::assertInstanceOf(XmlElement::class, $idProp->xmlElement);
        self::assertSame('ID', $idProp->xmlElement->name);
        self::assertTrue($idProp->xmlElement->required);
    }

    #[Test]
    public function readsArrayInnerTypeFromXmlElementTypeParam(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $elements = $meta->getElementProperties();
        $invoiceLines = $elements[2];
        self::assertSame('invoiceLines', $invoiceLines->name);
        self::assertTrue($invoiceLines->isArray);
        self::assertSame(SampleLineItem::class, $invoiceLines->innerType);
    }

    #[Test]
    public function readsArrayInnerTypeFromPhpDocFallback(): void
    {
        $meta = $this->factory->getMetadata(SampleOrder::class);

        $elements = $meta->getElementProperties();
        self::assertCount(1, $elements);

        $orderLines = $elements[0];
        self::assertSame('orderLines', $orderLines->name);
        self::assertTrue($orderLines->isArray);
        self::assertSame(
            'Xterr\UBL\Generator\Tests\Fixtures\Xml\SampleLineItem',
            $orderLines->innerType,
        );
    }

    #[Test]
    public function cachesMetadata(): void
    {
        $first = $this->factory->getMetadata(SampleAmount::class);
        $second = $this->factory->getMetadata(SampleAmount::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function isRootDocumentReturnsTrueForRootClass(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        self::assertTrue($meta->isRootDocument());
    }

    #[Test]
    public function isRootDocumentReturnsFalseForNonRootClass(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        self::assertFalse($meta->isRootDocument());
    }

    #[Test]
    public function getValuePropertyReturnsNullWhenNoValueProperty(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        self::assertNull($meta->getValueProperty());
    }

    #[Test]
    public function readsAnyProperties(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $anyProps = $meta->getAnyProperties();
        self::assertCount(1, $anyProps);
        self::assertSame('extensions', $anyProps[0]->name);
        self::assertTrue($anyProps[0]->isAny());
        self::assertTrue($anyProps[0]->isArray);
    }

    #[Test]
    public function skipsPropertiesWithoutXmlMappingAttributes(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $allPropertyNames = array_map(fn($p) => $p->name, $meta->properties);
        self::assertNotContains('unmappedProperty', $allPropertyNames);
    }

    #[Test]
    public function detectsNullableProperties(): void
    {
        $meta = $this->factory->getMetadata(SampleAmount::class);

        $valueProp = $meta->getValueProperty();
        self::assertNotNull($valueProp);
        self::assertTrue($valueProp->isNullable);
        self::assertSame('float', $valueProp->phpType);
    }

    #[Test]
    public function readsElementChoiceGroup(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $elements = $meta->getElementProperties();
        $creditNote = $elements[3];
        self::assertSame('creditNoteLine', $creditNote->name);
        self::assertSame('lineChoice', $creditNote->xmlElement->choiceGroup);
    }

    #[Test]
    public function readsElementFormat(): void
    {
        $meta = $this->factory->getMetadata(SampleInvoice::class);

        $elements = $meta->getElementProperties();
        $issueDate = $elements[1];
        self::assertSame('issueDate', $issueDate->name);
        self::assertSame('date', $issueDate->xmlElement->format);
    }
}
