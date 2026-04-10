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
        self::assertGreaterThan(100, self::$result->cacClassCount);
        self::assertFileExists(self::$tempDir . '/Cac/Address.php');
        self::assertFileExists(self::$tempDir . '/Cac/Party.php');
    }

    #[Test]
    public function documentRootClassesAreGenerated(): void
    {
        self::assertGreaterThanOrEqual(93, self::$result->docClassCount);
        self::assertFileExists(self::$tempDir . '/Doc/Invoice.php');
    }

    #[Test]
    public function registryFilesAreGenerated(): void
    {
        self::assertFileExists(self::$tempDir . '/Xml/DocumentRegistry.php');
        self::assertFileExists(self::$tempDir . '/Xml/TypeMap.php');
    }

    #[Test]
    public function totalFilesWrittenMatchesSum(): void
    {
        $expected = self::$result->cbcClassCount
            + self::$result->cacClassCount
            + self::$result->docClassCount
            + self::$result->enumCount
            + 2;

        self::assertSame($expected, self::$result->totalFilesWritten);
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
        $this->assertPhpSyntaxValid(self::$tempDir . '/Xml/TypeMap.php');
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
