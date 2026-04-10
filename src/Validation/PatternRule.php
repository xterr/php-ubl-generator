<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class PatternRule extends AbstractRule
{
    public function __construct(private readonly string $pattern)
    {
    }

    public function testCondition(string $paramName): string
    {
        $escaped = addcslashes($this->pattern, "'");

        return sprintf("!\is_null(\$%s) && !\preg_match('/%s/', (string) \$%s)", $paramName, $escaped, $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        $escaped = addcslashes($this->pattern, "'");

        return sprintf(
            "sprintf('Invalid value for %s, must match pattern /%s/, got %%s', \$%s)",
            $paramName,
            $escaped,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'pattern';
    }
}
