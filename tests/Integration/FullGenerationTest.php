<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\UblGenerator;

final class FullGenerationTest extends TestCase
{
    private static string $tempDir;
    private static \Xterr\UBL\Generator\GenerationResult $result;

    public static function setUpBeforeClass(): void
    {
        self::$tempDir = sys_get_temp_dir() . '/php-ubl-test-' . uniqid('', true);

        $config = GeneratorConfig::defaults()->withOverrides([
            'schema_dir' => __DIR__ . '/../Fixtures/Xsd',
            'output_dir' => self::$tempDir,
        ]);

        $generator = new UblGenerator($config);
        self::$result = $generator->generate();
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(self::$tempDir);
    }

    #[Test]
    public function cbcLeafClassesAreGenerated(): void
    {
        self::assertGreaterThan(0, self::$result->cbcClassCount);
        self::assertFileExists(self::$tempDir . '/Cbc/Amount.php');
        self::assertFileExists(self::$tempDir . '/Cbc/Code.php');
        self::assertFileExists(self::$tempDir . '/Cbc/Identifier.php');
    }

    #[Test]
    public function cacComplexClassesAreGenerated(): void
    {
        self::assertGreaterThan(0, self::$result->cacClassCount);
        self::assertFileExists(self::$tempDir . '/Cac/Address.php');
        self::assertFileExists(self::$tempDir . '/Cac/Party.php');
    }

    #[Test]
    public function documentRootClassesAreGenerated(): void
    {
        self::assertGreaterThanOrEqual(2, self::$result->docClassCount);
        self::assertFileExists(self::$tempDir . '/Doc/Invoice.php');
    }

    #[Test]
    public function registryFilesAreGenerated(): void
    {
        self::assertFileExists(self::$tempDir . '/Xml/DocumentRegistry.php');
        self::assertFileExists(self::$tempDir . '/Xml/ClassMap.php');
    }

    #[Test]
    public function totalFilesWrittenMatchesSum(): void
    {
        $expected = self::$result->cbcClassCount
            + self::$result->cacClassCount
            + self::$result->docClassCount
            + self::$result->enumCount
            + self::$result->codelistEnumCount
            + 2;

        self::assertSame($expected, self::$result->totalFilesWritten);
    }

    #[Test]
    public function extAggregateClassesLandInCac(): void
    {
        // EXT complex types with child elements go to Cac/, not Cbc/
        self::assertFileExists(self::$tempDir . '/Cac/UBLExtensions.php');
        self::assertFileExists(self::$tempDir . '/Cac/UBLExtension.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/UBLExtensions.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/UBLExtension.php');
    }

    #[Test]
    public function extLeafElementsCollapseIntoExistingCbcClasses(): void
    {
        // EXT simpleContent elements (ExtensionAgencyID, etc.) share the same UDT base
        // as CBC elements — they must NOT generate standalone classes
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionAgencyID.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionAgencyName.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionReasonCode.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionReason.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionURI.php');
        self::assertFileDoesNotExist(self::$tempDir . '/Cbc/ExtensionVersionID.php');

        // They should resolve to existing leaf classes that ARE generated
        self::assertFileExists(self::$tempDir . '/Cbc/Identifier.php');
        self::assertFileExists(self::$tempDir . '/Cbc/Text.php');
        self::assertFileExists(self::$tempDir . '/Cbc/Code.php');
    }

    #[Test]
    public function generatedExtAggregateClassIsSyntacticallyValid(): void
    {
        $this->assertPhpSyntaxValid(self::$tempDir . '/Cac/UBLExtensions.php');
        $this->assertPhpSyntaxValid(self::$tempDir . '/Cac/UBLExtension.php');
    }

    #[Test]
    public function generatedCbcClassIsSyntacticallyValid(): void
    {
        $this->assertPhpSyntaxValid(self::$tempDir . '/Cbc/Amount.php');
    }

    #[Test]
    public function generatedCacClassIsSyntacticallyValid(): void
    {
        $this->assertPhpSyntaxValid(self::$tempDir . '/Cac/Address.php');
    }

    #[Test]
    public function generatedDocClassIsSyntacticallyValid(): void
    {
        $this->assertPhpSyntaxValid(self::$tempDir . '/Doc/Invoice.php');
    }

    #[Test]
    public function generatedRegistryIsSyntacticallyValid(): void
    {
        $this->assertPhpSyntaxValid(self::$tempDir . '/Xml/DocumentRegistry.php');
        $this->assertPhpSyntaxValid(self::$tempDir . '/Xml/ClassMap.php');
    }

    private function assertPhpSyntaxValid(string $filePath): void
    {
        $output = [];
        $exitCode = 0;
        exec(sprintf('php -l %s 2>&1', escapeshellarg($filePath)), $output, $exitCode);
        self::assertSame(0, $exitCode, sprintf(
            "Syntax error in %s:\n%s",
            $filePath,
            implode("\n", $output),
        ));
    }
}
