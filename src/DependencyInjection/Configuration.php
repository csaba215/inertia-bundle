<?php

namespace Rompetomp\InertiaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 *
 * @author  Hannes Vermeire <hannes@codedor.be>
 * @author  Tudorache Leonard Valentin <tudorache.leonard@wyverr.com>
 *
 * @since   2019-08-02
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('inertia');
        $treeBuilder
            ->getRootNode()
            ->children()
            ->scalarNode('root_view')
            ->defaultValue('base.html.twig')
            ->end()
            ->booleanNode('expose_shared_prop_keys')
            ->defaultTrue()
            ->end()
            ->arrayNode('validation')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('all_errors')
            ->defaultFalse()
            ->end()
            ->end()
            ->end()
            ->arrayNode('version')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('manifest_paths')
            ->scalarPrototype()
            ->end()
            ->defaultValue([])
            ->end()
            ->scalarNode('asset_url')
            ->defaultValue(null)
            ->end()
            ->end()
            ->end()
            ->arrayNode('pages')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('ensure_pages_exist')
            ->defaultFalse()
            ->end()
            ->arrayNode('paths')
            ->scalarPrototype()
            ->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('extensions')
            ->scalarPrototype()
            ->end()
            ->defaultValue(['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'])
            ->end()
            ->end()
            ->end()
            ->arrayNode('testing')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('ensure_pages_exist')
            ->defaultTrue()
            ->end()
            ->end()
            ->end()
            ->arrayNode('history')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('encrypt')
            ->defaultFalse()
            ->end()
            ->end()
            ->end()
            ->arrayNode('ssr')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->scalarNode('url')
            ->defaultValue('')
            ->end()
            ->end()
            ->end()
            ->arrayNode('csrf')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('cookie_name')
            ->defaultValue('XSRF-TOKEN')
            ->end()
            ->scalarNode('header_name')
            ->defaultValue('X-XSRF-TOKEN')
            ->end()
            ->scalarNode('expire')
            ->defaultValue(0)
            ->end()
            ->scalarNode('path')
            ->defaultValue('/')
            ->end()
            ->scalarNode('domain')
            ->defaultValue(null)
            ->end()
            ->scalarNode('secure')
            ->defaultValue(false)
            ->end()
            ->scalarNode('raw')
            ->defaultValue(false)
            ->end()
            ->scalarNode('samesite')
            ->defaultValue('lax')
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
