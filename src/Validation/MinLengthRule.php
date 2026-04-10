<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class MinLengthRule extends AbstractRule
{
    public function __construct(private readonly int $minLength)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && \mb_strlen((string) $%s) < %d', $paramName, $paramName, $this->minLength);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid length for %s, minimum is %d, got %%d', \\mb_strlen((string) \$%s))",
            $paramName,
            $this->minLength,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'minLength';
    }
}
