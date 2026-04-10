<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class Rules
{
    /**
     * @param list<AbstractRule> $rules
     */
    public function __construct(private readonly array $rules = [])
    {
    }

    /**
     * @return list<AbstractRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @return list<string> All validation code lines
     */
    public function generateAllValidationLines(string $paramName): array
    {
        $lines = [];
        foreach ($this->rules as $rule) {
            $generated = $rule->generateValidationBlock($paramName);
            if ($generated !== []) {
                $lines = [...$lines, ...$generated];
            }
        }

        return $lines;
    }

    /**
     * Returns all ArrayRule instances (for static method generation).
     *
     * @return list<ArrayRule>
     */
    public function arrayRules(): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof ArrayRule) {
                $result[] = $rule;
            }
        }

        return $result;
    }

    /**
     * Returns all UnionRule instances (for static method generation).
     *
     * @return list<UnionRule>
     */
    public function unionRules(): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof UnionRule) {
                $result[] = $rule;
            }
        }

        return $result;
    }

    /**
     * Returns whether any rule requires a static validation method.
     */
    public function hasStaticMethods(): bool
    {
        return $this->arrayRules() !== [] || $this->unionRules() !== [];
    }
}
