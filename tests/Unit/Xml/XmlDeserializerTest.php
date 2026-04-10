<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Xml;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Exception\DeserializationException;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\AmountFixture;
use Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer\InvoiceFixture;
use Xterr\UBL\Xml\XmlDeserializer;

final class XmlDeserializerTest extends TestCase
{
    private XmlDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new XmlDeserializer();
    }

    #[Test]
    public function deserializesInvoiceXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>INV-001</cbc:ID>
    <cbc:IssueDate>2024-03-15</cbc:IssueDate>
    <cbc:TaxAmount currencyID="EUR">100.50</cbc:TaxAmount>
</Invoice>
XML;

        $invoice = $this->deserializer->deserialize($xml, InvoiceFixture::class);

        self::assertInstanceOf(InvoiceFixture::class, $invoice);
        self::assertSame('INV-001', $invoice->getId());
        self::assertNotNull($invoice->getIssueDate());
        self::assertSame('2024-03-15', $invoice->getIssueDate()->format('Y-m-d'));
        self::assertNotNull($invoice->getTaxAmount());
        self::assertSame('100.50', $invoice->getTaxAmount()->getValue());
        self::assertSame('EUR', $invoice->getTaxAmount()->getCurrencyId());
    }

    #[Test]
    public function deserializesAmountWithAttributes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<AmountType xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
            currencyID="USD">250.00</AmountType>
XML;

        $amount = $this->deserializer->deserialize($xml, AmountFixture::class);

        self::assertInstanceOf(AmountFixture::class, $amount);
        self::assertSame('250.00', $amount->getValue());
        self::assertSame('USD', $amount->getCurrencyId());
    }

    #[Test]
    public function missingOptionalElementsAreNull(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>INV-002</cbc:ID>
</Invoice>
XML;

        $invoice = $this->deserializer->deserialize($xml, InvoiceFixture::class);

        self::assertSame('INV-002', $invoice->getId());
        self::assertNull($invoice->getIssueDate());
        self::assertNull($invoice->getTaxAmount());
        self::assertSame([], $invoice->getNotes());
    }

    #[Test]
    public function deserializesArrayElements(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>INV-003</cbc:ID>
    <cbc:Note currencyID="EUR">10.00</cbc:Note>
    <cbc:Note currencyID="USD">20.00</cbc:Note>
</Invoice>
XML;

        $invoice = $this->deserializer->deserialize($xml, InvoiceFixture::class);

        self::assertCount(2, $invoice->getNotes());
        self::assertSame('10.00', $invoice->getNotes()[0]->getValue());
        self::assertSame('EUR', $invoice->getNotes()[0]->getCurrencyId());
        self::assertSame('20.00', $invoice->getNotes()[1]->getValue());
        self::assertSame('USD', $invoice->getNotes()[1]->getCurrencyId());
    }

    #[Test]
    public function xxeAttackIsRejected(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">&xxe;</cbc:ID>
</Invoice>
XML;

        // DOCTYPE is stripped, so the entity reference &xxe; will cause a parse error
        $this->expectException(DeserializationException::class);
        $this->deserializer->deserialize($xml, InvoiceFixture::class);
    }

    #[Test]
    public function malformedXmlThrowsException(): void
    {
        $xml = '<Invoice><not-closed>';

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessage('Failed to parse XML');
        $this->deserializer->deserialize($xml, InvoiceFixture::class);
    }

    #[Test]
    public function sizeLimitExceededThrowsException(): void
    {
        $deserializer = new XmlDeserializer(maxXmlSize: 100);
        $xml = str_repeat('x', 101);

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessage('XML exceeds maximum size');
        $deserializer->deserialize($xml, InvoiceFixture::class);
    }

    #[Test]
    public function nullTargetClassThrowsException(): void
    {
        $xml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessage('Target class must be specified');
        $this->deserializer->deserialize($xml);
    }

    #[Test]
    public function unmappedChildElementsCollectedInXmlAny(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <cbc:ID>INV-004</cbc:ID>
    <ext:UBLExtensions><ext:UBLExtension>custom</ext:UBLExtension></ext:UBLExtensions>
</Invoice>
XML;

        $invoice = $this->deserializer->deserialize($xml, InvoiceFixture::class);

        self::assertSame('INV-004', $invoice->getId());
        self::assertNotEmpty($invoice->getExtensions());
        self::assertCount(1, $invoice->getExtensions());
        self::assertStringContainsString('UBLExtensions', $invoice->getExtensions()[0]);
    }
}
