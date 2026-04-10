<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class StringRule extends AbstractRule
{
    public function testCondition(string $paramName): string
    {
        return sprintf('!\is_null($%s) && !\is_string($%s)', $paramName, $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, expected string, got %%s', get_debug_type(\$%s))",
            $paramName,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'string';
    }
}
