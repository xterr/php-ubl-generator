<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Xsd;

use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;
use Xterr\UBL\Generator\Exception\SchemaParseException;

final class SchemaLoader
{
    private SchemaReader $reader;

    public function __construct()
    {
        $this->reader = new SchemaReader();
    }

    /**
     * @return list<Schema>
     */
    public function loadAll(string $xsdDir): array
    {
        $maindocDir = rtrim($xsdDir, '/') . '/maindoc';

        if (!is_dir($maindocDir)) {
            throw new SchemaParseException("Maindoc directory not found: {$maindocDir}");
        }

        $files = glob($maindocDir . '/*.xsd');

        if ($files === false || $files === []) {
            throw new SchemaParseException("No XSD files found in: {$maindocDir}");
        }

        $schemas = [];

        foreach ($files as $file) {
            try {
                $schemas[] = $this->reader->readFile($file);
            } catch (\Exception $e) {
                throw new SchemaParseException(
                    "Failed to parse schema {$file}: {$e->getMessage()}",
                    previous: $e,
                );
            }
        }

        return $schemas;
    }

    public function loadFile(string $xsdPath, ?string $allowedBaseDir = null): Schema
    {
        $resolved = realpath($xsdPath);

        if ($resolved === false) {
            throw new SchemaParseException("Schema file not found: {$xsdPath}");
        }

        if ($allowedBaseDir !== null) {
            $resolvedBase = realpath($allowedBaseDir);
            if ($resolvedBase === false || !str_starts_with($resolved, $resolvedBase)) {
                throw new SchemaParseException(
                    "Schema path '{$resolved}' is outside the allowed directory '{$allowedBaseDir}'."
                );
            }
        }

        try {
            return $this->reader->readFile($resolved);
        } catch (\Exception $e) {
            throw new SchemaParseException(
                "Failed to parse schema {$resolved}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
