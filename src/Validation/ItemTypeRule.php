<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class ItemTypeRule extends AbstractRule
{
    public function __construct(private readonly string $expectedFqcn)
    {
    }

    public function testCondition(string $paramName): string
    {
        return sprintf('!\$%s instanceof %s', $paramName, $this->expectedFqcn);
    }

    public function errorMessage(string $paramName): string
    {
        return sprintf(
            "sprintf('Invalid value for %s, expected %s, got %%s', get_debug_type(\$%s))",
            $paramName,
            $this->expectedFqcn,
            $paramName,
        );
    }

    public function name(): string
    {
        return 'itemType';
    }
}
