<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Validation\ArrayRule;
use Xterr\UBL\Generator\Validation\BoolRule;
use Xterr\UBL\Generator\Validation\ChoiceRule;
use Xterr\UBL\Generator\Validation\EnumerationRule;
use Xterr\UBL\Generator\Validation\FloatRule;
use Xterr\UBL\Generator\Validation\FractionDigitsRule;
use Xterr\UBL\Generator\Validation\IntRule;
use Xterr\UBL\Generator\Validation\ItemTypeRule;
use Xterr\UBL\Generator\Validation\LengthRule;
use Xterr\UBL\Generator\Validation\MaxExclusiveRule;
use Xterr\UBL\Generator\Validation\MaxInclusiveRule;
use Xterr\UBL\Generator\Validation\MaxLengthRule;
use Xterr\UBL\Generator\Validation\MaxOccursRule;
use Xterr\UBL\Generator\Validation\MinExclusiveRule;
use Xterr\UBL\Generator\Validation\MinInclusiveRule;
use Xterr\UBL\Generator\Validation\MinLengthRule;
use Xterr\UBL\Generator\Validation\NumericStringRule;
use Xterr\UBL\Generator\Validation\PatternRule;
use Xterr\UBL\Generator\Validation\Rules;
use Xterr\UBL\Generator\Validation\StringRule;
use Xterr\UBL\Generator\Validation\TotalDigitsRule;
use Xterr\UBL\Generator\Validation\UnionRule;

final class RulesTest extends TestCase
{
    #[Test]
    public function stringRuleGeneratesIsStringCheck(): void
    {
        $rule = new StringRule();
        $condition = $rule->testCondition('value');

        self::assertStringContainsString('is_string', $condition);
        self::assertStringContainsString('$value', $condition);
        self::assertSame('string', $rule->name());
    }

    #[Test]
    public function intRuleGeneratesIsIntCheck(): void
    {
        $rule = new IntRule();
        $condition = $rule->testCondition('amount');

        self::assertStringContainsString('is_int', $condition);
        self::assertStringContainsString('$amount', $condition);
        self::assertSame('int', $rule->name());
    }

    #[Test]
    public function floatRuleGeneratesIsFloatAndIsIntCheck(): void
    {
        $rule = new FloatRule();
        $condition = $rule->testCondition('price');

        self::assertStringContainsString('is_float', $condition);
        self::assertStringContainsString('is_int', $condition);
        self::assertStringContainsString('$price', $condition);
        self::assertSame('float', $rule->name());
    }

    #[Test]
    public function boolRuleGeneratesIsBoolCheck(): void
    {
        $rule = new BoolRule();
        $condition = $rule->testCondition('active');

        self::assertStringContainsString('is_bool', $condition);
        self::assertStringContainsString('$active', $condition);
        self::assertSame('bool', $rule->name());
    }

    #[Test]
    public function numericStringRuleGeneratesIsNumericCheck(): void
    {
        $rule = new NumericStringRule();
        $condition = $rule->testCondition('amount');

        self::assertStringContainsString('is_numeric', $condition);
        self::assertStringContainsString('$amount', $condition);
        self::assertSame('numeric', $rule->name());
    }

    #[Test]
    public function patternRuleGeneratesRegexCheck(): void
    {
        $rule = new PatternRule('[A-Z]{2}');
        $condition = $rule->testCondition('code');

        self::assertStringContainsString('preg_match', $condition);
        self::assertStringContainsString('[A-Z]{2}', $condition);
        self::assertStringContainsString('$code', $condition);
        self::assertSame('pattern', $rule->name());
    }

    #[Test]
    public function patternRuleEscapesSingleQuotes(): void
    {
        $rule = new PatternRule("it's");
        $condition = $rule->testCondition('val');

        self::assertStringContainsString("it\\'s", $condition);
    }

    #[Test]
    public function enumerationRuleGeneratesTryFromCheck(): void
    {
        $rule = new EnumerationRule('\App\Enum\CurrencyCode');
        $condition = $rule->testCondition('currency');

        self::assertStringContainsString('\App\Enum\CurrencyCode::tryFrom', $condition);
        self::assertStringContainsString('$currency', $condition);
        self::assertStringContainsString('null', $condition);
        self::assertSame('enumeration', $rule->name());
    }

    #[Test]
    public function itemTypeRuleGeneratesInstanceofCheck(): void
    {
        $rule = new ItemTypeRule('\App\Entity\Line');
        $condition = $rule->testCondition('item');

        self::assertStringContainsString('instanceof', $condition);
        self::assertStringContainsString('\App\Entity\Line', $condition);
        self::assertStringContainsString('$item', $condition);
        self::assertSame('itemType', $rule->name());
    }

    #[Test]
    public function arrayRuleGeneratesStaticMethodCall(): void
    {
        $rule = new ArrayRule('\App\Entity\Line');
        $condition = $rule->testCondition('lines');

        self::assertStringContainsString('self::', $condition);
        self::assertStringContainsString('validateLinesForItemTypeConstraint', $condition);
        self::assertSame('$msg', $rule->errorMessage('lines'));
        self::assertSame('arrayItemType', $rule->name());
    }

    #[Test]
    public function arrayRuleGeneratesStaticValidationMethod(): void
    {
        $rule = new ArrayRule('\App\Entity\Line');
        $lines = $rule->generateStaticValidationMethod('lines');

        self::assertNotEmpty($lines);

        $body = implode("\n", $lines);
        self::assertStringContainsString('is_array', $body);
        self::assertStringContainsString('foreach', $body);
        self::assertStringContainsString('instanceof \App\Entity\Line', $body);
        self::assertStringContainsString('get_debug_type', $body);
        self::assertStringContainsString("return ''", $body);
    }

    #[Test]
    public function arrayRuleStaticMethodNameUsesPropertyName(): void
    {
        $rule = new ArrayRule('\App\Entity\Line');

        self::assertSame('validateLinesForItemTypeConstraint', $rule->staticMethodName('lines'));
        self::assertSame('validateItemsForItemTypeConstraint', $rule->staticMethodName('items'));
    }

    #[Test]
    public function maxOccursRuleGeneratesCountCheck(): void
    {
        $rule = new MaxOccursRule(5);
        $condition = $rule->testCondition('items');

        self::assertStringContainsString('count', $condition);
        self::assertStringContainsString('> 5', $condition);
        self::assertStringContainsString('$items', $condition);
        self::assertSame('maxOccurs', $rule->name());
    }

    #[Test]
    public function choiceRuleChecksSiblingProperties(): void
    {
        $rule = new ChoiceRule(['name', 'description']);
        $condition = $rule->testCondition('title');

        self::assertStringContainsString('$this->name !== null', $condition);
        self::assertStringContainsString('$this->description !== null', $condition);
        self::assertStringContainsString('$title', $condition);
        self::assertSame('choice', $rule->name());
    }

    #[Test]
    public function choiceRuleWithEmptySiblingsReturnsEmptyCondition(): void
    {
        $rule = new ChoiceRule([]);
        $condition = $rule->testCondition('title');

        self::assertSame('', $condition);
    }

    #[Test]
    public function choiceRuleWithEmptySiblingsGeneratesEmptyBlock(): void
    {
        $rule = new ChoiceRule([]);
        $block = $rule->generateValidationBlock('title');

        self::assertSame([], $block);
    }

    #[Test]
    public function unionRuleGeneratesStaticMethodCall(): void
    {
        $rule = new UnionRule([new StringRule(), new IntRule()]);
        $condition = $rule->testCondition('value');

        self::assertStringContainsString('self::', $condition);
        self::assertStringContainsString('validateValueForUnionConstraint', $condition);
        self::assertSame('$msg', $rule->errorMessage('value'));
        self::assertSame('union', $rule->name());
    }

    #[Test]
    public function unionRuleGeneratesStaticValidationMethod(): void
    {
        $rule = new UnionRule([new StringRule(), new IntRule()]);
        $lines = $rule->generateStaticValidationMethod('value');

        self::assertNotEmpty($lines);

        $body = implode("\n", $lines);
        self::assertStringContainsString('is_null', $body);
        self::assertStringContainsString('$errors', $body);
        self::assertStringContainsString("return ''", $body);
        self::assertStringContainsString('string, int', $body);
    }

    #[Test]
    public function unionRuleStaticMethodNameUsesPropertyName(): void
    {
        $rule = new UnionRule([]);

        self::assertSame('validateAmountForUnionConstraint', $rule->staticMethodName('amount'));
    }

    #[Test]
    public function minLengthRuleGeneratesMbStrlenCheck(): void
    {
        $rule = new MinLengthRule(3);
        $condition = $rule->testCondition('code');

        self::assertStringContainsString('mb_strlen', $condition);
        self::assertStringContainsString('< 3', $condition);
        self::assertStringContainsString('$code', $condition);
        self::assertSame('minLength', $rule->name());
    }

    #[Test]
    public function maxLengthRuleGeneratesMbStrlenCheck(): void
    {
        $rule = new MaxLengthRule(100);
        $condition = $rule->testCondition('name');

        self::assertStringContainsString('mb_strlen', $condition);
        self::assertStringContainsString('> 100', $condition);
        self::assertStringContainsString('$name', $condition);
        self::assertSame('maxLength', $rule->name());
    }

    #[Test]
    public function lengthRuleGeneratesExactMbStrlenCheck(): void
    {
        $rule = new LengthRule(2);
        $condition = $rule->testCondition('iso');

        self::assertStringContainsString('mb_strlen', $condition);
        self::assertStringContainsString('!== 2', $condition);
        self::assertStringContainsString('$iso', $condition);
        self::assertSame('length', $rule->name());
    }

    #[Test]
    public function minInclusiveRuleGeneratesLessThanCheck(): void
    {
        $rule = new MinInclusiveRule('0');
        $condition = $rule->testCondition('quantity');

        self::assertStringContainsString('(float)', $condition);
        self::assertStringContainsString('< 0', $condition);
        self::assertStringContainsString('$quantity', $condition);
        self::assertSame('minInclusive', $rule->name());
    }

    #[Test]
    public function maxInclusiveRuleGeneratesGreaterThanCheck(): void
    {
        $rule = new MaxInclusiveRule('999');
        $condition = $rule->testCondition('quantity');

        self::assertStringContainsString('(float)', $condition);
        self::assertStringContainsString('> 999', $condition);
        self::assertStringContainsString('$quantity', $condition);
        self::assertSame('maxInclusive', $rule->name());
    }

    #[Test]
    public function minExclusiveRuleGeneratesLessThanOrEqualCheck(): void
    {
        $rule = new MinExclusiveRule('0');
        $condition = $rule->testCondition('rate');

        self::assertStringContainsString('(float)', $condition);
        self::assertStringContainsString('<= 0', $condition);
        self::assertStringContainsString('$rate', $condition);
        self::assertSame('minExclusive', $rule->name());
    }

    #[Test]
    public function maxExclusiveRuleGeneratesGreaterThanOrEqualCheck(): void
    {
        $rule = new MaxExclusiveRule('100');
        $condition = $rule->testCondition('percent');

        self::assertStringContainsString('(float)', $condition);
        self::assertStringContainsString('>= 100', $condition);
        self::assertStringContainsString('$percent', $condition);
        self::assertSame('maxExclusive', $rule->name());
    }

    #[Test]
    public function totalDigitsRuleGeneratesDigitCountCheck(): void
    {
        $rule = new TotalDigitsRule(10);
        $condition = $rule->testCondition('number');

        self::assertStringContainsString('preg_match_all', $condition);
        self::assertStringContainsString('[0-9]', $condition);
        self::assertStringContainsString('> 10', $condition);
        self::assertStringContainsString('$number', $condition);
        self::assertSame('totalDigits', $rule->name());
    }

    #[Test]
    public function fractionDigitsRuleGeneratesFractionCheck(): void
    {
        $rule = new FractionDigitsRule(2);
        $condition = $rule->testCondition('amount');

        self::assertStringContainsString('str_contains', $condition);
        self::assertStringContainsString('explode', $condition);
        self::assertStringContainsString('> 2', $condition);
        self::assertStringContainsString('$amount', $condition);
        self::assertSame('fractionDigits', $rule->name());
    }

    #[Test]
    public function generateValidationBlockProducesIfThrowStructure(): void
    {
        $rule = new StringRule();
        $block = $rule->generateValidationBlock('value');

        self::assertCount(4, $block);
        self::assertStringContainsString('// validation for constraint: string', $block[0]);
        self::assertStringStartsWith('if (', $block[1]);
        self::assertStringContainsString('throw new \InvalidArgumentException', $block[2]);
        self::assertSame('}', $block[3]);
    }

    #[Test]
    public function rulesGenerateAllValidationLinesFromMultipleRules(): void
    {
        $rules = new Rules([
            new StringRule(),
            new MinLengthRule(1),
            new MaxLengthRule(50),
        ]);

        $lines = $rules->generateAllValidationLines('name');

        self::assertNotEmpty($lines);

        $body = implode("\n", $lines);
        self::assertStringContainsString('constraint: string', $body);
        self::assertStringContainsString('constraint: minLength', $body);
        self::assertStringContainsString('constraint: maxLength', $body);
        self::assertSame(3, substr_count($body, 'throw new \InvalidArgumentException'));
    }

    #[Test]
    public function rulesIsEmptyWhenNoRules(): void
    {
        $rules = new Rules();

        self::assertTrue($rules->isEmpty());
        self::assertSame([], $rules->all());
        self::assertSame([], $rules->generateAllValidationLines('x'));
    }

    #[Test]
    public function rulesIsNotEmptyWithRules(): void
    {
        $rules = new Rules([new StringRule()]);

        self::assertFalse($rules->isEmpty());
        self::assertCount(1, $rules->all());
    }

    #[Test]
    public function rulesReturnsArrayRules(): void
    {
        $arrayRule = new ArrayRule('\App\Entity\Line');
        $rules = new Rules([new StringRule(), $arrayRule, new IntRule()]);

        $arrayRules = $rules->arrayRules();
        self::assertCount(1, $arrayRules);
        self::assertSame($arrayRule, $arrayRules[0]);
    }

    #[Test]
    public function rulesReturnsUnionRules(): void
    {
        $unionRule = new UnionRule([new StringRule()]);
        $rules = new Rules([new IntRule(), $unionRule]);

        $unionRules = $rules->unionRules();
        self::assertCount(1, $unionRules);
        self::assertSame($unionRule, $unionRules[0]);
    }

    #[Test]
    public function rulesHasStaticMethodsReturnsTrueWithArrayRule(): void
    {
        $rules = new Rules([new ArrayRule('\App\Entity\Line')]);

        self::assertTrue($rules->hasStaticMethods());
    }

    #[Test]
    public function rulesHasStaticMethodsReturnsTrueWithUnionRule(): void
    {
        $rules = new Rules([new UnionRule([new StringRule()])]);

        self::assertTrue($rules->hasStaticMethods());
    }

    #[Test]
    public function rulesHasStaticMethodsReturnsFalseWithoutSpecialRules(): void
    {
        $rules = new Rules([new StringRule(), new MinLengthRule(1)]);

        self::assertFalse($rules->hasStaticMethods());
    }

    #[Test]
    public function typeCheckRulesAllowNull(): void
    {
        $typeRules = [
            new StringRule(),
            new IntRule(),
            new FloatRule(),
            new BoolRule(),
            new NumericStringRule(),
        ];

        foreach ($typeRules as $rule) {
            $condition = $rule->testCondition('val');
            self::assertStringContainsString('is_null', $condition, sprintf(
                '%s should check for null to allow nullable values',
                $rule->name(),
            ));
        }
    }

    #[Test]
    public function errorMessagesIncludeParamName(): void
    {
        $rules = [
            new StringRule(),
            new IntRule(),
            new FloatRule(),
            new BoolRule(),
            new NumericStringRule(),
            new PatternRule('[A-Z]+'),
            new EnumerationRule('\App\Enum\Test'),
            new ItemTypeRule('\App\Entity\Foo'),
            new MinLengthRule(1),
            new MaxLengthRule(10),
            new LengthRule(5),
            new MinInclusiveRule('0'),
            new MaxInclusiveRule('100'),
            new MinExclusiveRule('0'),
            new MaxExclusiveRule('100'),
            new TotalDigitsRule(5),
            new FractionDigitsRule(2),
            new MaxOccursRule(3),
        ];

        foreach ($rules as $rule) {
            $msg = $rule->errorMessage('myParam');
            self::assertStringContainsString('myParam', $msg, sprintf(
                '%s error message should include param name',
                $rule->name(),
            ));
        }
    }
}
