<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/24
 * Time: 14:42
 */

namespace GoSwoole\Plugins\Actuator;

use FastRoute\RouteCollector;
use GoSwoole\BaseServer\Plugins\Logger\GetLogger;
use GoSwoole\BaseServer\Server\Context;
use GoSwoole\BaseServer\Server\Plugin\AbstractPlugin;
use GoSwoole\BaseServer\Server\Plugin\PluginInterfaceManager;
use GoSwoole\Plugins\Actuator\Aspect\ActuatorAspect;
use GoSwoole\Plugins\Aop\AopConfig;
use GoSwoole\Plugins\Aop\AopPlugin;
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
        $this->atBefore("GoSwoole\Plugins\EasyRoute\EasyRoutePlugin");
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \GoSwoole\BaseServer\Exception
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $serverConfig = $pluginInterfaceManager->getServer()->getServerConfig();
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $aopConfig = new AopConfig($serverConfig->getVendorDir() . "/go-swoole/base-server");
            $aopPlugin = new AopPlugin($aopConfig);
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
     * 在服务启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context)
    {
        $serverConfig = $context->getServer()->getServerConfig();
        $actuatorController = new ActuatorController();
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute("GET", "/actuator", "index");
            $r->addRoute("GET", "/actuator/health", "health");
            $r->addRoute("GET", "/actuator/info", "info");
        });
        //AOP注入
        $aopPlugin = $context->getServer()->getPlugManager()->getPlug(AopPlugin::class);
        if ($aopPlugin instanceof AopPlugin) {
            $aopPlugin->getAopConfig()->addIncludePath($serverConfig->getVendorDir() . "/go-swoole/base-server");
            $aopPlugin->getAopConfig()->addAspect(new ActuatorAspect($actuatorController, $dispatcher));
        } else {
            $this->error("没有添加AOP插件，Actuator无法工作");
        }
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