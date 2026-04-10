<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Codelist;

use Xterr\UBL\Generator\Exception\GeneratorException;

final class GenericodeParser
{
    private const GC_NS = 'http://docs.oasis-open.org/codelist/ns/genericode/1.0/';

    /**
     * Parse a single .gc file.
     *
     * @throws GeneratorException on parse failure
     */
    public function parse(string $filePath): ParsedCodelist
    {
        if (!file_exists($filePath)) {
            throw new GeneratorException(sprintf('Genericode file not found: %s', $filePath));
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$dom->load($filePath)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $msg = $errors !== [] ? $errors[0]->message : 'unknown error';
                throw new GeneratorException(sprintf('Failed to parse Genericode file %s: %s', $filePath, trim($msg)));
            }
        } finally {
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('gc', self::GC_NS);

        // Detect whether child elements use the gc namespace or are unprefixed.
        // Real ESPD .gc files use unprefixed children under <gc:CodeList>.
        $usePrefix = $xpath->query('/gc:CodeList/gc:Identification')->length > 0;
        $p = $usePrefix ? 'gc:' : '';

        $identification = $xpath->query("/gc:CodeList/{$p}Identification")->item(0);
        if ($identification === null) {
            throw new GeneratorException(sprintf('Missing <Identification> element in: %s', $filePath));
        }

        $shortName = $this->nodeText($xpath, $identification, "{$p}ShortName");
        if ($shortName === null) {
            throw new GeneratorException(sprintf('Missing <ShortName> in <Identification> in: %s', $filePath));
        }

        $listID = $this->nodeText($xpath, $identification, "{$p}LongName[@Identifier='listId']");
        if ($listID === null) {
            throw new GeneratorException(sprintf('Missing <LongName Identifier="listId"> in: %s', $filePath));
        }

        $version = $this->nodeText($xpath, $identification, "{$p}Version");
        $locationUri = $this->nodeText($xpath, $identification, "{$p}LocationUri");

        // Agency info
        $agencyNode = $xpath->query("{$p}Agency", $identification)->item(0);
        $agencyID = null;
        if ($agencyNode !== null) {
            // Try Identifier element's Identifier attribute first (ESPD style: <Identifier Identifier="OP"/>)
            $identifierNode = $xpath->query("{$p}Identifier", $agencyNode)->item(0);
            if ($identifierNode instanceof \DOMElement) {
                $agencyID = $identifierNode->getAttribute('Identifier') ?: $identifierNode->textContent ?: null;
            }
            // Fall back to Agency/ShortName
            if ($agencyID === null || $agencyID === '') {
                $agencyID = $this->nodeText($xpath, $agencyNode, "{$p}ShortName");
            }
        }

        // Determine the name column ID. Most files use "Name", some use "eng_label" or similar.
        $nameColumnId = $this->detectNameColumnId($xpath, $p);

        // Parse rows
        $rows = $xpath->query("/gc:CodeList/{$p}SimpleCodeList/{$p}Row");
        $entries = [];

        if ($rows !== false) {
            foreach ($rows as $row) {
                $code = $this->rowValue($xpath, $row, $p, 'code');
                if ($code === null) {
                    continue;
                }

                $status = $this->rowValue($xpath, $row, $p, 'status') ?? 'ACTIVE';
                $name = $nameColumnId !== null ? $this->rowValue($xpath, $row, $p, $nameColumnId) : null;

                $entries[] = new CodelistEntry(
                    code: $code,
                    name: $name,
                    status: $status,
                );
            }
        }

        return new ParsedCodelist(
            shortName: $shortName,
            listID: $listID,
            version: $version,
            agencyID: $agencyID,
            locationUri: $locationUri,
            entries: $entries,
        );
    }

    /**
     * Parse all .gc files in a directory.
     *
     * @return array<string, ParsedCodelist> keyed by listID
     * @throws GeneratorException
     */
    public function parseDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new GeneratorException(sprintf('Codelist directory not found: %s', $dir));
        }

        $files = glob(rtrim($dir, '/') . '/*.gc');
        if ($files === false || $files === []) {
            return [];
        }

        $codelists = [];
        foreach ($files as $file) {
            $codelist = $this->parse($file);
            $codelists[$codelist->listID] = $codelist;
        }

        return $codelists;
    }

    private function nodeText(\DOMXPath $xpath, \DOMNode $context, string $expression): ?string
    {
        $node = $xpath->query($expression, $context)->item(0);
        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }

    private function rowValue(\DOMXPath $xpath, \DOMNode $row, string $prefix, string $columnRef): ?string
    {
        $node = $xpath->query("{$prefix}Value[@ColumnRef='{$columnRef}']/{$prefix}SimpleValue", $row)->item(0);
        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }

    /**
     * Detect the column ID used for English names/descriptions.
     * Most ESPD files use "Name", some use "eng_label" or other IDs.
     */
    private function detectNameColumnId(\DOMXPath $xpath, string $prefix): ?string
    {
        // Prefer "Name" column (most common)
        $nameCol = $xpath->query("/gc:CodeList/{$prefix}ColumnSet/{$prefix}Column[@Id='Name']");
        if ($nameCol !== false && $nameCol->length > 0) {
            return 'Name';
        }

        // Fall back to first column with Lang="eng" in its Data element
        $engCols = $xpath->query("/gc:CodeList/{$prefix}ColumnSet/{$prefix}Column[{$prefix}Data/@Lang='eng']");
        if ($engCols !== false && $engCols->length > 0) {
            $col = $engCols->item(0);
            if ($col instanceof \DOMElement) {
                $id = $col->getAttribute('Id');
                if ($id !== '' && $id !== 'code' && $id !== 'status') {
                    return $id;
                }
            }
        }

        return null;
    }
}
