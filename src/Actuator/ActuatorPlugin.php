<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/24
 * Time: 14:42
 */

namespace ESD\Plugins\Actuator;

use ESD\BaseServer\Plugins\Logger\GetLogger;
use ESD\BaseServer\Server\Context;
use ESD\BaseServer\Server\Plugin\AbstractPlugin;
use ESD\BaseServer\Server\Plugin\PluginInterfaceManager;
use ESD\BaseServer\Server\Server;
use ESD\Plugins\Actuator\Aspect\ActuatorAspect;
use ESD\Plugins\Aop\AopConfig;
use ESD\Plugins\Aop\AopPlugin;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

;

class ActuatorPlugin extends AbstractPlugin
{
    use GetLogger;

    public function __construct()
    {
        parent::__construct();
        //需要aop的支持，所以放在aop后加载
        $this->atAfter(AopPlugin::class);
        //由于Aspect排序问题需要在EasyRoutePlugin之前加载
        $this->atBefore("ESD\Plugins\EasyRoute\EasyRoutePlugin");
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \ReflectionException
     * @throws \DI\DependencyException
     * @throws \ESD\BaseServer\Exception
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $aopPlugin = new AopPlugin();
            $pluginInterfaceManager->addPlug($aopPlugin);
        }
    }

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string
    {
        return "Actuator";
    }

    /**
     * @param Context $context
     * @return mixed|void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \ESD\BaseServer\Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        $serverConfig = Server::$instance->getServerConfig();
        $aopConfig = Server::$instance->getContainer()->get(AopConfig::class);
        $actuatorController = new ActuatorController();
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute("GET", "/actuator", "index");
            $r->addRoute("GET", "/actuator/health", "health");
            $r->addRoute("GET", "/actuator/info", "info");
        });
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/esd/base-server");
        $aopConfig->addAspect(new ActuatorAspect($actuatorController, $dispatcher));
    }

    /**
     * 在服务启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context)
    {
        return;
    }

    /**
     * 在进程启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}