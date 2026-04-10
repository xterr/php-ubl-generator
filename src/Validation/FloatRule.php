<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class FloatRule extends AbstractRule
{
    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && (!\is_float($%s) && !\is_int($%s))', $paramName, $paramName, $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, expected float, got %%s', get_debug_type(\$%s))",
            $paramName,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'float';
    }
}
