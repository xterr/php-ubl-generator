<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Codelist;

final readonly class ParsedCodelist
{
    /**
     * @param list<CodelistEntry> $entries
     */
    public function __construct(
        public string $shortName,
        public string $listID,
        public ?string $version,
        public ?string $agencyID,
        public ?string $locationUri,
        public array $entries,
    ) {}
}
