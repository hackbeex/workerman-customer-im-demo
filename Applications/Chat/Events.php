<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */

use GatewayWorker\Lib\Gateway;
use Workerman\MySQL\Connection;

class Events
{
    /** @var Connection */
    public static $db = null;

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        if (getenv("CHAT_LOG_TYPE")) {
            self::$db = new Connection('host', 'port', 'user', 'password', 'db_name');
        }
    }

    /**
     * 有消息时
     * @param int $client_id
     * @param mixed $message
     */
    public static function onMessage($client_id, $message)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";

        // 客户端传递的是json数据
        if (!$msg = json_decode($message, true)) {
            return;
        }

        $service_list_key = 'service_list';
        $client_list_key = 'client_list';

        // 根据类型执行不同的业务
        switch ($msg['type']) {
            case 'pong':
                return;

            case 'service_login':
                $client_name = htmlspecialchars($msg['client_name']);
                $_SESSION['client_name'] = $client_name;

                Gateway::joinGroup($client_id, $service_list_key);

                $new_msg = [
                    'type' => 'service_login',
                    'client_id' => $client_id,
                    'client_name' => $client_name,
                    'time' => date('Y-m-d H:i:s'),
                ];
                Gateway::sendToGroup($client_list_key, json_encode($new_msg));

                $client_list = Gateway::getClientSessionsByGroup($client_list_key);
                foreach ($client_list as $tmp_client_id => $item) {
                    $client_list[$tmp_client_id] = $item['client_name'];
                }

                $new_msg['client_list'] = $client_list;
                Gateway::sendToCurrentClient(json_encode($new_msg));
                return;

            case 'client_login':
                $client_name = htmlspecialchars($msg['client_name']);
                $_SESSION['client_name'] = $client_name;

                Gateway::joinGroup($client_id, $client_list_key);

                $service_list = Gateway::getClientSessionsByGroup($service_list_key);
                foreach ($service_list as $tmp_id => $item) {
                    $service_list[$tmp_id] = $item['client_name'];
                }

                if (!count($service_list)) {
                    $new_msg = [
                        'type' => 'error',
                        'time' => date('Y-m-d H:i:s'),
                        'msg' => '现在还没有客服在线'
                    ];
                    Gateway::sendToCurrentClient(json_encode($new_msg));
                    return;
                }

                // 目前默认第一个客服为其服务
                $service = reset($service_list);
                $client_id = key($service_list);

                $new_msg = [
                    'type' => 'client_login',
                    'client_id' => $client_id,
                    'client_name' => $service['client_name'],
                    'time' => date('Y-m-d H:i:s'),
                ];
                Gateway::sendToCurrentClient(json_encode($new_msg));
                return;

            case 'say':
                $client_name = $_SESSION['client_name'];
                $content = nl2br(htmlspecialchars($msg['content']));

                $new_msg = [
                    'type' => 'say',
                    'from_client_id' => $client_id,
                    'from_client_name' => $client_name,
                    'to_client_id' => $msg['to_client_id'],
                    'content' => $content,
                    'time' => date('Y-m-d H:i:s'),
                ];
                Gateway::sendToClient($msg['to_client_id'], json_encode($new_msg));

                //$new_msg['content'] = nl2br(htmlspecialchars($msg['content']));
                //Gateway::sendToCurrentClient(json_encode($new_msg));

                //self::logChat($client_name, $msg['content']);
                return;
        }
    }

    /**
     * 当客户端断开连接时
     * @param integer $client_id 客户端id
     */
    public static function onClose($client_id)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";

        // 从房间的客户端列表中删除
        if (isset($_SESSION['room_id'])) {
            $room_id = $_SESSION['room_id'];
            $new_message = array('type' => 'logout', 'from_client_id' => $client_id, 'from_client_name' => $_SESSION['client_name'], 'time' => date('Y-m-d H:i:s'));
            Gateway::sendToGroup($room_id, json_encode($new_message));
        }
    }

    /**
     * 记录聊天内容
     * @param $client_name
     * @param $content
     */
    private static function logChat($client_name, $content)
    {
        $log = [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'name' => $client_name,
            'content' => $content,
            'time' => date('Y-m-d H:i:s')
        ];
        $chat_log_type = getenv("CHAT_LOG_TYPE");

        if ($chat_log_type == "file") {
            $log_dir = getenv("CHAT_LOG_DIR");
            if (!file_exists($log_dir)) {
                if (mkdir($log_dir, 777, true)) {
                    echo "成功创建聊天记录保存目录{$log_dir}\n";
                } else {
                    echo "聊天记录保存目录{$log_dir} 创建失败，请手动创建\n";
                }
            }

            $log_file = $log_dir . "chat" . date('Y-m-d') . ".log";
            file_put_contents($log_file, json_encode($log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

        } elseif ($chat_log_type == "mysql") {
            self::$db->insert('chat_logs')->cols($log)->query();
        }
    }
}
