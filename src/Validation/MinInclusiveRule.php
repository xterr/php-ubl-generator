<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class MinInclusiveRule extends AbstractRule
{
    public function __construct(private readonly string $min)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && (float) $%s < %s', $paramName, $paramName, $this->min);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, minimum inclusive is %s, got %%s', \$%s)",
            $paramName,
            $this->min,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'minInclusive';
    }
}
