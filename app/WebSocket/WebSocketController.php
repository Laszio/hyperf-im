<?php


namespace App\WebSocket;

use App\Service\OnlineUser;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Swoole\Http\Request;
use Swoole\WebSocket\Server as WebSocketServer;
use Psr\Container\ContainerInterface;
use Swoole\Websocket\Frame;
use App\utils\WebSocketAction;
use Swoole\Server;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function onMessage(WebSocketServer $server, Frame $frame): void
    {
        $this->parserAction($server, $frame);
    }

    public function onOpen(WebSocketServer $server, Request $request): void
    {
        if (isset($request->get['username']) && !empty($request->get['username'])) {
            $userName = $request->get['user_name'];
        } else {
            $random = rand(1000, 9999);
            $username = '神秘乘客' . $random;
        }

        // 插入在线用户列表并广播给所有用户
        $this->addOnlineUserAndBroadcastOtherUsers($server, $request, $username);
    }

    public function onClose(Server $server, int $fd, int $reactorId): void
    {
        $this->delOnlineUserAndBroadcastOtherUsers($server, $fd);
    }

    // 添加新用户并广播给其他用户
    protected function addOnlineUserAndBroadcastOtherUsers(WebSocketServer $server, Request $request, $username)
    {
        $avatar = [
          'http://img.qqzhi.com/uploads/2018-12-17/173849533.jpg',
          'http://img5.imgtn.bdimg.com/it/u=3547705403,341906334&fm=26&gp=0.jpg',
          'http://pic.17qq.com/img_qqtouxiang/75978077.jpeg',
          'http://tx.haiqq.com/uploads/allimg/170506/0S0095M7-6.jpg',
          'https://ss1.bdstatic.com/70cFv8Sh_Q1YnxGkpoWK1HF6hhy/it/u=3699333020,2000962123&fm=27&gp=0.jpg',
          'http://image.biaobaiju.com/uploads/20180830/19/1535628120-pcjIeVWdbl.jpg',
        ];
        $currentFd = $request->fd;
        $userInfo = [
            'fd' => $currentFd,
            'username' => $username,
            "avatar" => $avatar[array_rand($avatar)],
            'last_heartbeat' => time(),
        ];
        OnlineUser::setUser($currentFd, $userInfo);
        $data = json_encode([
            'fd' => $currentFd,
            'content' => $userInfo,
            'action' => WebSocketAction::USER_COME_ROOM,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->broadcastAllUserInfo($server, $currentFd, $data);
    }

    // 删除离线用户并广播给其他人
    protected function delOnlineUserAndBroadcastOtherUsers(Server $server, int $fd)
    {
        OnlineUser::delUser($fd);
        $data = json_encode([
            'fd' => $fd,
            'content' => '',
            'action' => WebSocketAction::USER_OUT_ROOM,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->broadcastAllUserInfo($server, $fd, $data);
    }

    // 向其他人广播信息
    protected function broadcastAllUserInfo(Server $server, $exceptFd, $data)
    {
        $users = OnlineUser::getAllUser();
        foreach ($users as $fd => $user) {
            if ($exceptFd == $fd) {
                continue;
            }
            // 当前客户端是否存在 不存在删除
            if (!$server->exist($fd)) {
                OnlineUser::delUser($fd);
                continue;
            }
            $this->logger->debug("广播: 向用户 {$fd} 发送消息. 数据: {$data}");
            $server->push($fd, $data);
        }
    }

    // 解析行为
    protected function parserAction(WebSocketServer $server, Frame $frame)
    {
        $data = $frame->data;
        if ($data === 'PING') {
            $server->push($frame->fd, 'PONG');
            return;
        }
        $payload = json_decode($data, true);
        $class = $payload['controller'] ?? 'index';
        $action = $payload['action'] ?? 'actionNotFound';
        $params = isset($payload['params']) ? (array)$payload['params'] : [];
        $controllerClass = "\\App\\WebSocket\\Controller\\" . ucfirst($class);
        try {
            if (!class_exists($controllerClass)) {
                $controllerClass = "\\App\\WebSocket\\Controller\\Index";
            }
            $ref = new \ReflectionClass($controllerClass);
            if (!$ref->hasMethod($action)) {
                $action = 'actionNotFound';
                $params = $payload;
            }
            $obj = new $controllerClass($server, $frame, $this->container);
            call_user_func_array([$obj, $action], $params);
        } catch (\ReflectionException $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}