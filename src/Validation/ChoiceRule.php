<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Validation;

final class ChoiceRule extends AbstractRule
{
    /**
     * @param list<string> $siblingProperties Other property names in the choice group
     */
    public function __construct(private readonly array $siblingProperties)
    {
    }

    public function testCondition(string $paramName): string
    {
        if ($this->siblingProperties === []) {
            return '';
        }

        $checks = [];
        foreach ($this->siblingProperties as $prop) {
            $checks[] = sprintf('$this->%s !== null', $prop);
        }

        return sprintf('!\is_null($%s) && (%s)', $paramName, implode(' || ', $checks));
    }

    public function errorMessage(string $paramName): string
    {
        $siblings = implode(', ', $this->siblingProperties);

        return sprintf(
            "'%s is mutually exclusive with: %s'",
            $paramName,
            $siblings,
        );
    }

    public function name(): string
    {
        return 'choice';
    }
}
