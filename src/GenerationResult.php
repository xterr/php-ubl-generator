<?php declare(strict_types=1);

namespace Xterr\UBL\Generator;

final readonly class GenerationResult
{
    /**
     * @param array{complexTypes: int, simpleTypes: int, globalElements: int, namespaces: int} $stats
     */
    public function __construct(
        public string $schemaVersion,
        public array $stats,
        public int $cbcClassCount,
        public int $cacClassCount,
        public int $docClassCount,
        public int $enumCount,
        public int $totalFilesWritten,
    ) {}

    public function summary(): string
    {
        return sprintf(
            'UBL %s: %d CBC classes, %d CAC classes, %d document roots, %d enums, %d total files',
            $this->schemaVersion,
            $this->cbcClassCount,
            $this->cacClassCount,
            $this->docClassCount,
            $this->enumCount,
            $this->totalFilesWritten,
        );
    }
}
