<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

final readonly class ResolvedLeafType
{
    /**
     * @param string $className PHP class name (e.g., 'Amount', 'Code', 'Identifier')
     * @param string $valuePhpType PHP type for the value content (always 'string' for decimals per decision)
     * @param list<ResolvedAttribute> $attributes XML attributes on this type
     * @param list<string> $cbcElementNames All CBC element names that map to this leaf type
     */
    public function __construct(
        public string $className,
        public string $valuePhpType,
        public array $attributes,
        public array $cbcElementNames,
    ) {}
}
