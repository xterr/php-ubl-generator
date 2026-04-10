<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class LengthRule extends AbstractRule
{
    public function __construct(private readonly int $exactLength)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && \mb_strlen((string) $%s) !== %d', $paramName, $paramName, $this->exactLength);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid length for %s, expected exactly %d, got %%d', \\mb_strlen((string) \$%s))",
            $paramName,
            $this->exactLength,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'length';
    }
}
