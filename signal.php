<?php
// 中断信号
// event 扩展它的实现是使用了libevent2.x 库的源码实现的
// libevent 它封装了三种事件，I/O事件、定时事件、中断信号事件 [统称为事件源]
// 中断信号[多进程编程讲过]，信号编码，信号名称

// I/O事件其实就是指文件描述符上的事件【读写事件】，内核监听到事件发生之后，会通过文件描述符来通知应用

// 事件源【中断信号，定时，I/O】也称为句柄，I/O就是文件描述符，中断信号就是指信号，定时就是时间
// 事件多路分发器，它的实现是使用I/O复用函数来实现的，select epoll poll kqueue 它们能监听大量的文件描述符上的事件
// 事件处理器/具体的事件处理器，其实就是值事件回调函数
// 后面我们会分析libevent框架的工作原理【选看，需要c语言基础】

// 事件处理模式：reactor proactor 【同步模式，异步模式】。
// 同步模式：指的是监听的文件描述符上有就绪事件发生就返回
// 异步模式：指的是完成事件【读写完成的事件】libevent 它是同步模式

// 中断信号事件
$eventBase = new EventBase();
// echo "start:".time()."\r\n";
$event = new Event($eventBase, 2, Event::SIGNAL, function($fd, $what, $arg) {//2=>SIGINT
    echo "中断信号开始执行了\n";
    echo($fd).PHP_EOL;
    echo($what).PHP_EOL;
    print_r($arg).PHP_EOL;
},['a'=>'b']);

$event->add();

$allEvents[] = $event;

$eventBase->dispatch();//loop和dispatch一样   内部会执行循环
