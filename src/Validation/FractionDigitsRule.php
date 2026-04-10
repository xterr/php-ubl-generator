<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class FractionDigitsRule extends AbstractRule
{
    public function __construct(private readonly int $fractionDigits)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf(
            "!\is_null(\$%s) && \str_contains((string) \$%s, '.') && \strlen(\\explode('.', (string) \$%s)[1]) > %d",
            $paramName,
            $paramName,
            $paramName,
            $this->fractionDigits,
        );
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, fraction digits must not exceed %d, got %%s', \$%s)",
            $paramName,
            $this->fractionDigits,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'fractionDigits';
    }
}
