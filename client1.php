<?php
use Te\Client;

require_once "vendor/autoload.php";

$clients = $client = new Client("tcp://127.0.0.1:12345");

$client->on("connect", function(\Te\Client $client) {
    $client->send("hello");
    fprintf(STDOUT, "socket<%d> connect success\n", (int)$client->socketFd());
});

$client->on("recvive", function(\Te\Client $client, $msg) {
    fprintf(STDOUT, "recv from server:%s\n", $msg);
    $client->send("i am client 客户端");
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
