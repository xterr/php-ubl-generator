<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Emitter;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\Resolver\NamingResolver;
use Xterr\UBL\Generator\Resolver\ResolvedLeafType;
use Xterr\UBL\Xml\Mapping\XmlAttribute as XmlAttributeMapping;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlNamespace as Ns;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;
use Xterr\UBL\Xml\Mapping\XmlValue;

final class ClassEmitter
{
    private readonly PsrPrinter $printer;

    public function __construct(
        private readonly GeneratorConfig $config,
        private readonly NamingResolver $namingResolver,
    ) {
        $this->printer = new PsrPrinter();
    }

    /**
     * Emit a Cbc leaf class (Amount, Code, Identifier, Text, etc.).
     */
    public function emitLeafClass(ResolvedLeafType $leafType): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $fqcn = $this->config->namespace . '\\' . $this->config->cbcNamespace . '\\' . $leafType->className;
        $namespaceName = $this->config->namespace . '\\' . $this->config->cbcNamespace;

        $ns = $file->addNamespace($namespaceName);
        $ns->addUse(XmlType::class);
        $ns->addUse(XmlValue::class);
        $ns->addUse(XmlAttributeMapping::class);
        $ns->addUse(Ns::class, 'Ns');

        $class = $ns->addClass($leafType->className);
        $class->setFinal();

        $this->addClassPhpDoc($class, null);

        // #[XmlType(localName: '...Type', namespace: Ns::CBC)]
        $class->addAttribute(XmlType::class, [
            'localName' => $leafType->className . 'Type',
            'namespace' => new Literal('Ns::CBC'),
        ]);

        // Value property
        $valueProp = $class->addProperty('value')
            ->setPrivate()
            ->setType($leafType->valuePhpType)
            ->setNullable()
            ->setValue(null);
        $valueProp->addAttribute(XmlValue::class);

        // Value getter
        $class->addMethod('getValue')
            ->setPublic()
            ->setReturnType($leafType->valuePhpType)
            ->setReturnNullable()
            ->setBody('return $this->value;');

        // Value setter
        $valueSetter = $class->addMethod('setValue')
            ->setPublic()
            ->setReturnType('self');
        $valueSetter->addParameter('value')
            ->setType($leafType->valuePhpType)
            ->setNullable()
            ->setDefaultValue(null);

        if ($leafType->valuePhpType === 'string') {
            $valueSetter->setBody(<<<'PHP'
$this->value = $value;
return $this;
PHP);
        } else {
            $valueSetter->setBody(<<<'PHP'
$this->value = $value;
return $this;
PHP);
        }

        // Attribute properties
        foreach ($leafType->attributes as $attr) {
            $prop = $class->addProperty($attr->phpName)
                ->setPrivate()
                ->setType($attr->phpType)
                ->setNullable()
                ->setValue(null);
            $prop->addAttribute(XmlAttributeMapping::class, [
                'name' => $attr->xmlName,
                'required' => $attr->required,
            ]);

            // Getter
            $class->addMethod('get' . ucfirst($attr->phpName))
                ->setPublic()
                ->setReturnType($attr->phpType)
                ->setReturnNullable()
                ->setBody('return $this->' . $attr->phpName . ';');

            // Setter
            $setter = $class->addMethod('set' . ucfirst($attr->phpName))
                ->setPublic()
                ->setReturnType('self');
            $setter->addParameter($attr->phpName)
                ->setType($attr->phpType)
                ->setNullable()
                ->setDefaultValue(null);
            $setter->setBody(
                '$this->' . $attr->phpName . ' = $' . $attr->phpName . ';' . "\n"
                . 'return $this;'
            );
        }

        // __toString
        $class->addMethod('__toString')
            ->setPublic()
            ->setReturnType('string')
            ->setBody('return (string) $this->value;');

        return $this->printer->printFile($file);
    }

    /**
     * Emit a Cac or Doc complex class.
     *
     * @param list<ResolvedProperty> $properties
     */
    public function emitComplexClass(
        string $className,
        string $xsdTypeName,
        string $xsdNamespace,
        array $properties,
        bool $isDocumentRoot = false,
        ?string $rootNamespace = null,
        ?string $rootLocalName = null,
        ?string $documentation = null,
    ): string {
        $file = new PhpFile();
        $file->setStrictTypes();

        $subNamespace = $isDocumentRoot ? $this->config->docNamespace : $this->config->cacNamespace;
        $namespaceName = $this->config->namespace . '\\' . $subNamespace;

        $ns = $file->addNamespace($namespaceName);
        $ns->addUse(XmlType::class);
        $ns->addUse(XmlElement::class);
        $ns->addUse(Ns::class, 'Ns');

        if ($isDocumentRoot) {
            $ns->addUse(XmlRoot::class);
        }

        if ($this->config->generateValidatorAttributes) {
            $ns->addUse('Symfony\Component\Validator\Constraints', 'Assert');

            $hasChoiceGroup = false;
            foreach ($properties as $prop) {
                if ($prop->choiceGroup !== null) {
                    $hasChoiceGroup = true;
                    break;
                }
            }
            if ($hasChoiceGroup) {
                $ns->addUse(\Xterr\UBL\Validation\ChoiceGroupConstraint::class);
            }
        }

        $importedTypes = [];
        foreach ($properties as $prop) {
            // Import the codelist enum type if bound, otherwise the standard php type
            $effectiveType = $prop->codelistEnumType ?? ($prop->isArray ? $prop->innerType : $prop->phpType);
            if ($effectiveType !== null && !$this->isBuiltinType($effectiveType) && !isset($importedTypes[$effectiveType])) {
                $sameNamespace = str_starts_with($effectiveType, $namespaceName . '\\')
                    && !str_contains(substr($effectiveType, strlen($namespaceName) + 1), '\\');
                if ($sameNamespace) {
                    continue;
                }
                $importedTypes[$effectiveType] = true;
                $ns->addUse($effectiveType);
            }

            // Also import the original type if different (needed for array inner types when codelist applies)
            $typeToImport = $prop->isArray ? $prop->innerType : $prop->phpType;
            if ($typeToImport !== null && $typeToImport !== $effectiveType && !$this->isBuiltinType($typeToImport) && !isset($importedTypes[$typeToImport])) {
                $sameNamespace = str_starts_with($typeToImport, $namespaceName . '\\')
                    && !str_contains(substr($typeToImport, strlen($namespaceName) + 1), '\\');
                if (!$sameNamespace) {
                    $importedTypes[$typeToImport] = true;
                    $ns->addUse($typeToImport);
                }
            }
        }

        $class = $ns->addClass($className);
        $class->setFinal();

        $this->addClassPhpDoc($class, $documentation);

        // #[XmlType]
        $nsLiteral = $this->resolveNamespaceLiteral($xsdNamespace);
        $class->addAttribute(XmlType::class, [
            'localName' => $xsdTypeName,
            'namespace' => $nsLiteral,
        ]);

        // #[XmlRoot] for document root classes
        if ($isDocumentRoot && $rootLocalName !== null && $rootNamespace !== null) {
            $rootNsLiteral = $this->resolveNamespaceLiteral($rootNamespace);
            $class->addAttribute(XmlRoot::class, [
                'localName' => $rootLocalName,
                'namespace' => $rootNsLiteral,
            ]);
        }

        // Properties, getters, setters
        foreach ($properties as $prop) {
            $this->addComplexProperty($class, $ns, $prop, $this->config->generateValidatorAttributes);
        }

        return $this->printer->printFile($file);
    }

    private function addComplexProperty(ClassType $class, PhpNamespace $ns, ResolvedProperty $prop, bool $withValidatorAttributes = false): void
    {
        $nsLiteral = $this->resolveNamespaceLiteral($prop->xmlNamespace);

        if ($prop->isArray) {
            // Array property
            $property = $class->addProperty($prop->phpName)
                ->setPrivate()
                ->setType('array')
                ->setValue([]);

            $shortType = $prop->innerType !== null ? $this->shortClassName($prop->innerType) : 'mixed';
            $property->addComment('@var list<' . $shortType . '>');

            if ($withValidatorAttributes) {
                if ($prop->innerType !== null && !$this->isBuiltinType($prop->innerType)) {
                    $property->addAttribute('Symfony\Component\Validator\Constraints\Valid');
                }
                if ($prop->required) {
                    $property->addAttribute('Symfony\Component\Validator\Constraints\Count', ['min' => 1]);
                }
                if ($prop->choiceGroup !== null) {
                    $property->addAttribute(\Xterr\UBL\Validation\ChoiceGroupConstraint::class, ['group' => $prop->choiceGroup]);
                }
            }

            $xmlElementArgs = [
                'name' => $prop->xmlElementName,
                'namespace' => $nsLiteral,
            ];
            if ($prop->innerType !== null) {
                $xmlElementArgs['type'] = new Literal($shortType . '::class');
            }
            if ($prop->required) {
                $xmlElementArgs['required'] = true;
            }
            if ($prop->choiceGroup !== null) {
                $xmlElementArgs['choiceGroup'] = $prop->choiceGroup;
            }
            $property->addAttribute(XmlElement::class, $xmlElementArgs);

            // Getter
            $class->addMethod('get' . ucfirst($prop->phpName))
                ->setPublic()
                ->setReturnType('array')
                ->addComment('@return list<' . $shortType . '>')
                ->setBody('return $this->' . $prop->phpName . ';');

            // Setter
            $setter = $class->addMethod('set' . ucfirst($prop->phpName))
                ->setPublic()
                ->setReturnType('self');
            $setter->addParameter($prop->phpName)
                ->setType('array');
            $setter->addComment('@param list<' . $shortType . '> $' . $prop->phpName);
            $setter->setBody(
                'self::validate' . ucfirst($prop->phpName) . '($' . $prop->phpName . ');' . "\n"
                . '$this->' . $prop->phpName . ' = $' . $prop->phpName . ';' . "\n"
                . 'return $this;'
            );

            // addTo method
            $adder = $class->addMethod('addTo' . ucfirst($prop->phpName))
                ->setPublic()
                ->setReturnType('self');
            if ($prop->innerType !== null) {
                $adder->addParameter('item')
                    ->setType($prop->innerType);
            } else {
                $adder->addParameter('item');
            }
            $adder->setBody(
                '$this->' . $prop->phpName . '[] = $item;' . "\n"
                . 'return $this;'
            );

            // Static validation method
            $validator = $class->addMethod('validate' . ucfirst($prop->phpName))
                ->setPrivate()
                ->setStatic()
                ->setReturnType('void');
            $validator->addParameter('values')
                ->setType('array');
            $validator->addComment('@param list<' . $shortType . '> $values');

            if ($prop->innerType !== null) {
                $validator->setBody(
                    'foreach ($values as $value) {' . "\n"
                    . '    if (!$value instanceof ' . $shortType . ') {' . "\n"
                    . '        throw new \\InvalidArgumentException(' . "\n"
                    . '            sprintf(\'Expected instance of ' . $shortType . ', got %s\', get_debug_type($value)),' . "\n"
                    . '        );' . "\n"
                    . '    }' . "\n"
                    . '}'
                );
            } else {
                $validator->setBody('// No type validation for untyped array');
            }
        } else {
            // Scalar / single-object property
            // When a codelist enum is bound, use it instead of the original CBC type
            $effectiveType = $prop->codelistEnumType ?? $prop->phpType;

            $property = $class->addProperty($prop->phpName)
                ->setPrivate()
                ->setType($effectiveType)
                ->setNullable($prop->isNullable);
            if ($prop->isNullable) {
                $property->setValue(null);
            }

            if ($withValidatorAttributes && $prop->codelistEnumType === null) {
                if ($prop->required) {
                    $property->addAttribute('Symfony\Component\Validator\Constraints\NotBlank');
                }
                if (!$this->isBuiltinType($effectiveType)) {
                    $property->addAttribute('Symfony\Component\Validator\Constraints\Valid');
                }
                if ($prop->choiceGroup !== null) {
                    $property->addAttribute(\Xterr\UBL\Validation\ChoiceGroupConstraint::class, ['group' => $prop->choiceGroup]);
                }
            }

            $xmlElementArgs = [
                'name' => $prop->xmlElementName,
                'namespace' => $nsLiteral,
            ];
            if ($prop->required) {
                $xmlElementArgs['required'] = true;
            }
            if ($prop->choiceGroup !== null) {
                $xmlElementArgs['choiceGroup'] = $prop->choiceGroup;
            }
            $property->addAttribute(XmlElement::class, $xmlElementArgs);

            // Getter
            $getter = $class->addMethod('get' . ucfirst($prop->phpName))
                ->setPublic()
                ->setReturnType($effectiveType)
                ->setReturnNullable($prop->isNullable)
                ->setBody('return $this->' . $prop->phpName . ';');

            // Setter
            $setter = $class->addMethod('set' . ucfirst($prop->phpName))
                ->setPublic()
                ->setReturnType('self');
            $setter->addParameter($prop->phpName)
                ->setType($effectiveType)
                ->setNullable($prop->isNullable);
            if ($prop->isNullable) {
                $setter->getParameter($prop->phpName)->setDefaultValue(null);
            }
            $setter->setBody(
                '$this->' . $prop->phpName . ' = $' . $prop->phpName . ';' . "\n"
                . 'return $this;'
            );
        }
    }

    private function addClassPhpDoc(ClassType $class, ?string $documentation): void
    {
        if ($this->config->includeGeneratedTag) {
            $class->addComment('@generated by php-ubl code generator — do not edit');
        }

        if ($this->config->includeDocumentation && $documentation !== null && $documentation !== '') {
            if ($this->config->includeGeneratedTag) {
                $class->addComment('');
            }
            $class->addComment($documentation);
        }
    }

    private function resolveNamespaceLiteral(string $xmlNamespace): Literal|string
    {
        $prefix = Ns::prefixFor($xmlNamespace);
        if ($prefix !== null) {
            return new Literal('Ns::' . strtoupper($prefix));
        }

        return $xmlNamespace;
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    private function isBuiltinType(string $type): bool
    {
        return in_array($type, [
            'string', 'int', 'float', 'bool', 'array', 'object',
            'callable', 'iterable', 'void', 'never', 'mixed', 'null',
            'true', 'false',
        ], true);
    }
}
