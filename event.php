<?php
// 定时时间
$eventBase = new EventBase();

// 定时器事件
echo "start:".time()."\r\n";
$event = new Event($eventBase, -1, Event::TIMEOUT|Event::PERSIST, function($fd, $what, $arg) { //Event::PERSIST 持续运行
    // echo "时间到了\n";
    echo($fd).PHP_EOL;
    echo($what).PHP_EOL;
    echo($arg).PHP_EOL;
},['a'=>'b']);

$event->add(1); //传递参数是执行循环

$allEvents[] = $event;

$eventBase->loop();