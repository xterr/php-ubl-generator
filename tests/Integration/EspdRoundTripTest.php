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

final class EspdRoundTripTest extends TestCase
{
    private static string $generatedDir;
    private static bool $generated = false;
    private static string $namespace = 'PhpUblEspdTest';

    /** @var list<string> */
    private static array $roundTripIssues = [];

    public static function setUpBeforeClass(): void
    {
        self::$generatedDir = sys_get_temp_dir() . '/php-ubl-espd-' . getmypid();

        $config = GeneratorConfig::defaults()->withOverrides([
            'schema_version' => '2.3',
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
            fwrite(\STDERR, "\n--- ESPD Round-Trip Issues (documented, not blocking) ---\n");
            foreach (self::$roundTripIssues as $issue) {
                fwrite(\STDERR, "  - {$issue}\n");
            }
            fwrite(\STDERR, "---\n");
        }
    }

    #[Test]
    public function espdGeneratedClassesExist(): void
    {
        self::assertTrue(self::$generated, 'Code generation must succeed');
        self::assertFileExists(self::$generatedDir . '/Doc/QualificationApplicationRequest.php');
        self::assertFileExists(self::$generatedDir . '/Doc/QualificationApplicationResponse.php');
        self::assertFileExists(self::$generatedDir . '/Cac/TenderingCriterion.php');
        self::assertFileExists(self::$generatedDir . '/Cac/ContractingParty.php');
    }

    #[Test]
    #[Depends('espdGeneratedClassesExist')]
    public function espdRequestClassIsLoadable(): void
    {
        $class = self::$namespace . '\\Doc\\QualificationApplicationRequest';
        self::assertTrue(class_exists($class), "Class {$class} must be loadable");
    }

    #[Test]
    #[Depends('espdGeneratedClassesExist')]
    public function espdResponseClassIsLoadable(): void
    {
        $class = self::$namespace . '\\Doc\\QualificationApplicationResponse';
        self::assertTrue(class_exists($class), "Class {$class} must be loadable");
    }

    #[Test]
    #[Depends('espdRequestClassIsLoadable')]
    public function deserializeEspdRequest(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-request.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $requestClass = self::$namespace . '\\Doc\\QualificationApplicationRequest';

        try {
            $request = $deserializer->deserialize($xml, $requestClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Request deserialization failed: ' . $e->getMessage();
            self::fail('ESPD Request deserialization failed: ' . $e->getMessage());
        }

        self::assertInstanceOf($requestClass, $request);
        $this->assertEspdRequestBasicProperties($request);
    }

    #[Test]
    #[Depends('espdResponseClassIsLoadable')]
    public function deserializeEspdResponse(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-response.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $responseClass = self::$namespace . '\\Doc\\QualificationApplicationResponse';

        try {
            $response = $deserializer->deserialize($xml, $responseClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Response deserialization failed: ' . $e->getMessage();
            self::fail('ESPD Response deserialization failed: ' . $e->getMessage());
        }

        self::assertInstanceOf($responseClass, $response);
        $this->assertEspdResponseBasicProperties($response);
    }

    #[Test]
    #[Depends('deserializeEspdRequest')]
    public function roundTripEspdRequestProducesValidXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-request.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $requestClass = self::$namespace . '\\Doc\\QualificationApplicationRequest';

        try {
            $request = $deserializer->deserialize($xml, $requestClass);
            $output = $serializer->serialize($request);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Request round-trip failed: ' . $e->getMessage();
            self::fail('ESPD Request round-trip failed: ' . $e->getMessage());
        }

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($output);
        self::assertTrue($loaded, 'Re-serialized ESPD Request must be valid XML');
        self::assertNotNull($dom->documentElement);
        self::assertSame('QualificationApplicationRequest', $dom->documentElement->localName);
    }

    #[Test]
    #[Depends('deserializeEspdResponse')]
    public function roundTripEspdResponseProducesValidXml(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-response.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $responseClass = self::$namespace . '\\Doc\\QualificationApplicationResponse';

        try {
            $response = $deserializer->deserialize($xml, $responseClass);
            $output = $serializer->serialize($response);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Response round-trip failed: ' . $e->getMessage();
            self::fail('ESPD Response round-trip failed: ' . $e->getMessage());
        }

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($output);
        self::assertTrue($loaded, 'Re-serialized ESPD Response must be valid XML');
        self::assertNotNull($dom->documentElement);
        self::assertSame('QualificationApplicationResponse', $dom->documentElement->localName);
    }

    #[Test]
    #[Depends('roundTripEspdRequestProducesValidXml')]
    public function roundTripEspdRequestPreservesContent(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-request.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $requestClass = self::$namespace . '\\Doc\\QualificationApplicationRequest';

        try {
            $request = $deserializer->deserialize($xml, $requestClass);
            $output = $serializer->serialize($request);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Request content preservation failed: ' . $e->getMessage();
            self::fail('ESPD Request content preservation failed: ' . $e->getMessage());
        }

        $outputDom = new \DOMDocument();
        $outputDom->loadXML($output);

        $this->assertXmlContainsValue($outputDom, 'ID', 'ESPD-REQ-001');
        $this->assertXmlContainsValue($outputDom, 'UBLVersionID', '2.3');
        $this->assertXmlContainsValue($outputDom, 'ContractFolderID', 'PROC-2025-001');
        $this->assertXmlContainsValue($outputDom, 'Name', 'Contracting Authority Example');
    }

    #[Test]
    #[Depends('roundTripEspdRequestProducesValidXml')]
    public function roundTripEspdRequestIsIdempotent(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-request.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $serializer = new XmlSerializer();
        $requestClass = self::$namespace . '\\Doc\\QualificationApplicationRequest';

        try {
            $request1 = $deserializer->deserialize($xml, $requestClass);
            $output1 = $serializer->serialize($request1);

            $request2 = $deserializer->deserialize($output1, $requestClass);
            $output2 = $serializer->serialize($request2);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Request idempotency check failed: ' . $e->getMessage();
            self::fail('ESPD Request idempotency check failed: ' . $e->getMessage());
        }

        self::assertSame($output1, $output2, 'ESPD Request round-trip must be idempotent');
    }

    #[Test]
    #[Depends('deserializeEspdRequest')]
    public function deserializedEspdRequestHasTenderingCriteria(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-request.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $requestClass = self::$namespace . '\\Doc\\QualificationApplicationRequest';

        try {
            $request = $deserializer->deserialize($xml, $requestClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Request criteria check failed: ' . $e->getMessage();
            self::fail('ESPD Request criteria check failed: ' . $e->getMessage());
        }

        if (method_exists($request, 'getTenderingCriteria')) {
            $criteria = $request->getTenderingCriteria();
            self::assertIsArray($criteria);
            self::assertCount(2, $criteria, 'ESPD Request should have 2 tendering criteria');
        } elseif (method_exists($request, 'getTenderingCriterions')) {
            $criteria = $request->getTenderingCriterions();
            self::assertIsArray($criteria);
            self::assertCount(2, $criteria, 'ESPD Request should have 2 tendering criteria');
        } else {
            self::$roundTripIssues[] = 'ESPD Request missing getTenderingCriteria/getTenderingCriterions method';
            self::fail('ESPD Request must have tendering criteria accessor');
        }
    }

    #[Test]
    #[Depends('deserializeEspdResponse')]
    public function deserializedEspdResponseHasCriterionResponses(): void
    {
        $xml = file_get_contents(self::fixturesDir() . '/espd-response.xml');
        self::assertNotFalse($xml);

        $deserializer = new XmlDeserializer();
        $responseClass = self::$namespace . '\\Doc\\QualificationApplicationResponse';

        try {
            $response = $deserializer->deserialize($xml, $responseClass);
        } catch (\Throwable $e) {
            self::$roundTripIssues[] = 'ESPD Response criteria response check failed: ' . $e->getMessage();
            self::fail('ESPD Response criteria response check failed: ' . $e->getMessage());
        }

        if (method_exists($response, 'getTenderingCriterionResponses')) {
            $responses = $response->getTenderingCriterionResponses();
            self::assertIsArray($responses);
            self::assertCount(1, $responses, 'ESPD Response should have 1 criterion response');
        } else {
            self::$roundTripIssues[] = 'ESPD Response missing getTenderingCriterionResponses method';
            self::fail('ESPD Response must have criterion response accessor');
        }
    }

    private function assertEspdRequestBasicProperties(object $request): void
    {
        if (method_exists($request, 'getId')) {
            $id = $request->getId();
            self::assertNotNull($id, 'ESPD Request ID should not be null');
            if (is_object($id) && method_exists($id, 'getValue')) {
                self::assertSame('ESPD-REQ-001', $id->getValue());
            }
        } else {
            self::$roundTripIssues[] = 'ESPD Request missing getId() method';
            self::fail('ESPD Request must have getId() method');
        }

        if (method_exists($request, 'getUblVersionID')) {
            $version = $request->getUblVersionID();
            self::assertNotNull($version, 'UBLVersionID should not be null');
            if (is_object($version) && method_exists($version, 'getValue')) {
                self::assertSame('2.3', $version->getValue());
            }
        }

        if (method_exists($request, 'getContractFolderID')) {
            $folderId = $request->getContractFolderID();
            self::assertNotNull($folderId, 'ContractFolderID should not be null');
            if (is_object($folderId) && method_exists($folderId, 'getValue')) {
                self::assertSame('PROC-2025-001', $folderId->getValue());
            }
        }
    }

    private function assertEspdResponseBasicProperties(object $response): void
    {
        if (method_exists($response, 'getId')) {
            $id = $response->getId();
            self::assertNotNull($id, 'ESPD Response ID should not be null');
            if (is_object($id) && method_exists($id, 'getValue')) {
                self::assertSame('ESPD-RES-001', $id->getValue());
            }
        } else {
            self::$roundTripIssues[] = 'ESPD Response missing getId() method';
            self::fail('ESPD Response must have getId() method');
        }

        if (method_exists($response, 'getContractFolderID')) {
            $folderId = $response->getContractFolderID();
            self::assertNotNull($folderId, 'ContractFolderID should not be null');
        }

        if (method_exists($response, 'getEconomicOperatorParty')) {
            $eoParty = $response->getEconomicOperatorParty();
            self::assertNotNull($eoParty, 'EconomicOperatorParty should not be null');
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
            self::$roundTripIssues[] = "Expected {$localName}={$expectedValue} not found in ESPD round-trip output";
        }

        self::assertTrue($found, "Round-trip output should contain <{$localName}>{$expectedValue}</{$localName}>");
    }

    private static function fixturesDir(): string
    {
        return __DIR__ . '/../Fixtures/Xml';
    }
}
