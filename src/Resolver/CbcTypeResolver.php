<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItem;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeSingle;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use GoetasWebservices\XML\XSDReader\Schema\Type\BaseComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexTypeSimpleContent;
use GoetasWebservices\XML\XSDReader\Schema\Type\SimpleType;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use Symfony\Component\Yaml\Yaml;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;

final class CbcTypeResolver
{
    /** @var array<string, ResolvedLeafType> */
    private array $leafTypesByClassName = [];

    /** @var array<string, string> */
    private array $elementToClassName = [];

    /** @var array<string, string> */
    private array $xsdTypeMap;

    /** @var array<string, string> */
    private array $primitiveTypeMap;

    private bool $resolved = false;

    public function __construct(
        private readonly UblTypeRegistry $registry,
        private readonly string $xsdTypesConfigPath,
    ) {
        $config = $this->loadConfig();
        $this->xsdTypeMap = PhpTypeNormalizer::normalizeMap($config['types']);
        $this->primitiveTypeMap = PhpTypeNormalizer::normalizeMap($config['primitive_types']);
    }

    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        $leafElements = [];
        foreach (UblTypeRegistry::COMPONENT_NAMESPACES as $ns) {
            $leafElements = array_merge($leafElements, $this->registry->globalElementsInNamespace($ns));
        }

        /** @var array<string, list<string>> */
        $elementsByBaseType = [];
        /** @var array<string, list<ResolvedAttribute>> */
        $attributesByBaseType = [];
        /** @var array<string, string> */
        $valueTypeByBaseType = [];

        foreach ($leafElements as $element) {
            $type = $element->getType();

            // Only process simpleContent complex types (leaf types: value + attributes)
            if (!$type instanceof ComplexTypeSimpleContent) {
                continue;
            }

            $attributes = $this->collectAttributes($type);
            $baseTypeName = $this->resolveBaseTypeName($type);
            $valuePhpType = $this->resolveValuePhpType($type, $baseTypeName);

            $elementsByBaseType[$baseTypeName][] = $element->getName();

            if (!isset($attributesByBaseType[$baseTypeName])) {
                $attributesByBaseType[$baseTypeName] = $attributes;
            }

            if (!isset($valueTypeByBaseType[$baseTypeName])) {
                $valueTypeByBaseType[$baseTypeName] = $valuePhpType;
            }
        }

        foreach ($elementsByBaseType as $baseTypeName => $elementNames) {
            if (isset($this->primitiveTypeMap[$baseTypeName])) {
                foreach ($elementNames as $elementName) {
                    $this->elementToClassName[$elementName] = $this->primitiveTypeMap[$baseTypeName];
                }

                continue;
            }

            $className = $this->deriveClassName($baseTypeName);
            $leafType = new ResolvedLeafType(
                className: $className,
                valuePhpType: $valueTypeByBaseType[$baseTypeName],
                attributes: $attributesByBaseType[$baseTypeName],
                cbcElementNames: $elementNames,
            );

            $this->leafTypesByClassName[$className] = $leafType;

            foreach ($elementNames as $elementName) {
                $this->elementToClassName[$elementName] = $className;
            }
        }
    }

    /** @return array<string, ResolvedLeafType> */
    public function leafTypes(): array
    {
        $this->resolve();

        return $this->leafTypesByClassName;
    }

    public function leafTypeForElement(string $cbcElementName): ?ResolvedLeafType
    {
        $this->resolve();

        $className = $this->elementToClassName[$cbcElementName] ?? null;

        if ($className === null || isset($this->primitiveTypeMap[$this->resolveBaseTypeNameForElement($cbcElementName)])) {
            return null;
        }

        return $this->leafTypesByClassName[$className] ?? null;
    }

    public function phpTypeForElement(string $cbcElementName): ?string
    {
        $this->resolve();

        return $this->elementToClassName[$cbcElementName] ?? null;
    }

    public function isPrimitiveMapping(string $cbcElementName): bool
    {
        $this->resolve();

        $className = $this->elementToClassName[$cbcElementName] ?? null;

        if ($className === null) {
            return false;
        }

        return in_array($className, $this->primitiveTypeMap, true);
    }

    /** @return list<ResolvedAttribute> */
    private function collectAttributes(Type $type): array
    {
        $attributes = [];
        $seen = [];
        $current = $type;

        while ($current instanceof BaseComplexType) {
            foreach ($current->getAttributes() as $attr) {
                foreach ($this->flattenAttributeItem($attr) as $singleAttr) {
                    $xmlName = $singleAttr->getName();

                    if (isset($seen[$xmlName])) {
                        continue;
                    }

                    $seen[$xmlName] = true;
                    $required = $singleAttr->getUse() === AttributeSingle::USE_REQUIRED;

                    $attributes[] = new ResolvedAttribute(
                        xmlName: $xmlName,
                        phpName: $this->attributeXmlNameToPhpName($xmlName),
                        phpType: '?string',
                        required: $required,
                    );
                }
            }

            $current = $this->walkParent($current);
        }

        return $attributes;
    }

    /** @return list<AttributeSingle> */
    private function flattenAttributeItem(AttributeItem $item): array
    {
        if ($item instanceof AttributeSingle) {
            return [$item];
        }

        if ($item instanceof AttributeGroup) {
            $result = [];

            foreach ($item->getAttributes() as $child) {
                foreach ($this->flattenAttributeItem($child) as $single) {
                    $result[] = $single;
                }
            }

            return $result;
        }

        return [];
    }

    private function resolveBaseTypeName(Type $type): string
    {
        $current = $type;

        while (true) {
            $next = $this->walkParent($current);

            if ($next === null) {
                break;
            }

            if ($next instanceof SimpleType) {
                $name = $current->getName();

                if ($name !== null) {
                    return $name;
                }

                break;
            }

            $current = $next;
        }

        return $current->getName() ?? 'string';
    }

    private function resolveValuePhpType(Type $type, string $baseTypeName): string
    {
        if (isset($this->primitiveTypeMap[$baseTypeName])) {
            return $this->primitiveTypeMap[$baseTypeName];
        }

        $current = $type;

        while (true) {
            $next = $this->walkParent($current);

            if ($next instanceof SimpleType) {
                return $this->resolveSimpleTypeToPhp($next);
            }

            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return 'string';
    }

    private function resolveSimpleTypeToPhp(SimpleType $type): string
    {
        $current = $type;

        while (true) {
            $name = $current->getName();

            if ($name !== null) {
                $xsdKey = 'xsd:' . $name;

                if (isset($this->xsdTypeMap[$xsdKey])) {
                    return $this->xsdTypeMap[$xsdKey];
                }
            }

            $restriction = $current->getRestriction();

            if ($restriction === null) {
                break;
            }

            $base = $restriction->getBase();

            if (!$base instanceof SimpleType) {
                break;
            }

            $current = $base;
        }

        return 'string';
    }

    private function walkParent(Type $type): ?Type
    {
        $extension = $type->getExtension();

        if ($extension !== null) {
            return $extension->getBase();
        }

        $restriction = $type->getRestriction();

        if ($restriction !== null) {
            return $restriction->getBase();
        }

        return null;
    }

    private function deriveClassName(string $baseTypeName): string
    {
        if (str_ends_with($baseTypeName, 'Type')) {
            return substr($baseTypeName, 0, -4);
        }

        return $baseTypeName;
    }

    private function attributeXmlNameToPhpName(string $xmlName): string
    {
        $result = '';
        $nextUpper = false;

        for ($i = 0; $i < strlen($xmlName); $i++) {
            $char = $xmlName[$i];

            if ($char === '_' || $char === '-') {
                $nextUpper = true;

                continue;
            }

            if ($i === 0) {
                $result .= strtolower($char);
            } elseif ($nextUpper) {
                $result .= strtoupper($char);
                $nextUpper = false;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    private function resolveBaseTypeNameForElement(string $cbcElementName): string
    {
        $allElements = [];
        foreach (UblTypeRegistry::COMPONENT_NAMESPACES as $ns) {
            $allElements = array_merge($allElements, $this->registry->globalElementsInNamespace($ns));
        }

        foreach ($allElements as $element) {
            if ($element->getName() === $cbcElementName) {
                $type = $element->getType();

                if ($type !== null) {
                    return $this->resolveBaseTypeName($type);
                }
            }
        }

        return '';
    }

    /** @return array{types: array<string, string>, primitive_types: array<string, string>} */
    private function loadConfig(): array
    {
        /** @var array{types: array<string, string>, primitive_types: array<string, string>} $config */
        $config = Yaml::parseFile($this->xsdTypesConfigPath);

        return $config;
    }
}
