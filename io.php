<?php
// I/O事件

// unix域套接字网络进程间通信
// UNIX无命名的套接字只能用于有血缘关系的进程间通信【父子、兄弟】全双工
// UNIX【本地域】是不经过网卡的
$sockfd = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0); //创建一对完全一样的网路套接字连接流 STREAM_SOCK_STREAM是tcp域名｜STREAM_SOCK_DGRAM是upd域
// $sockfd[0]; //读
// $sockfd[1]; //写
stream_set_blocking($sockfd[0], 0); //设置非阻塞
stream_set_blocking($sockfd[1], 0); //设置非阻塞

// epoll
$pid = pcntl_fork();
if ($pid == 0) { //子进程
    // while (1) {
    //     fwrite($sockfd[1], "hello");//写事件
    //     sleep(1);
    // }

    $eventBase = new EventBase();
    // I/O事件
    $event = new Event($eventBase, $sockfd[1], Event::WRITE|Event::PERSIST, function($fd, $what, $arg) {
        echo fwrite($fd, "china"); //写事件
        echo "\n";
    },['a'=>'b']);

    $event->add();

    $allEvents[] = $event;

    $eventBase->dispatch();
} else { //父进程

    $eventBase = new EventBase();
    // I/O事件
    $event = new Event($eventBase, $sockfd[0], Event::READ|Event::PERSIST, function($fd, $what, $arg) {
        echo fread($fd, 128); //读事件
        echo "\n";
    },['a'=>'b']);

    $event->add();

    $allEvents[] = $event;

    $eventBase->dispatch();
}
