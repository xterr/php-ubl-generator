<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Integration\Xsd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Exception\SchemaParseException;
use Xterr\UBL\Generator\Xsd\SchemaLoader;

final class SchemaLoaderTest extends TestCase
{
    private const string XSD_DIR = __DIR__ . '/../../../resources/schemas/2.4/xsd';

    #[Test]
    public function loadAllReturns93Schemas(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        self::assertCount(93, $schemas);
    }

    #[Test]
    public function loadAllReturnsSchemaObjectsWithNamespaces(): void
    {
        $loader = new SchemaLoader();
        $schemas = $loader->loadAll(self::XSD_DIR);

        foreach ($schemas as $schema) {
            self::assertNotNull($schema->getTargetNamespace());
        }
    }

    #[Test]
    public function loadFileParsesInvoiceSchema(): void
    {
        $loader = new SchemaLoader();
        $schema = $loader->loadFile(self::XSD_DIR . '/maindoc/UBL-Invoice-2.4.xsd');

        self::assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            $schema->getTargetNamespace(),
        );
    }

    #[Test]
    public function loadFileImportsCommonSchemas(): void
    {
        $loader = new SchemaLoader();
        $schema = $loader->loadFile(self::XSD_DIR . '/maindoc/UBL-Invoice-2.4.xsd');

        self::assertNotEmpty($schema->getSchemas());
    }

    #[Test]
    public function loadAllThrowsOnMissingDirectory(): void
    {
        $this->expectException(SchemaParseException::class);

        $loader = new SchemaLoader();
        $loader->loadAll('/nonexistent/path');
    }

    #[Test]
    public function loadFileThrowsOnMissingFile(): void
    {
        $this->expectException(SchemaParseException::class);

        $loader = new SchemaLoader();
        $loader->loadFile('/nonexistent/file.xsd');
    }
}
