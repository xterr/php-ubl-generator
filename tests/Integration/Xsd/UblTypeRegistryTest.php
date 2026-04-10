<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Integration\Xsd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Xsd\SchemaLoader;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class UblTypeRegistryTest extends TestCase
{
    private const XSD_DIR = __DIR__ . '/../../Fixtures/Xsd';

    private static UblTypeRegistry $registry;

    public static function setUpBeforeClass(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        self::$registry = new UblTypeRegistry();
        self::$registry->populate($schemas);
    }

    #[Test]
    public function cacNamespaceContainsExpectedComplexTypes(): void
    {
        $types = self::$registry->complexTypesInNamespace(XmlNamespace::CAC);

        self::assertGreaterThan(0, count($types));
    }

    #[Test]
    public function cacNamespaceContainsAddressType(): void
    {
        $types = self::$registry->complexTypesInNamespace(XmlNamespace::CAC);
        $names = array_map(static fn ($t) => $t->getName(), $types);

        self::assertContains('AddressType', $names);
    }

    #[Test]
    public function cacNamespaceContainsPartyType(): void
    {
        $types = self::$registry->complexTypesInNamespace(XmlNamespace::CAC);
        $names = array_map(static fn ($t) => $t->getName(), $types);

        self::assertContains('PartyType', $names);
    }

    #[Test]
    public function cbcNamespaceContainsGlobalElements(): void
    {
        $elements = self::$registry->globalElementsInNamespace(XmlNamespace::CBC);

        self::assertNotEmpty($elements);
    }

    #[Test]
    public function cbcNamespaceContainsIdElement(): void
    {
        $elements = self::$registry->globalElementsInNamespace(XmlNamespace::CBC);
        $names = array_map(static fn ($e) => $e->getName(), $elements);

        self::assertContains('ID', $names);
    }

    #[Test]
    public function cbcNamespaceContainsAmountElement(): void
    {
        $elements = self::$registry->globalElementsInNamespace(XmlNamespace::CBC);
        $names = array_map(static fn ($e) => $e->getName(), $elements);

        self::assertContains('Amount', $names);
    }

    #[Test]
    public function cbcNamespaceContainsNameElement(): void
    {
        $elements = self::$registry->globalElementsInNamespace(XmlNamespace::CBC);
        $names = array_map(static fn ($e) => $e->getName(), $elements);

        self::assertContains('Name', $names);
    }

    #[Test]
    public function documentRootElementsReturns2Elements(): void
    {
        $roots = self::$registry->documentRootElements();

        self::assertCount(2, $roots);
    }

    #[Test]
    public function simpleTypesWithEnumerationsDoesNotFail(): void
    {
        $enumTypes = self::$registry->simpleTypesWithEnumerations();

        self::assertGreaterThanOrEqual(0, count($enumTypes));
    }

    #[Test]
    public function statsReturnsReasonableCounts(): void
    {
        $stats = self::$registry->stats();

        self::assertGreaterThan(0, $stats['complexTypes']);
        self::assertGreaterThan(0, $stats['simpleTypes']);
        self::assertGreaterThan(0, $stats['globalElements']);
        self::assertGreaterThan(0, $stats['namespaces']);
    }

    #[Test]
    public function allNamespacesContainsCbcAndCac(): void
    {
        $namespaces = self::$registry->allNamespaces();

        self::assertContains(XmlNamespace::CBC, $namespaces);
        self::assertContains(XmlNamespace::CAC, $namespaces);
    }
}
