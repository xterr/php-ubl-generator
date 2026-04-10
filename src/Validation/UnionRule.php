<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class UnionRule extends AbstractRule
{
    /**
     * @param list<AbstractRule> $memberRules
     */
    public function __construct(private readonly array $memberRules)
    {
    }

    public function staticMethodName(string $propertyName): string
    {
        return sprintf('validate%sForUnionConstraint', ucfirst($propertyName));
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
        return 'union';
    }

    /**
     * Generate the static validation method body lines.
     *
     * @return list<string>
     */
    public function generateStaticValidationMethod(string $propertyName): array
    {
        $lines = [];
        $lines[] = sprintf('if (\is_null($%s)) {', $propertyName);
        $lines[] = "    return '';";
        $lines[] = '}';
        $lines[] = '$errors = [];';

        foreach ($this->memberRules as $i => $rule) {
            $condition = $rule->testCondition($propertyName);
            if ($condition === '') {
                continue;
            }
            $lines[] = sprintf('if (%s) {', $condition);
            $lines[] = sprintf('    $errors[] = %s;', $rule->errorMessage($propertyName));
            $lines[] = '} else {';
            $lines[] = "    return '';";
            $lines[] = '}';
        }

        $ruleNames = array_map(static fn (AbstractRule $r): string => $r->name(), $this->memberRules);
        $lines[] = 'if ($errors !== []) {';
        $lines[] = sprintf(
            "    return sprintf('Value for %s does not match any union member type [%s]: %%s', implode('; ', \$errors));",
            $propertyName,
            implode(', ', $ruleNames),
        );
        $lines[] = '}';
        $lines[] = "return '';";

        return $lines;
    }
}
