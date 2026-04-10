<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Codelist;

final readonly class CodelistEntry
{
    public function __construct(
        public string $code,
        public ?string $name,
        public string $status,
    ) {}
}
