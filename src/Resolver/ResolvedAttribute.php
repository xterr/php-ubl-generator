<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

final readonly class ResolvedAttribute
{
    public function __construct(
        public string $xmlName,
        public string $phpName,
        public string $phpType,
        public bool $required,
    ) {}
}
