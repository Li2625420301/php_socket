<?php
// http并发测试

use App\Controllers\ControllerDispatcher;
use Te\Request;
use Te\Response;
use Te\Server;

require_once "vendor/autoload.php";
$server = new Server("http://0.0.0.0:12345"); //http协议

$server->setting([
    "workerNum" => 1,//创建进程数
    "daemon"    => false, //守护进程
    "taskNum"   => 1, //任务数
    "task"      => [  //任务unix文件
        'unix_socket_server_file' => '/te/sock/te_unix_socket_server.sock',
        'unix_socket_client_file' => '/te/sock/te_unix_socket_client.sock',
    ], 
]);

// http请求
$server->on("workerStart", function(\Te\Server $server) {
    global $routes;
    global $dispatcher;
    $routes     = require_once "app/routes/api.php";
    $dispatcher = new ControllerDispatcher();
});
// $server->on("connect", function(\Te\Server $server, \Te\TcpConnection $connection) {
//     $server->echoLog("有客户端连接了");
// });
$server->on("request", function(\Te\Server $server, \Te\Request $request, \Te\Response $response) {
    global $routes;
    global $dispatcher;
    
    /** @var ControllerDispatcher $dispatcher */
    $dispatcher->callAction($routes, $request, $response);
});
$server->on("close", function(\Te\Server $server, \Te\TcpConnection $connection) {
    $server->echoLog("客户端断开连接了");
});
$server->Start();


