<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Codelist;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Codelist\GenericodeParser;
use Xterr\UBL\Generator\Exception\GeneratorException;

final class GenericodeParserTest extends TestCase
{
    private GenericodeParser $parser;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->parser = new GenericodeParser();
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures/Codelists';
    }

    #[Test]
    public function parseExtractsMetadataCorrectly(): void
    {
        $codelist = $this->parser->parse($this->fixturesDir . '/TestCodelist.gc');

        self::assertSame('TestCodelist', $codelist->shortName);
        self::assertSame('test-codelist', $codelist->listID);
        self::assertSame('1.0.0', $codelist->version);
        self::assertSame('TA', $codelist->agencyID);
        self::assertSame('https://example.com/test/TestCodelist.gc', $codelist->locationUri);
    }

    #[Test]
    public function parseExtractsAllEntries(): void
    {
        $codelist = $this->parser->parse($this->fixturesDir . '/TestCodelist.gc');

        // 4 rows total (3 ACTIVE + 1 DEPRECATED)
        self::assertCount(4, $codelist->entries);

        self::assertSame('ALPHA', $codelist->entries[0]->code);
        self::assertSame('The first item', $codelist->entries[0]->name);
        self::assertSame('ACTIVE', $codelist->entries[0]->status);

        self::assertSame('BETA', $codelist->entries[1]->code);
        self::assertSame('The second item', $codelist->entries[1]->name);
        self::assertSame('ACTIVE', $codelist->entries[1]->status);

        self::assertSame('DEPRECATED_ITEM', $codelist->entries[2]->code);
        self::assertSame('DEPRECATED', $codelist->entries[2]->status);

        self::assertSame('gamma-value', $codelist->entries[3]->code);
        self::assertSame('ACTIVE', $codelist->entries[3]->status);
    }

    #[Test]
    public function parseDirectoryReturnsAllCodelistsKeyedByListId(): void
    {
        $codelists = $this->parser->parseDirectory($this->fixturesDir);

        self::assertArrayHasKey('test-codelist', $codelists);
        self::assertArrayHasKey('boolean-gui-control-type', $codelists);
        self::assertCount(2, $codelists);

        self::assertSame('TestCodelist', $codelists['test-codelist']->shortName);
        self::assertSame('BooleanGUIControl', $codelists['boolean-gui-control-type']->shortName);
    }

    #[Test]
    public function parseThrowsOnMissingFile(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('not found');

        $this->parser->parse('/nonexistent/path/file.gc');
    }

    #[Test]
    public function parseDirectoryThrowsOnMissingDir(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('not found');

        $this->parser->parseDirectory('/nonexistent/directory');
    }

    #[Test]
    public function parseDirectoryReturnsEmptyForDirWithNoGcFiles(): void
    {
        // Use a directory that exists but has no .gc files
        $codelists = $this->parser->parseDirectory(dirname(__DIR__, 2) . '/Fixtures/Xml');

        self::assertSame([], $codelists);
    }
}
