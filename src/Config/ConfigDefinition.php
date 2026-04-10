<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ubl_generator');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->scalarNode('schema_version')->defaultValue('2.4')->end()
            ->scalarNode('schema_dir')->defaultNull()->info('Path to XSD schemas directory. Defaults to bundled schemas.')->end()
            ->scalarNode('output_dir')->defaultValue('src')->info('Output directory for generated classes')->end()
            ->scalarNode('namespace')->defaultValue('Xterr\\UBL')->info('Root PHP namespace for generated classes')->end()
            ->arrayNode('namespaces')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('cbc')->defaultValue('Cbc')->end()
                    ->scalarNode('cac')->defaultValue('Cac')->end()
                    ->scalarNode('doc')->defaultValue('Doc')->end()
                    ->scalarNode('enum')->defaultValue('Enum')->end()
                ->end()
            ->end()
            ->arrayNode('include')
                ->scalarPrototype()->end()
                ->defaultValue([])
                ->info('Glob patterns to include specific types (empty = all)')
            ->end()
            ->arrayNode('exclude')
                ->scalarPrototype()->end()
                ->defaultValue([])
                ->info('Glob patterns to exclude specific types')
            ->end()
            ->arrayNode('type_overrides')
                ->scalarPrototype()->end()
                ->defaultValue([])
                ->info('XSD type to PHP type overrides, e.g. {"xsd:decimal": "float"}')
            ->end()
            ->arrayNode('class_name_overrides')
                ->scalarPrototype()->end()
                ->defaultValue([])
                ->info('XSD type name to PHP class name overrides')
            ->end()
            ->arrayNode('property_name_overrides')
                ->scalarPrototype()->end()
                ->defaultValue([])
                ->info('XSD element name to PHP property name overrides')
            ->end()
            ->booleanNode('include_documentation')->defaultTrue()->info('Include XSD documentation in PHPDoc')->end()
            ->booleanNode('generate_validation')->defaultTrue()->info('Generate setter validation rules')->end()
            ->booleanNode('generate_validator_attributes')->defaultFalse()->info('Generate symfony/validator #[Assert\\*] attributes')->end()
            ->booleanNode('include_generated_tag')->defaultTrue()->info('Include @generated tag in PHPDoc')->end()
        ->end();

        return $treeBuilder;
    }
}
