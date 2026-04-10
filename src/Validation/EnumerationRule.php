<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class EnumerationRule extends AbstractRule
{
    public function __construct(private readonly string $enumFqcn)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && %s::tryFrom($%s) === null', $paramName, $this->enumFqcn, $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, must be a valid %s value, got %%s', \$%s)",
            $paramName,
            $this->enumFqcn,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'enumeration';
    }
}
