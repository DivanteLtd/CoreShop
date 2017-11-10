<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Bundle\ResourceBundle\DependencyInjection\Extension;

use CoreShop\Bundle\ResourceBundle\DependencyInjection\Driver\DriverProvider;
use CoreShop\Component\Resource\Metadata\Metadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

abstract class AbstractModelExtension extends Extension
{
    /**
     * @param string $applicationName
     * @param string $driver
     * @param array $resources
     * @param ContainerBuilder $container
     */
    protected function registerResources($applicationName, $driver, array $resources, ContainerBuilder $container)
    {
        $container->setParameter(sprintf('%s.driver.%s', $this->getAlias(), $driver), true);
        $container->setParameter(sprintf('%s.driver', $this->getAlias()), $driver);

        foreach ($resources as $resourceName => $resourceConfig) {
            $alias = $applicationName . '.' . $resourceName;
            $resourceConfig = array_merge(['driver' => $driver], $resourceConfig);

            $resources = $container->hasParameter('coreshop.resources') ? $container->getParameter('coreshop.resources') : [];
            $resources = array_merge($resources, [$alias => $resourceConfig]);
            $container->setParameter('coreshop.resources', $resources);

            $metadata = Metadata::fromAliasAndConfiguration($alias, $resourceConfig);

            DriverProvider::get($metadata)->load($container, $metadata);

            if ($metadata->hasParameter('translation')) {
                $alias = $alias . '_translation';
                $resourceConfig = array_merge(['driver' => $driver], $resourceConfig['translation']);

                $resources = $container->hasParameter('coreshop.resources') ? $container->getParameter('coreshop.resources') : [];
                $resources = array_merge($resources, [$alias => $resourceConfig]);
                $container->setParameter('coreshop.resources', $resources);

                $metadata = Metadata::fromAliasAndConfiguration($alias, $resourceConfig);

                DriverProvider::get($metadata)->load($container, $metadata);
            }
        }
    }

    /**
     * @param $applicationName
     * @param array $models
     * @param ContainerBuilder $container
     */
    protected function registerPimcoreModels($applicationName, array $models, ContainerBuilder $container)
    {
        $container->setParameter(sprintf('%s.driver.%s', $this->getAlias(), 'pimcore'), true);
        $container->setParameter(sprintf('%s.driver', $this->getAlias()), 'pimcore');

        foreach ($models as $modelName => $modelConfig) {
            $alias = $applicationName . '.' . $modelName;
            $modelConfig = array_merge(['driver' => 'pimcore', 'alias' => $this->getAlias()], $modelConfig);

            foreach (['coreshop.pimcore', sprintf('%s.pimcore.classes', $applicationName)] as $parameter) {
                $models = $container->hasParameter($parameter) ? $container->getParameter($parameter) : [];
                $models = array_merge($models, [$alias => $modelConfig]);
                $container->setParameter($parameter, $models);
            }

            $metadata = Metadata::fromAliasAndConfiguration($alias, $modelConfig);

            DriverProvider::get($metadata)->load($container, $metadata);
        }
    }

    /**
     * @param $applicationName
     * @param $bundleResources
     * @param ContainerBuilder $container
     */
    protected function registerPimcoreResources($applicationName, $bundleResources, ContainerBuilder $container)
    {
        $resourceTypes = ['js', 'css'];

        foreach ($resourceTypes as $resourceType) {
            if (array_key_exists($resourceType, $bundleResources)) {
                $applicationParameter = sprintf('%s.application.pimcore.admin.%s', $applicationName, $resourceType);
                $aliasParameter = sprintf('%s.pimcore.admin.%s', $this->getAlias(), $resourceType);
                $globalParameter = sprintf('resources.admin.%s', $resourceType);

                $parameters = [
                    $applicationParameter, $aliasParameter, $globalParameter
                ];

                foreach ($parameters as $containerParameter) {
                    $resources = [];

                    if ($container->hasParameter($containerParameter)) {
                        $resources = $container->getParameter($containerParameter);
                    }

                    $container->setParameter($containerParameter, array_merge($resources, array_values($bundleResources[$resourceType])));
                }
            }
        }

        if (array_key_exists('install', $bundleResources)) {
            foreach ($bundleResources['install'] as $type => $value) {
                $applicationParameter = sprintf('%s.application.pimcore.admin.install.%s', $applicationName, $type);
                $aliasParameter = sprintf('%s.pimcore.admin.install.%s', $this->getAlias(), $type);
                $globalParameter = sprintf('resources.admin.install.%s', $type);

                foreach ([$applicationParameter, $aliasParameter, $globalParameter] as $containerParameter) {
                    $resources = [];

                    if ($container->hasParameter($containerParameter)) {
                        $resources = $container->getParameter($containerParameter);
                    }

                    $container->setParameter($containerParameter, array_merge($resources, array_values($value)));
                }
            }
        }

        if (array_key_exists('permissions', $bundleResources)) {
            $applicationPermissions = [];
            $applicationParameter = sprintf('%s.permissions', $applicationName);
            $resourcePermissions = [];
            $globalParameter = sprintf('coreshop.resource.permissions', $applicationName);

            if ($container->hasParameter($applicationParameter)) {
                $applicationPermissions = $container->getParameter($applicationParameter);
            }

            if ($container->hasParameter($globalParameter)) {
                $resourcePermissions = $container->getParameter($globalParameter);
            }

            $permissions = [];

            foreach ($bundleResources['permissions'] as $permission) {
                $identifier = sprintf('%s_permission_%s', $applicationName, $permission);

                $permissions[] = $identifier;
                $resourcePermissions[] = $identifier;
            }

            $container->setParameter($globalParameter, array_merge($applicationPermissions, $permissions));
            $container->setParameter($applicationParameter, array_merge($applicationPermissions, $permissions));
        }
    }

    /**
     * @param $applicationName
     * @param $implementations
     * @param ContainerBuilder $container
     */
    public function registerImplementations($applicationName, $implementations, ContainerBuilder $container) {
        $appParameterName = sprintf('%s.coreshop.application.implementations', $applicationName);
        $globalParameterName = 'coreshop.implementations';

        foreach ([$appParameterName, $globalParameterName] as $parameterName) {
            $implementationsConfig = $container->hasParameter($parameterName) ? $container->getParameter($parameterName) : [];

            foreach ($implementations as $key => $interface) {
                $key = sprintf('%s.%s', $applicationName, $key);

                if (array_key_exists($key, $implementationsConfig)) {
                    throw new \RuntimeException(sprintf('Implementations Key %s found twice', $key));
                }

                $implementationsConfig[$key] = $interface;
            }

            $container->setParameter($parameterName, $implementationsConfig);
        }
    }
}