<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Xsd;

use GoetasWebservices\XML\XSDReader\Schema\Element\ElementDef;
use GoetasWebservices\XML\XSDReader\Schema\Inheritance\RestrictionType;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\Schema\Type\BaseComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\SimpleType;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class UblTypeRegistry
{
    /**
     * All UBL component namespaces whose types and elements should be processed.
     * Classification (leaf vs aggregate) is structural, not namespace-based:
     * - ComplexTypeSimpleContent → leaf class (Cbc/)
     * - ComplexType with child elements → aggregate class (Cac/)
     */
    public const COMPONENT_NAMESPACES = [
        XmlNamespace::CBC,
        XmlNamespace::SBC,
        XmlNamespace::EXT,
        XmlNamespace::CAC,
        XmlNamespace::SAC,
        XmlNamespace::SIG,
    ];

    /** @var array<string, list<BaseComplexType>> */
    private array $complexTypes = [];

    /** @var array<string, list<SimpleType>> */
    private array $simpleTypes = [];

    /** @var array<string, list<ElementDef>> */
    private array $globalElements = [];

    /** @var array<int, true> */
    private array $processedIds = [];

    /** @param list<Schema> $schemas */
    public function populate(array $schemas): void
    {
        foreach ($schemas as $schema) {
            $this->walkSchema($schema);
        }
    }

    private function walkSchema(Schema $schema): void
    {
        $id = spl_object_id($schema);

        if (isset($this->processedIds[$id])) {
            return;
        }

        $this->processedIds[$id] = true;

        $ns = $schema->getTargetNamespace() ?? '';

        foreach ($schema->getTypes() as $type) {
            if ($type instanceof BaseComplexType) {
                $this->complexTypes[$ns][] = $type;
            } elseif ($type instanceof SimpleType) {
                $this->simpleTypes[$ns][] = $type;
            }
        }

        foreach ($schema->getElements() as $element) {
            $this->globalElements[$ns][] = $element;
        }

        foreach ($schema->getSchemas() as $importedSchema) {
            $this->walkSchema($importedSchema);
        }
    }

    /** @return list<BaseComplexType> */
    public function complexTypesInNamespace(string $namespace): array
    {
        return $this->complexTypes[$namespace] ?? [];
    }

    /** @return list<SimpleType> */
    public function simpleTypesInNamespace(string $namespace): array
    {
        return $this->simpleTypes[$namespace] ?? [];
    }

    /** @return list<SimpleType> */
    public function simpleTypesWithEnumerations(): array
    {
        $result = [];

        foreach ($this->simpleTypes as $types) {
            foreach ($types as $type) {
                $restriction = $type->getRestriction();

                if ($restriction === null) {
                    continue;
                }

                if ($restriction->getChecksByType(RestrictionType::ENUMERATION) !== []) {
                    $result[] = $type;
                }
            }
        }

        return $result;
    }

    /** @return list<ElementDef> */
    public function globalElementsInNamespace(string $namespace): array
    {
        return $this->globalElements[$namespace] ?? [];
    }

    /** @return list<ElementDef> */
    public function documentRootElements(): array
    {
        $commonNamespaces = [
            XmlNamespace::CBC,
            XmlNamespace::CAC,
            XmlNamespace::EXT,
            XmlNamespace::SIG,
            XmlNamespace::SAC,
            XmlNamespace::SBC,
            XmlNamespace::CCTS,
            XmlNamespace::UDT,
            XmlNamespace::QDT,
            XmlNamespace::DS,
            'http://www.w3.org/2001/XMLSchema',
            'http://www.w3.org/XML/1998/namespace',
            'http://www.w3.org/2009/xmldsig11#',
            'http://uri.etsi.org/01903/v1.3.2#',
            'http://uri.etsi.org/01903/v1.4.1#',
        ];

        $roots = [];

        foreach ($this->globalElements as $ns => $elements) {
            if (in_array($ns, $commonNamespaces, true)) {
                continue;
            }

            foreach ($elements as $element) {
                $roots[] = $element;
            }
        }

        return $roots;
    }

    /** @return list<string> */
    public function allNamespaces(): array
    {
        return array_values(array_unique([
            ...array_keys($this->complexTypes),
            ...array_keys($this->simpleTypes),
            ...array_keys($this->globalElements),
        ]));
    }

    /** @return array{complexTypes: int, simpleTypes: int, globalElements: int, namespaces: int} */
    public function stats(): array
    {
        $ct = 0;

        foreach ($this->complexTypes as $types) {
            $ct += count($types);
        }

        $st = 0;

        foreach ($this->simpleTypes as $types) {
            $st += count($types);
        }

        $ge = 0;

        foreach ($this->globalElements as $elements) {
            $ge += count($elements);
        }

        return [
            'complexTypes' => $ct,
            'simpleTypes' => $st,
            'globalElements' => $ge,
            'namespaces' => count($this->allNamespaces()),
        ];
    }
}
