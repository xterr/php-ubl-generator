<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class TotalDigitsRule extends AbstractRule
{
    public function __construct(private readonly int $totalDigits)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf(
            '!\is_null($%s) && \preg_match_all(\'/[0-9]/\', (string) $%s) > %d',
            $paramName,
            $paramName,
            $this->totalDigits,
        );
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, total digits must not exceed %d, got %%s', \$%s)",
            $paramName,
            $this->totalDigits,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'totalDigits';
    }
}
