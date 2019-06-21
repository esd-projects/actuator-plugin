<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/24
 * Time: 14:42
 */

namespace ESD\Plugins\Actuator;

use ESD\Core\Context\Context;
use ESD\Core\Memory\CrossProcess\Table;
use ESD\Core\PlugIn\AbstractPlugin;
use ESD\Core\PlugIn\PluginInterfaceManager;
use ESD\Core\Plugins\Logger\GetLogger;
use ESD\Core\Server\Process\Process;
use ESD\Core\Server\Server;
use ESD\Plugins\Actuator\Aspect\ActuatorAspect;
use ESD\Plugins\Actuator\Aspect\CountAspect;
use ESD\Plugins\Aop\AopConfig;
use ESD\Plugins\Aop\AopPlugin;
use ESD\Plugins\EasyRoute\EasyRoutePlugin;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;


class ActuatorPlugin extends AbstractPlugin
{
    use GetLogger;


    /**
     * @var Table
     */
    protected  $table;

    public function __construct()
    {
        parent::__construct();
        //需要aop的支持，所以放在aop后加载
        $this->atAfter(AopPlugin::class);
        //由于Aspect排序问题需要在EasyRoutePlugin之前加载
        $this->atBefore(EasyRoutePlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \ESD\Core\Exception
     * @throws \ReflectionException
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
     * @throws \ESD\Core\Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        $serverConfig = Server::$instance->getServerConfig();
        $aopConfig = DIget(AopConfig::class);
        $actuatorController = new ActuatorController();
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute("GET", "/actuator", "index");
            $r->addRoute("GET", "/actuator/health", "health");
            $r->addRoute("GET", "/actuator/info", "info");
        });
        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/esd/base-server");
        $aopConfig->addAspect(new ActuatorAspect($actuatorController, $dispatcher));
        $aopConfig->addAspect(new CountAspect());
    }

    /**
     * 在服务启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context)
    {
        /**
         * 1byte(int8)：-127 ~ 127
        2byte(int16)：-32767 ~ 32767
        4byte(int32)：-2147483647 ~ 2147483647
        8byte(int64)：不会溢出
         */
        $table = new Table(1024);
        $table->column('num_60', Table::TYPE_INT,4);
        $table->column('num_3600', Table::TYPE_INT,4);
        $table->column('num_86400', Table::TYPE_INT, 4);
        if(!$table->create()){
            throw  new \Exception('memory not allow');
        }
        $this->setToDIContainer('RouteCountTable', $table);
        $this->table = $table;

        return;
    }

    /**
     * 在进程启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessType() != Process::PROCESS_TYPE_WORKER) {
            $this->ready();
            return;
        }

        addTimerTick(60 * 1000 , function (){
            $this->updateCount('num_60');
        });

        addTimerTick(3600 * 1000, function (){
            $this->updateCount('num_3600');
        });

        addTimerTick(86400 * 1000, function (){
            $this->updateCount('num_86400');
        });
        $this->debug('beforeProcessStart');
        $this->ready();
    }


    function updateCount($column){
        foreach ($this->table as $key  => $num) {
            $this->table->set($key,[$column => 0]);
            $this->debug('updateCount ' .$key .':'. $column. ' -> 0');
        }
    }
}