<?php

namespace Te\Event;

interface Event
{
    const EV_READ  = 10; //读文件
    const EV_WRITE = 11; //写文件

    const EV_SIGNAL = 12;//信号事件

    const EV_TIME = 13; //定时事件持续执行
    const EV_TIME_ONCE = 14; //定时事件执行一次

    /**
     * 添加事件
     * fd   socket
     * flag 文件描述符
     * func 函数方法
     * arg  传递参数
     */
    // 监听socket 连接socket
    public function add($fd, $flag, $func, $arg);

    /**
     * 移除事件
     */
    public function del($fd, $flag);

    // 事件循环
    public function loop();

    // 清空定时器事件
    public function clearTimer();
    
    // 清空信号事件
    public function clearSignalevents();

    // 清空事件循环
    public function exitLoop();
    
}