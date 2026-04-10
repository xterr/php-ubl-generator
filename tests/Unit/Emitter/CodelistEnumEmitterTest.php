<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Emitter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Generator\Codelist\CodelistEntry;
use Xterr\UBL\Generator\Codelist\ParsedCodelist;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Emitter\CodelistEnumEmitter;

final class CodelistEnumEmitterTest extends TestCase
{
    private CodelistEnumEmitter $emitter;

    protected function setUp(): void
    {
        $config = GeneratorConfig::defaults();
        $this->emitter = new CodelistEnumEmitter($config);
    }

    #[Test]
    public function emitProducesValidStringBackedEnum(): void
    {
        $codelist = new ParsedCodelist(
            shortName: 'CriterionElementType',
            listID: 'criterion-element-type',
            version: '4.1.0',
            agencyID: 'OP',
            locationUri: 'https://example.com/CriterionElementType.gc',
            entries: [
                new CodelistEntry(code: 'QUESTION', name: 'A question', status: 'ACTIVE'),
                new CodelistEntry(code: 'REQUIREMENT', name: 'A requirement', status: 'ACTIVE'),
                new CodelistEntry(code: 'CAPTION', name: null, status: 'ACTIVE'),
            ],
        );

        $output = $this->emitter->emit('CriterionElementType', $codelist);

        self::assertStringContainsString('declare(strict_types=1)', $output);
        self::assertStringContainsString('enum CriterionElementType: string', $output);
        self::assertStringContainsString("case QUESTION = 'QUESTION'", $output);
        self::assertStringContainsString("case REQUIREMENT = 'REQUIREMENT'", $output);
        self::assertStringContainsString("case CAPTION = 'CAPTION'", $output);
    }

    #[Test]
    public function emitIncludesCodelistMetaAttribute(): void
    {
        $codelist = new ParsedCodelist(
            shortName: 'CriterionElementType',
            listID: 'criterion-element-type',
            version: '4.1.0',
            agencyID: 'OP',
            locationUri: null,
            entries: [
                new CodelistEntry(code: 'QUESTION', name: null, status: 'ACTIVE'),
            ],
        );

        $output = $this->emitter->emit('CriterionElementType', $codelist);

        self::assertStringContainsString('#[CodelistMeta(', $output);
        self::assertStringContainsString("listID: 'criterion-element-type'", $output);
        self::assertStringContainsString("listAgencyID: 'OP'", $output);
        self::assertStringContainsString("listVersionID: '4.1.0'", $output);
    }

    #[Test]
    public function emitFiltersOutNonActiveEntries(): void
    {
        $codelist = new ParsedCodelist(
            shortName: 'TestList',
            listID: 'test',
            version: '1.0',
            agencyID: null,
            locationUri: null,
            entries: [
                new CodelistEntry(code: 'ACTIVE_CODE', name: null, status: 'ACTIVE'),
                new CodelistEntry(code: 'DEPRECATED_CODE', name: null, status: 'DEPRECATED'),
                new CodelistEntry(code: 'INACTIVE_CODE', name: null, status: 'INACTIVE'),
            ],
        );

        $output = $this->emitter->emit('TestList', $codelist);

        self::assertStringContainsString("case ACTIVE_CODE = 'ACTIVE_CODE'", $output);
        self::assertStringNotContainsString('DEPRECATED_CODE', $output);
        self::assertStringNotContainsString('INACTIVE_CODE', $output);
    }

    #[Test]
    public function emitNormalizesSpecialCharactersInCaseNames(): void
    {
        $codelist = new ParsedCodelist(
            shortName: 'Test',
            listID: 'test',
            version: null,
            agencyID: null,
            locationUri: null,
            entries: [
                new CodelistEntry(code: 'crime-org', name: null, status: 'ACTIVE'),
                new CodelistEntry(code: '3rd-party', name: null, status: 'ACTIVE'),
            ],
        );

        $output = $this->emitter->emit('Test', $codelist);

        self::assertStringContainsString("case CRIME_ORG = 'crime-org'", $output);
        self::assertStringContainsString("case VALUE_3RD_PARTY = '3rd-party'", $output);
    }

    #[Test]
    public function emitUsesCodelistNamespace(): void
    {
        $config = GeneratorConfig::defaults();
        $output = $this->emitter->emit('TestEnum', new ParsedCodelist(
            shortName: 'TestEnum',
            listID: 'test',
            version: null,
            agencyID: null,
            locationUri: null,
            entries: [new CodelistEntry(code: 'A', name: null, status: 'ACTIVE')],
        ));

        $expectedNs = $config->namespace . '\\' . $config->codelistNamespace;
        self::assertStringContainsString('namespace ' . $expectedNs . ';', $output);
    }

    #[Test]
    public function emitIncludesGeneratedTag(): void
    {
        $output = $this->emitter->emit('TestEnum', new ParsedCodelist(
            shortName: 'TestEnum',
            listID: 'test',
            version: null,
            agencyID: null,
            locationUri: null,
            entries: [new CodelistEntry(code: 'A', name: null, status: 'ACTIVE')],
        ));

        self::assertStringContainsString('@generated by php-ubl code generator', $output);
    }

    #[Test]
    public function emitOmitsOptionalMetaFieldsWhenNull(): void
    {
        $codelist = new ParsedCodelist(
            shortName: 'Minimal',
            listID: 'minimal-list',
            version: null,
            agencyID: null,
            locationUri: null,
            entries: [new CodelistEntry(code: 'X', name: null, status: 'ACTIVE')],
        );

        $output = $this->emitter->emit('Minimal', $codelist);

        self::assertStringContainsString("listID: 'minimal-list'", $output);
        self::assertStringNotContainsString('listAgencyID', $output);
        self::assertStringNotContainsString('listVersionID', $output);
    }
}
