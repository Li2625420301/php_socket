<?php

use App\Controllers\ControllerDispatcher;
use Te\Request;
use Te\Response;
use Te\Server;

require_once "vendor/autoload.php";
//tcp connect/receive/clone
//udp packet / clone
//stream/text

//http request
//ws open/message/clone
//mqtt connect/subscribe/unsubscribe/publish/clone
ini_set("memory_limit", "2048M"); //设置内存

// $server = new Server("tcp://0.0.0.0:12345");  //tcp协议
// $server = new Server("stream://0.0.0.0:12345");
// $server = new Server("text://0.0.0.0:12345"); //text协议
// $server = new Server("http://0.0.0.0:12345"); //http协议
$server = new Server("ws://0.0.0.0:12345"); //http协议

$server->setting([
    "workerNum" => 1,//创建进程数
    "daemon"    => false, //守护进程
    "taskNum"   => 1, //任务数
    "task"      => [  //任务unix文件
        'unix_socket_server_file' => '/te/sock/te_unix_socket_server.sock',
        'unix_socket_client_file' => '/te/sock/te_unix_socket_client.sock',
    ], 
]);

// tcp请求
/*
    $server->on("masterStart", function(\Te\Server $server) {
        $server->echoLog("master server start");
    });
    $server->on("masterShutDown", function(\Te\Server $server) {
        $server->echoLog("master server shutdown");
    });
    $server->on("workerStart", function(\Te\Server $server) {
        $server->echoLog("worker <pid:%d> start", posix_getpid());
    });
    $server->on("workerStop", function(\Te\Server $server) {
        $server->echoLog("worker <pid:%d> stop", posix_getpid());
    });
    $server->on("workerReload", function(\Te\Server $server) {
        $server->echoLog("worker <pid:%d> reload", posix_getpid());
    });
    $server->on("connect", function(\Te\Server $server, \Te\TcpConnection $connection) {
        $server->echoLog("<pid:%d>有客户端连接了", posix_getpid());
    });
    $server->on("receive", function(\Te\Server $server, $msg, \Te\TcpConnection $connection) {
        $server->echoLog("<pid:%d>recv from client<%d>:%s", posix_getpid(), (int)$connection->socketFd(), $msg);

        $server->task(function ($result) use ($server) {
            // sleep(10);
            $server->echoLog("异步任务执行完，时间到了");
        }); // 耗时任务可以投递到任务进程来做【一步】
        // $data = file_get_contents("strace.log");
        $connection->send("i am server".time());
    });
    $server->on("receiveBufferFull", function(\Te\Server $server, \Te\TcpConnection $connection) {
        $server->echoLog("接收缓冲区已满");
    });
    $server->on("close", function(\Te\Server $server, \Te\TcpConnection $connection) {
        $server->echoLog("<pid:%d>客户端断开连接了", posix_getpid());
    });
    $server->on("task", function(\Te\Server $server, \Te\UdpConnection $udpConnection, $msg) {
        $server->echoLog("task process <pid:%d>on task %s", posix_getpid(), $msg);
    });
*/


// http请求
/*
    $server->on("workerStart", function(\Te\Server $server) {
        // $server->echoLog("worker <pid:%d> start", posix_getpid());
        echo "worker start\r\n";
        global $routes;
        global $dispatcher;
        $routes     = require_once "app/routes/api.php";
        $dispatcher = new ControllerDispatcher();
    });
    $server->on("connect", function(\Te\Server $server, \Te\TcpConnection $connection) {
        $server->echoLog("有客户端连接了");
    });
    $server->on("request", function(\Te\Server $server, \Te\Request $request, \Te\Response $response) {
        // print_r($_GET);
        // print_r($request->_get);
        // print_r($_POST);
        // print_r($request->_post);
        // $response->write("hello word");

        // $response->sendFile("www/".$request->_request['uri']);
        // $response->chunked("hello, word", 1);
        // sleep(1);
        // $response->chunked("hello, word1", 0);
        // $response->end();

        global $routes;
        global $dispatcher;
        if (preg_match("/.html|.jpg|.png|.gif|.js|.css|.jpeg/", $request->_request['uri'])) {
            $file = "app/resources".$request->_request['uri'];
            $response->sendFile($file);
            return true;
        }
        // $response->header("Content-Type", "application/json");
        // $data = array_merge($_GET, $_POST);
        // $response->write(json_encode($data));
        // print_r(1231);
        $dispatcher->callAction($routes, $request, $response);
    });
*/

// websocket请求
$server->on("workerStart", function(\Te\Server $server) {
    // $server->echoLog("worker <pid:%d> start", posix_getpid());
    echo "worker start\r\n";
    global $routes;
    global $dispatcher;
    $routes     = require_once "app/routes/api.php";
    $dispatcher = new ControllerDispatcher();
});
$server->on("connect", function(\Te\Server $server, \Te\TcpConnection $connection) {
    $server->echoLog("有客户端连接了");
});
$server->on("open", function(\Te\Server $server, \Te\TcpConnection $connection) {
    
});
$server->on("message", function(\Te\Server $server, $frame, \Te\TcpConnection $connection) {
    
});
$server->on("close", function(\Te\Server $server, \Te\TcpConnection $connection) {
    $server->echoLog("客户端断开连接了");
});
$server->on("request", function(\Te\Server $server, \Te\Request $request, \Te\Response $response) {
    global $routes;
    global $dispatcher;
    if (preg_match("/.html|.jpg|.png|.gif|.js|.css|.jpeg/", $request->_request['uri'])) {
        $file = "app/resources".$request->_request['uri'];
        $response->sendFile($file);
        return true;
    }
    $dispatcher->callAction($routes, $request, $response);
});
$server->Start();


