<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

abstract class AbstractRule
{
    /**
     * Returns the PHP condition expression that evaluates to true when validation FAILS.
     */
    abstract public function testCondition(string $paramName): string;

    /**
     * Returns the PHP expression for the exception message when validation fails.
     */
    abstract public function errorMessage(string $paramName): string;

    /**
     * Returns the rule name for the comment (e.g., 'string', 'pattern', 'maxOccurs').
     */
    abstract public function name(): string;

    /**
     * Generate the complete if/throw validation block as PHP code lines.
     *
     * @return list<string> Lines of PHP code
     */
    public function generateValidationBlock(string $paramName): array
    {
        $condition = $this->testCondition($paramName);
        if ($condition === '') {
            return [];
        }

        return [
            sprintf('// validation for constraint: %s', $this->name()),
            sprintf('if (%s) {', $condition),
            sprintf('    throw new \InvalidArgumentException(%s);', $this->errorMessage($paramName)),
            '}',
        ];
    }
}
