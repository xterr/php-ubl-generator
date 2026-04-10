<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class NumericStringRule extends AbstractRule
{
    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && !\is_numeric($%s)', $paramName, $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, expected numeric string, got %%s', get_debug_type(\$%s))",
            $paramName,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'numeric';
    }
}
