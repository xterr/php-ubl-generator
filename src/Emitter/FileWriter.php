<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Emitter;

use Symfony\Component\Filesystem\Filesystem;

final class FileWriter
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $outputDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function write(string $relativePath, string $content): void
    {
        $path = rtrim($this->outputDir, '/') . '/' . $relativePath;
        $this->filesystem->mkdir(\dirname($path));
        $this->filesystem->dumpFile($path, $content);
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }
}
