<?php


namespace App\WebSocket;

use Swoole\WebSocket\Server as WebSocketServer;
use Psr\Container\ContainerInterface;
use Swoole\Websocket\Frame;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;

class BaseController
{
    /**
     * @var WebSocketServer
     */
    protected $server;

    /**
     * @var Frame
     */
    protected $frame;

    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(WebSocketServer $server,Frame $frame,ContainerInterface $container)
    {
        $this->server = $server;
        $this->frame = $frame;
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    /**
     * 判断当前用户是否在线
     * @param int $fd
     * @return bool
     */
    public function exist(int $fd):bool
    {
        return $this->server->exist($fd);
    }

    /**
     * 向指定用户发送消息
     * @param int $fd
     * @param $data
     * @param int $opcode
     * @param bool $finish
     * @return bool
     */
    public function push(int $fd,$data,$opcode = 1, bool $finish = true)
    {
        if (!$this->exist($fd)){
            return false;
        }

        $this->logger->debug("广播: 向用户 {$fd} 发送消息. 数据:{$data}");
        return $this->server->push($fd,$data,$opcode,$finish);
    }

    /**
     * 当前用户文件描述符
     * @return int
     */
    protected function getFd()
    {
        return $this->frame->fd;
    }

    /**
     * 向指定客户端发送消息
     * @param int $receiverFd
     * @param string $data
     * @param int $sender
     * @return bool
     */
    public function sendTo(int $receiverFd, string $data, int $sender = 0) : bool
    {
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;
        if (!$this->exist($receiverFd)){
            return false;
        }
        $this->logger->debug("广播: {$fromUser} 向用户{ $receiverFd} 发送消息. 数据: {$data}");
        return $this->server->push($receiverFd,$data,1,true);
    }

    /**
     * 向所有人发送消息
     * @param string $data
     * @param int $sender
     * @param int $pageSize
     * @return int
     */
    public function sendToAll(string $data, int $sender = 0,$pageSize = 50) : int
    {
        $count = 0;
        $connList = $this->getConectionList($pageSize);
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;
        $this->logger->debug("广播: {$fromUser} 向所有用户发送消息. 消息: {$data}");
        if ($connList){
            foreach ($connList as $fd){
                if (!$this->exist($fd)){
                    continue;
                }

                $info = $this->getClientInfo($fd);
                if (isset($info['websocket_status']) && $info['websocket_status'] > 0){
                    $count++;
                    $this->push($fd,$data);
                }
            }
        }
        return $count;
    }

    /**
     * 发送消息给指定用户
     * @param string $data
     * @param array $receivers
     * @param array $excluded
     * @param int $sender
     * @return int
     */
    public function sendToSome(string $data, array $receivers = [], array $excluded = [], int $sender = 0): int
    {
        $count = 0;
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;
        $receivers = array_diff($receivers, $excluded);
        if ($receivers) {
            $this->logger->debug("广播: {$fromUser} 给某个指定用户发送消息. 数据: {$data}");
            foreach ($receivers as $fd) {
                if (!$this->exist($fd)) {
                    continue;
                }
                $info = $this->getClientInfo($fd);
                if (isset($info['websocket_status']) && $info['websocket_status'] > 0) {
                    $count++;
                    $this->push($fd, $data);
                }
            }
        }
        return $count;
    }

    /**
     * 获取 fd 详情
     * @param int $fd
     * @return array
     */
    public function getClientInfo(int $fd):array
    {
        return $this->server->getClientInfo($fd)?:[];
    }

    /**
     * 获取所有的客户端连接
     * @param int $pageSize
     * @return array
     */
    public function getConnectionList(int $pageSize = 50):array
    {
        $startFd = 0;
        $list = [];
        while (true){
            $connList = $this->server->connection_list($startFd,$pageSize);
            if ($connList === false || count($connList) === 0){
                break;
            }
            $startFd = end($connList);
            $list = array_merge($list,$connList);
        }
        return $list;
    }
}