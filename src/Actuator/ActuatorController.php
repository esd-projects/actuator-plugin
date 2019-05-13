<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/25
 * Time: 17:37
 */

namespace ESD\Plugins\Actuator;

/**
 * Class ActuatorController
 * @package ESD\Plugins\Actuator
 */
class ActuatorController
{
    public function index()
    {
        return json_encode(["status"=>"UP"]);
    }

    public function health()
    {
        return json_encode(["status"=>"UP"]);
    }

    public function info()
    {
        return json_encode(["status"=>"UP"]);
    }
}