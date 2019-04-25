<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/24
 * Time: 14:54
 */

namespace GoSwoole\Plugins\Actuator\Aspect;


use FastRoute\Dispatcher;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;
use GoSwoole\BaseServer\Server\Beans\Request;
use GoSwoole\BaseServer\Server\Server;
use GoSwoole\Plugins\Actuator\ActuatorController;

class ActuatorAspect implements Aspect
{
    /**
     * @var ActuatorController
     */
    private $actuatorController;
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(ActuatorController $actuatorController, Dispatcher $dispatcher)
    {
        $this->actuatorController = $actuatorController;
        $this->dispatcher = $dispatcher;
    }

    /**
     * around onHttpRequest
     *
     * @param MethodInvocation $invocation Invocation
     * @Around("within(GoSwoole\BaseServer\Server\IServerPort+) && execution(public **->onHttpRequest(*))")
     * @return mixed|null
     */
    protected function aroundRequest(MethodInvocation $invocation)
    {
        try {
            list($request, $response) = $invocation->getArguments();

            $routeInfo = $this->dispatcher->dispatch($request->getServer(Request::SERVER_REQUEST_METHOD), $request->getServer(Request::SERVER_REQUEST_URI));
            switch ($routeInfo[0]) {
                case Dispatcher::NOT_FOUND:
                    return $invocation->proceed();
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $response->setStatus(405);
                    $response->addHeader("Content-Type", "text/html; charset=utf-8");
                    $response->end("不支持的请求方法");
                    return null;
                case Dispatcher::FOUND: // 找到对应的方法
                    $className = $routeInfo[1];
                    $vars = $routeInfo[2]; // 获取请求参数
                    $response->addHeader("Content-Type", "application/json; charset=utf-8");
                    $response->end($this->actuatorController->$className($vars));
                    return null;
            }
        } catch (\Throwable $e) {
            $log = Server::$instance->getLog();
            $log->error($e);
        }
        return null;
    }
}