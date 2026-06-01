<?php

defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Ramblers\Component\Ra_delivery\Administrator\Extension\Ra_deliveryComponent;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->registerServiceProvider(new CategoryFactory('\\Ramblers\\Component\\Ra_delivery'));
        $container->registerServiceProvider(new MVCFactory('\\Ramblers\\Component\\Ra_delivery'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Ramblers\\Component\\Ra_delivery'));
        $container->registerServiceProvider(new RouterFactory('\\Ramblers\\Component\\Ra_delivery'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new Ra_deliveryComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
