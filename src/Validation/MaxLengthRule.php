<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class MaxLengthRule extends AbstractRule
{
    public function __construct(private readonly int $maxLength)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && \mb_strlen((string) $%s) > %d', $paramName, $paramName, $this->maxLength);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid length for %s, maximum is %d, got %%d', \\mb_strlen((string) \$%s))",
            $paramName,
            $this->maxLength,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'maxLength';
    }
}
