<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Xml;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Exception\SerializationException;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\AmountFixture;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\InvoiceFixture;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\NonRootFixture;
use Xterr\UBL\Xml\XmlSerializer;

final class XmlSerializerTest extends TestCase
{
    private XmlSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new XmlSerializer();
    }

    #[Test]
    public function serializesInvoiceWithNamespaces(): void
    {
        $amount = new AmountFixture();
        $amount->setValue('100.50');
        $amount->setCurrencyId('EUR');

        $invoice = new InvoiceFixture();
        $invoice->setId('INV-001');
        $invoice->setIssueDate(new \DateTimeImmutable('2024-03-15'));
        $invoice->setTaxAmount($amount);

        $xml = $this->serializer->serialize($invoice);

        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"', $xml);
        self::assertStringContainsString('<cbc:ID>INV-001</cbc:ID>', $xml);
        self::assertStringContainsString('<cbc:IssueDate>2024-03-15</cbc:IssueDate>', $xml);
        self::assertStringContainsString('<cbc:TaxAmount', $xml);
        self::assertStringContainsString('currencyID="EUR"', $xml);
        self::assertStringContainsString('100.50', $xml);
    }

    #[Test]
    public function nullPropertiesAreOmitted(): void
    {
        $invoice = new InvoiceFixture();
        $invoice->setId('INV-002');

        $xml = $this->serializer->serialize($invoice);

        self::assertStringContainsString('<cbc:ID>INV-002</cbc:ID>', $xml);
        self::assertStringNotContainsString('IssueDate', $xml);
        self::assertStringNotContainsString('TaxAmount', $xml);
    }

    #[Test]
    public function emptyArraysProduceNoOutput(): void
    {
        $invoice = new InvoiceFixture();
        $invoice->setId('INV-003');

        $xml = $this->serializer->serialize($invoice);

        self::assertStringNotContainsString('<cbc:Note', $xml);
    }

    #[Test]
    public function nonRootObjectThrowsException(): void
    {
        $nonRoot = new NonRootFixture();
        $nonRoot->setName('Test');

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('is not a document root');
        $this->serializer->serialize($nonRoot);
    }

    #[Test]
    public function serializesAmountAttributesCorrectly(): void
    {
        $amount = new AmountFixture();
        $amount->setValue('500.00');
        $amount->setCurrencyId('GBP');

        $invoice = new InvoiceFixture();
        $invoice->setId('INV-004');
        $invoice->setTaxAmount($amount);

        $xml = $this->serializer->serialize($invoice);

        // Parse the serialized XML and check the TaxAmount element
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $taxAmounts = $xpath->query('//cbc:TaxAmount');
        self::assertSame(1, $taxAmounts->length);

        $taxElement = $taxAmounts->item(0);
        self::assertSame('GBP', $taxElement->getAttribute('currencyID'));
        self::assertSame('500.00', trim($taxElement->textContent));
    }

    #[Test]
    public function serializesArrayElements(): void
    {
        $note1 = new AmountFixture();
        $note1->setValue('10.00');
        $note1->setCurrencyId('EUR');

        $note2 = new AmountFixture();
        $note2->setValue('20.00');
        $note2->setCurrencyId('USD');

        $invoice = new InvoiceFixture();
        $invoice->setId('INV-005');
        $invoice->setNotes([$note1, $note2]);

        $xml = $this->serializer->serialize($invoice);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $notes = $xpath->query('//cbc:Note');
        self::assertSame(2, $notes->length);
        self::assertSame('EUR', $notes->item(0)->getAttribute('currencyID'));
        self::assertSame('10.00', trim($notes->item(0)->textContent));
        self::assertSame('USD', $notes->item(1)->getAttribute('currencyID'));
        self::assertSame('20.00', trim($notes->item(1)->textContent));
    }

    #[Test]
    public function roundTripSerializationPreservesData(): void
    {
        $amount = new AmountFixture();
        $amount->setValue('99.99');
        $amount->setCurrencyId('JPY');

        $original = new InvoiceFixture();
        $original->setId('INV-RT');
        $original->setIssueDate(new \DateTimeImmutable('2024-06-01'));
        $original->setTaxAmount($amount);

        $xml = $this->serializer->serialize($original);

        $deserializer = new \Xterr\UBL\Xml\XmlDeserializer();
        $restored = $deserializer->deserialize($xml, InvoiceFixture::class);

        self::assertSame('INV-RT', $restored->getId());
        self::assertSame('2024-06-01', $restored->getIssueDate()->format('Y-m-d'));
        self::assertSame('99.99', $restored->getTaxAmount()->getValue());
        self::assertSame('JPY', $restored->getTaxAmount()->getCurrencyId());
    }

    #[Test]
    public function producesValidXml(): void
    {
        $invoice = new InvoiceFixture();
        $invoice->setId('INV-VALID');

        $xml = $this->serializer->serialize($invoice);

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml);
        self::assertTrue($loaded);
    }
}
