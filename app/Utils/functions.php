<?php
// 开启严格模式
declare(strict_types=1);

use Hyperf\Framework\Logger\StdoutLogger;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;

if (!function_exists('test')) {
    function test()
    {
        return "测试代码";
    }
}

if (!function_exists('setContext')) {
    /**
     * 设置key value写入当前请求上下文
     * @param $key
     * @param $value
     * @return mixed
     */
    function setContext($key,$value)
    {
        return Context::set($key,$value);
    }
}

if (!function_exists('getContext')) {
    /**
     * 获取当前请求上下文 key 的 value
     * @param $key
     * @param $value
     * @return mixed
     */
    function getContext($key)
    {
        return Context::get($key);
    }
}

if (!function_exists('hasContext')) {
    /**
     * 获取当前请求上下文 key 的 value
     * @param $key
     * @param $value
     * @return mixed
     */
    function hasContext($key)
    {
        return Context::has($key);
    }
}

if (!function_exists('redis')) {
    /**
     * Redis
     * @param string $name
     * @return \Hyperf\Redis\RedisProxy|Redis
     */
    function redis($name = 'default')
    {
        return di()->get(RedisFactory::class)->get($name);
    }
}

if (!function_exists('dd')) {
    /**
     * 终端打印调试
     * @param $data
     */
    function dd($data)
    {
        stdout()->info("-----------------打印调试开启-----------------");
        print_r($data);
        echo PHP_EOL;
        stdout()->info("-----------------打印调试结束-----------------");
    }
}

if (!function_exists('di')) {
    /**
     * @return \Psr\Container\ContainerInterface
     */
    function di()
    {
        return ApplicationContext::getContainer();
    }
}
if (!function_exists('stdout')) {
    /**
     * 终端日志
     * @return StdoutLogger|mixed
     */
    function stdout()
    {
        return di()->get(StdoutLogger::class);
    }
}