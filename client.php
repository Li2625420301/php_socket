<?php
use Te\Client;

require_once "vendor/autoload.php";

$clientNum = $argv['1']; //获取命令行参数
$sendMessageNum = $argv['2']; //发送信息条数
$clients = [];
$startTime = time(); //开始时间
ini_set("memory_limit", "2048M"); //设置内存

for ($i=0; $i<$clientNum; $i++) {
    $clients[] = $client = new Client("tcp://127.0.0.1:12345");
    // $clients[] = $client = new Client("tcp://172.16.113.2:12345");

    $client->on("connect", function(\Te\Client $client) {
        $client->write2socket("hello");
        fprintf(STDOUT, "socket<%d> connect success\n", (int)$client->socketFd());
    });
    
    $client->on("recvive", function(\Te\Client $client, $msg) {
        // fprintf(STDOUT, "recv from server:%s\n", $msg);
        // $client->write2socket("i am client 客户端");
    });

    $client->on("recviveBufferFull", function(\Te\Client $client, $msg) {
        fprintf(STDOUT, "发送缓冲区已满\n");
        // $client->write2socket("i am client 客户端");
    });
    
    $client->on("close", function(\Te\Client $client) {
        fprintf(STDOUT, "服务器断开我的连接了\n");
    });
    
    $client->on("error", function(\Te\Client $client, $errno, $errstr) {
        fprintf(STDOUT, "errno:%d,errstr:%s\n", $errno, $errstr);
    });
    
    $client->Start();
}
// $client = new Client("tcp://127.0.0.1:12345");


// 多进程执行 ------
// $pid = pcntl_fork();
// if ($pid == 0) {
//     while (1) {
//         for ($i=0; $i<$clientNum; $i++) {
//             /** @var Client $client */
//             $client = $clients[$i];
//             // $client->write2socket("holle,i am client");
//             $client->send("holle,i am client");
//         }
//     }
//     exit(0);
// }

// while (1) {
//     for ($i=0; $i<$clientNum; $i++) {
//         $client = $clients[$i];
//         /** @var Client $client */
//         if (!$client->eventLoop()) {
//             break;
//         }
//     }
// }
// 多进程执行 ------

// 单进程执行
while (1) {
    $now = time();
    $diff = $now - $startTime;

    // print_r($now."--------------1----------------".PHP_EOL);
    // print_r($startTime."--------------2----------------".PHP_EOL);
    // print_r($diff."--------------3----------------".PHP_EOL);

    $startTime = $now;
    if ($diff >= 1) {
        $sendNum = 0;
        $sendMsgNum = 0;

        foreach ($clients as $client) {
            /** @var Client $client */
            $sendNum += $client->_sendNum;
            $sendMsgNum += $client->_sendMsgNum;
        }   

        fprintf(STDOUT, "time:<%s>--<clientNum:%d>--<sendNum:%d>--<msgNum:%d>\r\n", $diff, $clientNum, $sendNum, $sendMsgNum*$sendMessageNum);

        foreach ($clients as $client) {
            $client->_sendNum = 0;
            $client->_sendMsgNum = 0;
        } 
    } 

    for ($i=0; $i<$clientNum; $i++) {
        // print_r($i.PHP_EOL);

        $client = $clients[$i];

        //一直发
        /** @var Client $client */
        for ($j=0; $j<$sendMessageNum; $j++) {
            $client->send("hello,i am client".time());
        }
       
        //一直等待读事件发生
        // if (!$client->eventLoop()) {
        //     break;
        // }
        if (!$client->loop()) {
            break;
        }

        // sleep(1);
    }
    sleep(1);
}