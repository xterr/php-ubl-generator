<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Emitter;

final readonly class ResolvedProperty
{
    public function __construct(
        public string $phpName,
        public string $phpType,
        public bool $isNullable,
        public bool $isArray,
        public string $xmlElementName,
        public string $xmlNamespace,
        public ?string $innerType,
        public ?string $choiceGroup,
        public ?string $documentation,
        public bool $required,
    ) {}
}
