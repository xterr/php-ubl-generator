<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class MaxOccursRule extends AbstractRule
{
    public function __construct(private readonly int $maxOccurs)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('\is_array($%s) && \count($%s) > %d', $paramName, $paramName, $this->maxOccurs);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid count for %s, maximum is %d, got %%d', \\count(\$%s))",
            $paramName,
            $this->maxOccurs,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'maxOccurs';
    }
}
