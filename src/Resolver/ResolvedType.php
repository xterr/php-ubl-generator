<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

final readonly class ResolvedType
{
    public function __construct(
        public string $phpType,
        public bool $isPrimitive,
        public bool $isLeafType,
        public bool $isArray,
        public bool $isNullable,
        public string $xmlElementName,
        public string $xmlNamespace,
        public ?string $choiceGroup,
    ) {}
}
