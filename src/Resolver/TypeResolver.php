<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

use GoetasWebservices\XML\XSDReader\Schema\Element\AbstractElementSingle;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementItem;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementRef;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexTypeSimpleContent;
use GoetasWebservices\XML\XSDReader\Schema\Type\SimpleType;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use Symfony\Component\Yaml\Yaml;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class TypeResolver
{
    /** @var array<string, string> */
    private array $xsdTypeMap;

    public function __construct(
        private readonly CbcTypeResolver $cbcTypeResolver,
        private readonly string $xsdTypesConfigPath,
    ) {
        $this->xsdTypeMap = $this->loadXsdTypeMap();
    }

    public function resolveElement(ElementItem $element, ?string $choiceGroup = null): ResolvedType
    {
        $elementSingle = $this->unwrapElement($element);

        if ($elementSingle === null) {
            return $this->fallbackType($element->getName(), '', $choiceGroup);
        }

        $xmlName = $element->getName();
        $xmlNamespace = $elementSingle->getSchema()->getTargetNamespace() ?? '';
        $min = $elementSingle->getMin();
        $max = $elementSingle->getMax();
        $isNullable = $min === 0;
        $isArray = $max > 1 || $max === -1;

        if ($xmlNamespace === XmlNamespace::CBC) {
            return $this->resolveCbcElement($xmlName, $xmlNamespace, $isNullable, $isArray, $choiceGroup);
        }

        $type = $elementSingle->getType();

        if ($type === null) {
            return $this->fallbackType($xmlName, $xmlNamespace, $choiceGroup, $isNullable, $isArray);
        }

        if ($type instanceof SimpleType) {
            return $this->resolveSimpleType($type, $xmlName, $xmlNamespace, $isNullable, $isArray, $choiceGroup);
        }

        if ($type instanceof ComplexTypeSimpleContent) {
            return $this->resolveComplexSimpleContent($type, $xmlName, $xmlNamespace, $isNullable, $isArray, $choiceGroup);
        }

        if ($type instanceof ComplexType) {
            return $this->resolveComplexType($type, $xmlName, $xmlNamespace, $isNullable, $isArray, $choiceGroup);
        }

        return $this->fallbackType($xmlName, $xmlNamespace, $choiceGroup, $isNullable, $isArray);
    }

    public function resolveXsdPrimitive(string $xsdTypeName): string
    {
        $key = str_starts_with($xsdTypeName, 'xsd:') ? $xsdTypeName : 'xsd:' . $xsdTypeName;

        return $this->xsdTypeMap[$key] ?? 'string';
    }

    private function resolveCbcElement(
        string $xmlName,
        string $xmlNamespace,
        bool $isNullable,
        bool $isArray,
        ?string $choiceGroup,
    ): ResolvedType {
        $phpType = $this->cbcTypeResolver->phpTypeForElement($xmlName);
        $isPrimitive = $this->cbcTypeResolver->isPrimitiveMapping($xmlName);

        if ($phpType === null) {
            return $this->fallbackType($xmlName, $xmlNamespace, $choiceGroup, $isNullable, $isArray);
        }

        if ($isPrimitive) {
            $fqcn = match ($phpType) {
                '\DateTimeImmutable' => '\DateTimeImmutable',
                default => $phpType,
            };

            return new ResolvedType(
                phpType: $fqcn,
                isPrimitive: true,
                isLeafType: false,
                isArray: $isArray,
                isNullable: $isNullable,
                xmlElementName: $xmlName,
                xmlNamespace: $xmlNamespace,
                choiceGroup: $choiceGroup,
            );
        }

        return new ResolvedType(
            phpType: $phpType,
            isPrimitive: false,
            isLeafType: true,
            isArray: $isArray,
            isNullable: $isNullable,
            xmlElementName: $xmlName,
            xmlNamespace: $xmlNamespace,
            choiceGroup: $choiceGroup,
        );
    }

    private function resolveSimpleType(
        SimpleType $type,
        string $xmlName,
        string $xmlNamespace,
        bool $isNullable,
        bool $isArray,
        ?string $choiceGroup,
    ): ResolvedType {
        $phpType = $this->walkSimpleTypeToPhp($type);

        return new ResolvedType(
            phpType: $phpType,
            isPrimitive: true,
            isLeafType: false,
            isArray: $isArray,
            isNullable: $isNullable,
            xmlElementName: $xmlName,
            xmlNamespace: $xmlNamespace,
            choiceGroup: $choiceGroup,
        );
    }

    private function resolveComplexSimpleContent(
        ComplexTypeSimpleContent $type,
        string $xmlName,
        string $xmlNamespace,
        bool $isNullable,
        bool $isArray,
        ?string $choiceGroup,
    ): ResolvedType {
        $typeName = $type->getName();

        if ($typeName !== null) {
            $className = $this->stripTypeSuffix($typeName);

            return new ResolvedType(
                phpType: $className,
                isPrimitive: false,
                isLeafType: true,
                isArray: $isArray,
                isNullable: $isNullable,
                xmlElementName: $xmlName,
                xmlNamespace: $xmlNamespace,
                choiceGroup: $choiceGroup,
            );
        }

        return $this->fallbackType($xmlName, $xmlNamespace, $choiceGroup, $isNullable, $isArray);
    }

    private function resolveComplexType(
        ComplexType $type,
        string $xmlName,
        string $xmlNamespace,
        bool $isNullable,
        bool $isArray,
        ?string $choiceGroup,
    ): ResolvedType {
        $typeName = $type->getName();

        if ($typeName !== null) {
            $className = $this->stripTypeSuffix($typeName);

            return new ResolvedType(
                phpType: $className,
                isPrimitive: false,
                isLeafType: false,
                isArray: $isArray,
                isNullable: $isNullable,
                xmlElementName: $xmlName,
                xmlNamespace: $xmlNamespace,
                choiceGroup: $choiceGroup,
            );
        }

        return $this->fallbackType($xmlName, $xmlNamespace, $choiceGroup, $isNullable, $isArray);
    }

    private function walkSimpleTypeToPhp(SimpleType $type): string
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

    private function unwrapElement(ElementItem $element): ?AbstractElementSingle
    {
        if ($element instanceof ElementRef) {
            return $element;
        }

        if ($element instanceof AbstractElementSingle) {
            return $element;
        }

        return null;
    }

    private function stripTypeSuffix(string $typeName): string
    {
        if (str_ends_with($typeName, 'Type')) {
            return substr($typeName, 0, -4);
        }

        return $typeName;
    }

    private function fallbackType(
        string $xmlName,
        string $xmlNamespace,
        ?string $choiceGroup,
        bool $isNullable = true,
        bool $isArray = false,
    ): ResolvedType {
        return new ResolvedType(
            phpType: 'string',
            isPrimitive: true,
            isLeafType: false,
            isArray: $isArray,
            isNullable: $isNullable,
            xmlElementName: $xmlName,
            xmlNamespace: $xmlNamespace,
            choiceGroup: $choiceGroup,
        );
    }

    /** @return array<string, string> */
    private function loadXsdTypeMap(): array
    {
        /** @var array{types: array<string, string>} $config */
        $config = Yaml::parseFile($this->xsdTypesConfigPath);

        return PhpTypeNormalizer::normalizeMap($config['types']);
    }
}
