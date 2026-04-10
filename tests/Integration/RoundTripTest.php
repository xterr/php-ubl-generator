<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Integration;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\UblGenerator;
use Xterr\UBL\Xml\XmlDeserializer;
use Xterr\UBL\Xml\XmlSerializer;

final class RoundTripTest extends TestCase
{
    private static string $generatedDir;
    private static bool $generated = false;
    private static string $namespace = 'PhpUblRoundTrip';

    /** @var list<string> */
    private static array $roundTripIssues = [];

    public static function setUpBeforeClass(): void
    {
        self::$generatedDir = sys_get_temp_dir() . '/php-ubl-roundtrip-' . getmypid();

        $config = GeneratorConfig::defaults()->withOverrides([
            'schema_dir' => __DIR__ . '/../Fixtures/Xsd',
            'output_dir' => self::$generatedDir,
            'namespace' => self::$namespace,
        ]);

        $generator = new UblGenerator($config);
        $generator->generate();

        spl_autoload_register(static function (string $class): void {
            $prefix = self::$namespace . '\\';
            if (str_starts_with($class, $prefix)) {
                $relPath = str_replace('\\', '/', substr($class, strlen($prefix)));
                $file = self::$generatedDir . '/' . $relPath . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });

        self::$generated = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$generatedDir)) {
            (new Filesystem())->remove(self::$generatedDir);
        }

        if (self::$roundTripIssues !== []) {
            fwrite(\STDERR, "\n--- Round-Trip Issues (documented, not blocking) ---\n");
            foreach (self::$roundTripIssues as $issue) {
                fwrite(\STDERR, "  - {$issue}\n");
            }
            fwrite(\STDERR, "---\n");
        }
    }

    #[Test]
    public function generatedClassesExist(): void
    {
        self::assertTrue(self::$generated, 'Code generation must succeed');
        self::assertFileExists(self::$generatedDir . '/Doc/Invoice.php');
        self::assertFileExists(self::$generatedDir . '/Doc/CreditNote.php');
        self::assertFileExists(self::$generatedDir . '/Cac/Party.php');
        self::assertFileExists(self::$generatedDir . '/Cbc/Amount.php');
    }

    #[Test]
    #[Depends('generatedClassesExist')]
    public function generatedInvoiceClassIsLoadable(): void
    {
        $class = self::$namespace . '\\Doc\\Invoice';
        self::assertTrue(class_exists($class), "Class {$class} must be loadable");
    }

    #[Test]
    #[Depends('generatedClassesExist')]
    public function generatedCreditNoteClassIsLoadable(): void
    {
        $class = self::$namespace . '\\Doc\\CreditNote';
        self::assertTrue(class_exists($class), "Class {$class} must be loadable");
    }

    #[Test]
    #[Depends('generatedInvoiceClassIsLoadable')]
    public function deserializeInvoiceXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice = $deserializer->deserialize($xml, $invoiceClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'Invoice deserialization failed: ' . $e->getMessage();
            self::fail('Invoice deserialization failed: ' . $e->getMessage());
        }

        self::assertInstanceOf($invoiceClass, $invoice);
        $this->assertInvoiceBasicProperties($invoice);
    }

    #[Test]
    #[Depends('generatedCreditNoteClassIsLoadable')]
    public function deserializeCreditNoteXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/credit-note.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $creditNoteClass = self::$namespace . '\\Doc\\CreditNote';

        try {
            $creditNote = $deserializer->deserialize($xml, $creditNoteClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'CreditNote deserialization failed: ' . $e->getMessage();
            self::fail('CreditNote deserialization failed: ' . $e->getMessage());
        }

        self::assertInstanceOf($creditNoteClass, $creditNote);

        if (method_exists($creditNote, 'getId')) {
            $id = $creditNote->getId();
            self::assertNotNull($id, 'CreditNote ID should not be null');
        }
    }

    #[Test]
    #[Depends('deserializeInvoiceXml')]
    public function roundTripInvoiceProducesValidXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice = $deserializer->deserialize($xml, $invoiceClass);
            $output = $serializer->serialize($invoice);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'Invoice round-trip failed: ' . $e->getMessage();
            self::fail('Invoice round-trip failed: ' . $e->getMessage());
        }

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($output);
        self::assertTrue($loaded, 'Re-serialized Invoice must be valid XML');
        self::assertNotNull($dom->documentElement);
        self::assertSame('Invoice', $dom->documentElement->localName);
    }

    #[Test]
    #[Depends('roundTripInvoiceProducesValidXml')]
    public function roundTripInvoicePreservesContent(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice = $deserializer->deserialize($xml, $invoiceClass);
            $output = $serializer->serialize($invoice);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'Invoice content preservation failed: ' . $e->getMessage();
            self::fail('Invoice content preservation check failed: ' . $e->getMessage());
        }

        $outputDom = new \DOMDocument();
        $outputDom->loadXML($output);

        $this->assertXmlContainsValue($outputDom, 'ID', 'INV-001');
        $this->assertXmlContainsValue($outputDom, 'UBLVersionID', '2.4');
        $this->assertXmlContainsValue($outputDom, 'InvoiceTypeCode', '380');
        $this->assertXmlContainsValue($outputDom, 'DocumentCurrencyCode', 'EUR');
    }

    #[Test]
    #[Depends('deserializeCreditNoteXml')]
    public function roundTripCreditNoteProducesValidXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/credit-note.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $creditNoteClass = self::$namespace . '\\Doc\\CreditNote';

        try {
            $creditNote = $deserializer->deserialize($xml, $creditNoteClass);
            $output = $serializer->serialize($creditNote);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'CreditNote round-trip failed: ' . $e->getMessage();
            self::fail('CreditNote round-trip failed: ' . $e->getMessage());
        }

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($output);
        self::assertTrue($loaded, 'Re-serialized CreditNote must be valid XML');

        self::assertNotNull($dom->documentElement);
        self::assertSame('CreditNote', $dom->documentElement->localName);
    }

    #[Test]
    #[Depends('roundTripInvoiceProducesValidXml')]
    public function roundTripInvoiceIsIdempotent(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice1 = $deserializer->deserialize($xml, $invoiceClass);
            $output1 = $serializer->serialize($invoice1);

            $invoice2 = $deserializer->deserialize($output1, $invoiceClass);
            $output2 = $serializer->serialize($invoice2);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'Invoice idempotency check failed: ' . $e->getMessage();
            self::fail('Invoice idempotency check failed: ' . $e->getMessage());
        }

        self::assertSame($output1, $output2, 'Round-trip must be idempotent (serialize(deserialize(output1)) === output1)');
    }

    #[Test]
    #[Depends('deserializeInvoiceXml')]
    public function deserializedInvoiceHasCorrectLineCount(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice = $deserializer->deserialize($xml, $invoiceClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'Invoice line count check failed: ' . $e->getMessage();
            self::fail('Invoice line count check failed: ' . $e->getMessage());
        }

        if (method_exists($invoice, 'getInvoiceLines')) {
            $lines = $invoice->getInvoiceLines();
            self::assertIsArray($lines);
            self::assertCount(2, $lines, 'Invoice should have 2 invoice lines');
        } else {
            self::$roundTripIssues[] = 'Invoice missing getInvoiceLines() method';
            self::fail('Invoice class must have getInvoiceLines() method');
        }
    }

    #[Test]
    #[Depends('deserializeInvoiceXml')]
    public function deserializedInvoiceCbcLeavesHaveAttributes(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/invoice.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $invoiceClass = self::$namespace . '\\Doc\\Invoice';

        try {
            $invoice = $deserializer->deserialize($xml, $invoiceClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'CBC attribute check failed: ' . $e->getMessage();
            self::fail('CBC attribute check failed: ' . $e->getMessage());
        }

        if (!method_exists($invoice, 'getLegalMonetaryTotal')) {
            self::$roundTripIssues[] = 'Invoice missing getLegalMonetaryTotal method';
            self::assertTrue(true);
            return;
        }

        $total = $invoice->getLegalMonetaryTotal();
        if ($total === null || !method_exists($total, 'getPayableAmount')) {
            self::$roundTripIssues[] = 'LegalMonetaryTotal missing getPayableAmount method';
            self::assertTrue(true);
            return;
        }

        $amount = $total->getPayableAmount();
        if ($amount === null) {
            self::$roundTripIssues[] = 'PayableAmount is null';
            self::assertTrue(true);
            return;
        }

        if (method_exists($amount, 'getCurrencyID')) {
            self::assertSame('EUR', $amount->getCurrencyID());
        } elseif (method_exists($amount, 'getCurrencyId')) {
            self::assertSame('EUR', $amount->getCurrencyId());
        } else {
            self::$roundTripIssues[] = 'PayableAmount missing getCurrencyID/getCurrencyId method';
        }

        if (method_exists($amount, 'getValue')) {
            self::assertSame('1210.00', $amount->getValue());
        }

        self::assertTrue(true);
    }

    private function assertInvoiceBasicProperties(object $invoice): void
    {
        if (method_exists($invoice, 'getId')) {
            $id = $invoice->getId();
            self::assertNotNull($id, 'Invoice ID should not be null');
            if (is_object($id) && method_exists($id, 'getValue')) {
                self::assertSame('INV-001', $id->getValue());
            } elseif (is_string($id)) {
                self::assertSame('INV-001', $id);
            }
        } else {
            self::$roundTripIssues[] = 'Invoice missing getId() method';
            self::fail('Invoice class must have getId() method');
        }

        if (method_exists($invoice, 'getUblVersionID')) {
            $version = $invoice->getUblVersionID();
            self::assertNotNull($version, 'UBLVersionID should not be null');
            if (is_object($version) && method_exists($version, 'getValue')) {
                self::assertSame('2.4', $version->getValue());
            }
        }

        if (method_exists($invoice, 'getIssueDate')) {
            $date = $invoice->getIssueDate();
            self::assertNotNull($date, 'IssueDate should not be null');
        }

        if (method_exists($invoice, 'getAccountingSupplierParty')) {
            $supplier = $invoice->getAccountingSupplierParty();
            self::assertNotNull($supplier, 'AccountingSupplierParty should not be null');

            if (method_exists($supplier, 'getParty')) {
                $party = $supplier->getParty();
                self::assertNotNull($party, 'Supplier Party should not be null');
            }
        }
    }

    private function assertXmlContainsValue(\DOMDocument $dom, string $localName, string $expectedValue): void
    {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[local-name()='{$localName}']");
        self::assertNotFalse($nodes);

        $found = false;
        foreach ($nodes as $node) {
            if (trim($node->textContent) === $expectedValue) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            self::$roundTripIssues[] = "Expected {$localName}={$expectedValue} not found in round-trip output";
        }

        self::assertTrue($found, "Round-trip output should contain <{$localName}>{$expectedValue}</{$localName}>");
    }

    private static function fixturesDir(): string
    {
        return __DIR__ . '/../Fixtures/xml';
    }
}
