<?php declare(strict_types=1);

namespace Xterr\UBL\Generator;

use GoetasWebservices\XML\XSDReader\Schema\Inheritance\RestrictionType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use Xterr\UBL\Generator\Codelist\GenericodeParser;
use Xterr\UBL\Generator\Codelist\ParsedCodelist;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Emitter\ClassEmitter;
use Xterr\UBL\Generator\Emitter\CodelistEnumEmitter;
use Xterr\UBL\Generator\Emitter\EnumEmitter;
use Xterr\UBL\Generator\Emitter\FileWriter;
use Xterr\UBL\Generator\Emitter\RegistryEmitter;
use Xterr\UBL\Generator\Emitter\ResolvedProperty;
use Xterr\UBL\Generator\Resolver\CbcTypeResolver;
use Xterr\UBL\Generator\Resolver\NamingResolver;
use Xterr\UBL\Generator\Resolver\ResolvedType;
use Xterr\UBL\Generator\Resolver\TypeResolver;
use Xterr\UBL\Generator\Xsd\SchemaLoader;
use Xterr\UBL\Generator\Xsd\UblTypeRegistry;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class UblGenerator
{
    private GeneratorConfig $config;
    private SchemaLoader $schemaLoader;
    private UblTypeRegistry $registry;
    private CbcTypeResolver $cbcResolver;
    private TypeResolver $typeResolver;
    private NamingResolver $namingResolver;
    private bool $resolved = false;

    /** @var array<string, ParsedCodelist> keyed by listID */
    private array $parsedCodelists = [];

    /** @var array<string, string> listID → generated FQCN */
    private array $codelistFqcnMap = [];

    public function __construct(GeneratorConfig $config)
    {
        $this->config = $config;
        $this->schemaLoader = new SchemaLoader();
        $this->registry = new UblTypeRegistry();
        $this->namingResolver = new NamingResolver($config);

        $xsdTypesConfigPath = dirname(__DIR__) . '/resources/config/xsd_types.yaml';
        $this->cbcResolver = new CbcTypeResolver($this->registry, $xsdTypesConfigPath);
        $this->typeResolver = new TypeResolver($this->cbcResolver, $xsdTypesConfigPath);
    }

    /**
     * @param callable(string $stage, int $current, int $total): void|null $onProgress
     */
    public function resolve(?callable $onProgress = null): GenerationResult
    {
        $progress = $onProgress ?? static fn() => null;

        $progress('Loading XSD schemas', 0, 0);
        $schemas = $this->schemaLoader->loadAll($this->config->resolveSchemaDir());

        $progress('Building type registry', 0, 0);
        $this->registry->populate($schemas);

        $progress('Resolving CBC leaf types', 0, 0);
        $this->cbcResolver->resolve();

        // Parse codelists if configured
        if ($this->config->codelistDir !== null) {
            $progress('Parsing codelist files', 0, 0);
            $parser = new GenericodeParser();
            $this->parsedCodelists = $parser->parseDirectory($this->config->codelistDir);

            // Pre-build FQCN map for bindings
            foreach ($this->parsedCodelists as $listID => $codelist) {
                $enumName = $this->config->codelistNameOverrides[$listID]
                    ?? $this->namingResolver->toClassName($codelist->shortName);
                $this->codelistFqcnMap[$listID] = $this->config->namespace . '\\' . $this->config->codelistNamespace . '\\' . $enumName;
            }
        }

        $this->resolved = true;

        $cacCount = 0;
        foreach (UblTypeRegistry::COMPONENT_NAMESPACES as $ns) {
            foreach ($this->registry->complexTypesInNamespace($ns) as $ct) {
                if ($ct instanceof ComplexType && $ct->getName() !== null
                    && !$this->isFiltered($ct->getName()) && $ct->getElements() !== []) {
                    $cacCount++;
                }
            }
        }

        $docCount = 0;
        foreach ($this->registry->documentRootElements() as $root) {
            $type = $root->getType();
            if ($type instanceof ComplexType && $root->getName() !== null) {
                $xsdName = $type->getName() ?? $root->getName();
                if (!$this->isFiltered($xsdName)) {
                    $docCount++;
                }
            }
        }

        $enumCount = 0;
        foreach ($this->registry->simpleTypesWithEnumerations() as $st) {
            if ($st->getName() !== null && !$this->isFiltered($st->getName())) {
                $restriction = $st->getRestriction();
                $checks = $restriction !== null ? $restriction->getChecksByType(RestrictionType::ENUMERATION) : [];
                if ($checks !== []) {
                    $enumCount++;
                }
            }
        }

        $cbcCount = count($this->cbcResolver->leafTypes());
        $codelistEnumCount = count($this->parsedCodelists);

        return new GenerationResult(
            schemaVersion: $this->config->schemaVersion,
            stats: $this->registry->stats(),
            cbcClassCount: $cbcCount,
            cacClassCount: $cacCount,
            docClassCount: $docCount,
            enumCount: $enumCount,
            codelistEnumCount: $codelistEnumCount,
            totalFilesWritten: $cbcCount + $cacCount + $docCount + $enumCount + $codelistEnumCount + 2,
        );
    }

    /**
     * @param callable(string $stage, int $current, int $total): void|null $onProgress
     */
    public function generate(?callable $onProgress = null): GenerationResult
    {
        if (!$this->resolved) {
            $this->resolve($onProgress);
        }

        $progress = $onProgress ?? static fn() => null;
        $classEmitter = new ClassEmitter($this->config, $this->namingResolver);
        $enumEmitter = new EnumEmitter($this->config);
        $registryEmitter = new RegistryEmitter($this->config);
        $writer = new FileWriter($this->config->outputDir);

        $cbcCount = 0;
        $cacCount = 0;
        $docCount = 0;
        $enumCount = 0;

        $progress('Emitting CBC leaf classes', 0, 0);
        $leafTypes = $this->cbcResolver->leafTypes();
        foreach ($leafTypes as $leafType) {
            $writer->write(
                $this->config->cbcNamespace . '/' . $leafType->className . '.php',
                $classEmitter->emitLeafClass($leafType),
            );
            $cbcCount++;
        }

        $progress('Emitting aggregate complex classes', 0, 0);
        foreach (UblTypeRegistry::COMPONENT_NAMESPACES as $aggregateNs) {
            foreach ($this->registry->complexTypesInNamespace($aggregateNs) as $complexType) {
                if (!$complexType instanceof ComplexType) {
                    continue;
                }
                $typeName = $complexType->getName();
                if ($typeName === null || $this->isFiltered($typeName)) {
                    continue;
                }
                // Skip simpleContent types — they are leaf classes emitted in Cbc/
                if ($complexType->getElements() === []) {
                    continue;
                }
                $className = $this->namingResolver->toClassName($typeName);
                $writer->write(
                    $this->config->cacNamespace . '/' . $className . '.php',
                    $classEmitter->emitComplexClass(
                        className: $className,
                        xsdTypeName: $typeName,
                        xsdNamespace: $aggregateNs,
                        properties: $this->buildPropertiesForComplexType($complexType, $typeName),
                        documentation: $this->extractDocumentation($complexType),
                    ),
                );
                $cacCount++;
            }
        }

        $progress('Emitting document root classes', 0, 0);
        /** @var array<string, array{namespace: string, localName: string}> $docRoots */
        $docRoots = [];
        foreach ($this->registry->documentRootElements() as $rootElement) {
            $rootName = $rootElement->getName();
            $rootNs = $rootElement->getSchema()->getTargetNamespace();
            $type = $rootElement->getType();
            if ($type === null || $rootName === null || !$type instanceof ComplexType) {
                continue;
            }
            $xsdTypeName = $type->getName() ?? $rootName;
            if ($this->isFiltered($xsdTypeName)) {
                continue;
            }
            $className = $this->namingResolver->toClassName($xsdTypeName);
            $writer->write(
                $this->config->docNamespace . '/' . $className . '.php',
                $classEmitter->emitComplexClass(
                    className: $className,
                    xsdTypeName: $xsdTypeName,
                    xsdNamespace: $rootNs ?? '',
                    properties: $this->buildPropertiesForComplexType($type, $type->getName()),
                    isDocumentRoot: true,
                    rootNamespace: $rootNs,
                    rootLocalName: $rootName,
                    documentation: $this->extractDocumentation($type),
                ),
            );
            $fqcn = $this->config->namespace . '\\' . $this->config->docNamespace . '\\' . $className;
            $docRoots[$fqcn] = ['namespace' => $rootNs ?? '', 'localName' => $rootName];
            $docCount++;
        }

        $progress('Emitting enums', 0, 0);
        foreach ($this->registry->simpleTypesWithEnumerations() as $simpleType) {
            $name = $simpleType->getName();
            if ($name === null || $this->isFiltered($name)) {
                continue;
            }
            $restriction = $simpleType->getRestriction();
            $checks = $restriction !== null ? $restriction->getChecksByType(RestrictionType::ENUMERATION) : [];
            $values = array_map(static fn(array $check): string => (string) $check['value'], $checks);
            if ($values === []) {
                continue;
            }
            $enumName = $this->namingResolver->toClassName($name);
            $writer->write(
                $this->config->enumNamespace . '/' . $enumName . '.php',
                $enumEmitter->emit($enumName, array_values($values), $this->extractDocumentation($simpleType)),
            );
            $enumCount++;
        }

        // Emit codelist enums
        $codelistEnumCount = 0;
        if ($this->parsedCodelists !== []) {
            $progress('Emitting codelist enums', 0, 0);
            $codelistEnumEmitter = new CodelistEnumEmitter($this->config);

            foreach ($this->parsedCodelists as $listID => $codelist) {
                $enumName = $this->config->codelistNameOverrides[$listID]
                    ?? $this->namingResolver->toClassName($codelist->shortName);

                $writer->write(
                    $this->config->codelistNamespace . '/' . $enumName . '.php',
                    $codelistEnumEmitter->emit($enumName, $codelist),
                );
                $codelistEnumCount++;
            }
        }

        $progress('Emitting registries', 0, 0);
        $typeMapEntries = [];
        foreach ($leafTypes as $leafType) {
            $leafFqcn = $this->config->namespace . '\\' . $this->config->cbcNamespace . '\\' . $leafType->className;
            foreach ($leafType->cbcElementNames as $elementName) {
                $typeMapEntries[$elementName] = $leafFqcn;
            }
        }
        $writer->write('Xml/DocumentRegistry.php', $registryEmitter->emitDocumentRegistry($docRoots));
        $writer->write('Xml/ClassMap.php', $registryEmitter->emitClassMap($typeMapEntries));

        return new GenerationResult(
            schemaVersion: $this->config->schemaVersion,
            stats: $this->registry->stats(),
            cbcClassCount: $cbcCount,
            cacClassCount: $cacCount,
            docClassCount: $docCount,
            enumCount: $enumCount,
            codelistEnumCount: $codelistEnumCount,
            totalFilesWritten: $cbcCount + $cacCount + $docCount + $enumCount + $codelistEnumCount + 2,
        );
    }

    /** @return list<ResolvedProperty> */
    private function buildPropertiesForComplexType(ComplexType $complexType, ?string $parentTypeName = null): array
    {
        $properties = [];
        foreach ($complexType->getElements() as $element) {
            $resolved = $this->typeResolver->resolveElement($element);
            $phpType = $this->resolvePhpTypeFqcn($resolved);
            $innerType = $resolved->isArray ? $phpType : null;

            // Check for codelist binding (single or union)
            $codelistEnumTypes = null;
            if ($parentTypeName !== null) {
                $binding = $this->config->getCodelistBinding($parentTypeName, $resolved->xmlElementName);
                if ($binding !== null) {
                    $listIDs = \is_array($binding) ? $binding : [$binding];
                    $resolvedTypes = [];
                    foreach ($listIDs as $listID) {
                        if (isset($this->codelistFqcnMap[$listID])) {
                            $resolvedTypes[] = $this->codelistFqcnMap[$listID];
                        }
                    }
                    if ($resolvedTypes !== []) {
                        $codelistEnumTypes = $resolvedTypes;
                    }
                }
            }

            $properties[] = new ResolvedProperty(
                phpName: $this->namingResolver->toPropertyName($resolved->xmlElementName, $resolved->isArray),
                phpType: $resolved->isArray ? 'array' : $phpType,
                isNullable: $resolved->isNullable,
                isArray: $resolved->isArray,
                xmlElementName: $resolved->xmlElementName,
                xmlNamespace: $resolved->xmlNamespace,
                innerType: $innerType,
                choiceGroup: $resolved->choiceGroup,
                documentation: null,
                required: !$resolved->isNullable,
                codelistEnumTypes: $codelistEnumTypes,
            );
        }
        return $properties;
    }

    private function resolvePhpTypeFqcn(ResolvedType $resolved): string
    {
        if ($resolved->isPrimitive) {
            return $resolved->phpType;
        }
        if ($resolved->isLeafType) {
            return $this->config->namespace . '\\' . $this->config->cbcNamespace . '\\' . $resolved->phpType;
        }
        return $this->config->namespace . '\\' . $this->config->cacNamespace . '\\' . $resolved->phpType;
    }

    private function extractDocumentation(Type $type): ?string
    {
        $doc = $type->getDoc();
        return ($doc === '' || $doc === '0') ? null : trim($doc);
    }

    private function isFiltered(string $typeName): bool
    {
        $baseName = $typeName;
        if (str_ends_with($baseName, 'Type') && strlen($baseName) > 4) {
            $baseName = substr($baseName, 0, -4);
        }
        foreach ($this->config->exclude as $pattern) {
            if (fnmatch($pattern, $baseName) || fnmatch($pattern, $typeName)) {
                return true;
            }
        }
        if ($this->config->include !== []) {
            foreach ($this->config->include as $pattern) {
                if (fnmatch($pattern, $baseName) || fnmatch($pattern, $typeName)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

}
