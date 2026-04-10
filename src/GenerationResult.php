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
        public int $codelistEnumCount = 0,
        public int $totalFilesWritten = 0,
    ) {}

    public function summary(): string
    {
        $parts = [
            sprintf('UBL %s:', $this->schemaVersion),
            sprintf('%d CBC classes', $this->cbcClassCount),
            sprintf('%d CAC classes', $this->cacClassCount),
            sprintf('%d document roots', $this->docClassCount),
            sprintf('%d enums', $this->enumCount),
        ];

        if ($this->codelistEnumCount > 0) {
            $parts[] = sprintf('%d codelist enums', $this->codelistEnumCount);
        }

        $parts[] = sprintf('%d total files', $this->totalFilesWritten);

        return implode(', ', $parts);
    }
}
