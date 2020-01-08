<?php


namespace App\Service;


class OnlineUser
{
    const ONLINE_USER_KEY = 'online-users';

    /**
     * 设置一个用户
     * @param int $fd
     * @param $data
     * @return bool|int
     */
    public static function setUser(int $fd, $data)
    {
        return redis()->hSet(self::ONLINE_USER_KEY, (string)$fd, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 获取所有用户
     * @param int $fd
     * @param $data
     * @return array
     */
    public static function getAllUser()
    {
        return redis()->hGetAll(self::ONLINE_USER_KEY);
    }

    /**
     * 获取一个用户
     * @param int $fd
     * @return array
     */
    public static function getUser(int $fd)
    {
        return redis()->hGet(self::ONLINE_USER_KEY, (string)$fd);
    }

    /**
     * 删除一个用户
     * @param int $fd
     * @return bool|int
     */
    public static function delUser(int $fd)
    {
        return redis()->hDel(self::ONLINE_USER_KEY, (string)$fd);
    }
}