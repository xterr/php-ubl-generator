<?php

declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

use Symfony\Component\Yaml\Yaml;
use Xterr\UBL\Generator\Config\GeneratorConfig;

final class NamingResolver
{
    /** @var list<string> */
    private array $reservedKeywords;

    /** @var list<string> */
    private array $reservedConstants;

    /** @var array<string, string> */
    private array $pluralOverrides;

    public function __construct(
        private readonly GeneratorConfig $config,
    ) {
        $this->loadReservedKeywords();
        $this->loadPluralOverrides();
    }

    public function toClassName(string $xsdTypeName): string
    {
        if (isset($this->config->classNameOverrides[$xsdTypeName])) {
            return $this->config->classNameOverrides[$xsdTypeName];
        }

        $name = $xsdTypeName;
        if (str_ends_with($name, 'Type') && strlen($name) > 4) {
            $name = substr($name, 0, -4);
        }

        return $this->escapeReservedWord($name);
    }

    public function toPropertyName(string $xmlName, bool $isArray = false): string
    {
        if (isset($this->config->propertyNameOverrides[$xmlName])) {
            return $this->config->propertyNameOverrides[$xmlName];
        }

        $name = $this->toCamelCase($xmlName);

        if ($isArray) {
            $name = $this->pluralize($xmlName, $name);
        }

        return $this->escapeReservedWord($name);
    }

    /**
     * Convert XSD name to camelCase following these rules:
     * 1. Short all-caps (≤4 chars): lowercase entirely (ID→id, UUID→uuid, URI→uri)
     * 2. Leading acronym run: lowercase up to last uppercase before lowercase
     *    (UBLVersionID→ublVersionID, URICode→uriCode)
     * 3. Standard PascalCase: lcfirst (StreetName→streetName)
     */
    private function toCamelCase(string $name): string
    {
        // Rule 1: Short all-caps (≤4 chars)
        if (mb_strtoupper($name) === $name && mb_strlen($name) <= 4) {
            return mb_strtolower($name);
        }

        // Rule 2: Leading acronym run
        $firstLower = 0;
        $len = strlen($name);
        while ($firstLower < $len && ctype_upper($name[$firstLower])) {
            $firstLower++;
        }

        if ($firstLower > 1 && $firstLower < $len) {
            // Lowercase the acronym run up to (but not including) the transition char
            return strtolower(substr($name, 0, $firstLower - 1)) . substr($name, $firstLower - 1);
        }

        // Rule 3: Standard PascalCase → camelCase
        return lcfirst($name);
    }

    private function pluralize(string $xmlName, string $camelName): string
    {
        if (isset($this->pluralOverrides[$xmlName])) {
            return lcfirst($this->pluralOverrides[$xmlName]);
        }

        // Words ending in 'y' preceded by a consonant → 'ies'
        if (preg_match('/[^aeiou]y$/i', $xmlName) === 1) {
            return substr($camelName, 0, -1) . 'ies';
        }

        // Words ending in 's', 'sh', 'ch', 'x', 'z' → add 'es'
        if (preg_match('/(s|sh|ch|x|z)$/i', $camelName) === 1) {
            return $camelName . 'es';
        }

        return $camelName . 's';
    }

    private function escapeReservedWord(string $name): string
    {
        // Case-insensitive keyword check
        if (in_array(strtolower($name), $this->reservedKeywords, true)) {
            return '_' . $name;
        }

        // Case-sensitive constant check
        if (in_array($name, $this->reservedConstants, true)) {
            return '_' . $name;
        }

        return $name;
    }

    private function loadReservedKeywords(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/config/php_reserved_keywords.yaml';
        if (!file_exists($path)) {
            $this->reservedKeywords = [];
            $this->reservedConstants = [];

            return;
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            $this->reservedKeywords = [];
            $this->reservedConstants = [];

            return;
        }

        /** @var list<string|int|bool|null> $rawKeywords */
        $rawKeywords = $data['keywords'] ?? [];
        /** @var list<string|int|bool|null> $rawConstants */
        $rawConstants = $data['constants'] ?? [];

        $this->reservedKeywords = array_map(static fn(string|int|bool|null $v): string => strtolower((string) $v), $rawKeywords);
        $this->reservedConstants = array_map(static fn(string|int|bool|null $v): string => (string) $v, $rawConstants);
    }

    private function loadPluralOverrides(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/config/naming.yaml';
        if (!file_exists($path)) {
            $this->pluralOverrides = [];
            return;
        }

        $data = Yaml::parseFile($path);
        $this->pluralOverrides = is_array($data) ? ($data['plural_overrides'] ?? []) : [];
    }
}
