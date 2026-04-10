<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class MaxExclusiveRule extends AbstractRule
{
    public function __construct(private readonly string $max)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && (float) $%s >= %s', $paramName, $paramName, $this->max);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, must be less than %s, got %%s', \$%s)",
            $paramName,
            $this->max,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'maxExclusive';
    }
}
