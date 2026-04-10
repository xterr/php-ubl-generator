<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class ArrayRule extends AbstractRule
{
    public function __construct(private readonly string $expectedItemFqcn)
    {
    }

    public function staticMethodName(string $propertyName): string
    {
        return sprintf('validate%sForItemTypeConstraint', ucfirst($propertyName));
    }

    public function testCondition(string $paramName): string
    {
        return sprintf("'' !== (\$msg = self::%s(\$%s))", $this->staticMethodName($paramName), $paramName);
    }

    public function errorMessage(string $paramName): string
    {
        return '$msg';
    }

    public function name(): string
    {
        return 'arrayItemType';
    }

    /**
     * Generate the static validation method body lines.
     *
     * @return list<string>
     */
    public function generateStaticValidationMethod(string $propertyName): array
    {
        return [
            'if (!\is_array($values)) {',
            "    return '';",
            '}',
            '$invalid = [];',
            'foreach ($values as $item) {',
            sprintf('    if (!$item instanceof %s) {', $this->expectedItemFqcn),
            '        $invalid[] = get_debug_type($item);',
            '    }',
            '}',
            'if ($invalid !== []) {',
            sprintf("    return sprintf('Items must be %s, got: %%s', implode(', ', \$invalid));", $this->expectedItemFqcn),
            '}',
            "return '';",
        ];
    }
}
