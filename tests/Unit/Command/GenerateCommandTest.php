<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Xterr\UBL\Generator\Command\GenerateCommand;

final class GenerateCommandTest extends TestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->addCommand(new GenerateCommand());

        $command = $application->find('ubl:generate');
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function dryRunExitsSuccessfullyAndShowsWarning(): void
    {
        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Dry-run mode', $this->tester->getDisplay());
        self::assertStringContainsString('Would Generate', $this->tester->getDisplay());
    }

    #[Test]
    public function forceOptionExitsSuccessfully(): void
    {
        $tempDir = sys_get_temp_dir() . '/php-ubl-cmd-test-' . uniqid();

        try {
            $this->tester->execute([
                '--force' => true,
                '--output-dir' => $tempDir,
            ]);

            self::assertSame(0, $this->tester->getStatusCode());
            self::assertStringContainsString('Generation Result', $this->tester->getDisplay());
            self::assertStringNotContainsString('Dry-run mode', $this->tester->getDisplay());
        } finally {
            (new Filesystem())->remove($tempDir);
        }
    }

    #[Test]
    public function schemaVersionOverrideIsApplied(): void
    {
        $this->tester->execute(['--schema-version' => '2.1']);

        $display = $this->tester->getDisplay();
        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('2.1', $display);
    }

    #[Test]
    public function nonExistentConfigFallsBackToDefaults(): void
    {
        $this->tester->execute(['--config' => '/nonexistent/path/config.yaml']);

        $display = $this->tester->getDisplay();
        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('2.4', $display);
    }
}
