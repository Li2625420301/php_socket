<?php
// libevent库在linux下它是自动选择epoll的I/O复用函数来实现的
// epoll 非阻塞方式

namespace Te\Event;

class Epoll implements Event
{
    public $_eventBase;
    public $_allEvents = [];    //i/o事件
    public $_signalEvents = []; //信号事件
    public $_timers = []; //定时事件
    static public $_timerId = 1; //定时器id

    public function __construct()
    {
        $this->_eventBase = new \EventBase();
    }
    public function add($fd, $flag, $func, $arg = [])
    {
        if ($flag) {
            // stream_set_blocking($fd, 0); //设置非阻塞

            switch ($flag) {
                case self::EV_READ:
                    //fd 必须设置为非阻塞方式，因为epoll内部是使用非阻塞的文件描述符把它添加到内核事件表
                    $event = new \Event($this->_eventBase, $fd, \Event::READ|\Event::PERSIST, $func, $arg);
                    if (!$event || !$event->add()) {
                        // print_r(error_get_last());
                        return false;
                    }
                    $this->_allEvents[(int)$fd][self::EV_READ] = $event;
                    // echo "read事件添加成功".PHP_EOL;
                    return true;
                break;

                case self::EV_WRITE:
                    $event = new \Event($this->_eventBase, $fd, \Event::WRITE|\Event::PERSIST, $func, $arg);
                    if (!$event || !$event->add()) {
                        return false;
                    }
                    $this->_allEvents[(int)$fd][self::EV_WRITE] = $event;
                    return true;
                break;

                case self::EV_SIGNAL:
                    // echo "signal事件添加成功".PHP_EOL;
                    $event = new \Event($this->_eventBase, $fd, \Event::SIGNAL, $func, $arg);
                    if (!$event || !$event->add()) {
                        return false;
                    }
                    $this->_signalEvents[(int)$fd] = $event;
                    return true;
                break;

                case self::EV_TIME:      //定时事件
                case self::EV_TIME_ONCE: //定时事件
                    $timerId = static::$_timerId;
                    $param = [$func, $flag, $timerId, $arg];
                    $event = new \Event($this->_eventBase, -1, \Event::TIMEOUT|\Event::PERSIST, [$this, "timerCallBack"], $param);
                    if (!$event || !$event->add($fd)) {
                        return false;
                    }
                    $this->_timers[$timerId][$flag] = $event;
                    ++ static::$_timerId;
                    return $timerId;
                break;
            }
        }
    }

    public function timerCallBack($fd, $what, $arg)
    {
        // $param = [$func, $flag, $timerId, $arg];
        $func = $arg[0];
        $flag = $arg[1];
        $timerId = $arg[2];
        $userArg = $arg[3];

        if ($flag == Event::EV_TIME_ONCE) {
            $event = $this->_timers[$timerId][$flag];
            $event->del();
            unset($this->_timers[$timerId][$flag]);
        }
        // echo "10".PHP_EOL;
        call_user_func_array($func, [$timerId, $userArg]);

    }

    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
                if (isset($this->_allEvents[(int)$fd][self::EV_READ])) {
                    $event = $this->_allEvents[(int)$fd][self::EV_READ];
                    if ($event->del()) {
                        // fprintf(STDOUT, "读事件移除成功\n");
                    }
                    unset($this->_allEvents[(int)$fd][self::EV_READ]);
                }
                if (empty($this->_allEvents[(int)$fd])) {
                    unset($this->_allEvents[(int)$fd]);
                }
                return true;
            break;

            case self::EV_WRITE:
                // if ($this->_allEvents[(int)$fd][self::EV_WRITE]) {
                if (isset($this->_allEvents[(int)$fd][self::EV_WRITE])) {
                    $event = $this->_allEvents[(int)$fd][self::EV_WRITE];
                    if ($event->del()) {
                        // fprintf(STDOUT, "写事件移除成功\n");
                    }
                    unset($this->_allEvents[(int)$fd][self::EV_WRITE]);
                }
                
                if (empty($this->_allEvents[(int)$fd])) {
                    unset($this->_allEvents[(int)$fd]);
                }
                return true;
            break;

            case self::EV_SIGNAL:
                if (isset($this->_signalEvents[$fd])) {
                    if ($this->_signalEvents[$fd]->del()) {
                        // fprintf(STDOUT, "信号事件移除成功\n");
                        unset($this->_signalEvents[$fd]);
                    }
                } 
            break;

            case self::EV_TIME:      
            case self::EV_TIME_ONCE: 
                if (isset($this->_timers[$fd][$flag])) {
                    if ($this->_timers[$fd][$flag]->del()) {
                        // fprintf(STDOUT, "定时事件移除成功2\n");
                        unset($this->_timers[$fd][$flag]);
                    }
                } 
            break;
        }
    }

    public function loop()
    {
        $this->_eventBase->dispatch(); //while epoll_wait
    }

    public function clearTimer()
    {
        foreach ($this->_timers as $fd => $event) {
            if (current($event)->del()) {
                // echo "移除定时事件成功1\n";
            }
        }
        $this->_timers = [];
    }

    public function clearSignalevents()
    {
        foreach ($this->_signalEvents as $fd => $event) {
            if ($event->del()) {
                // echo "移除信号事件成功\n";
            }
        }
        $this->_signalEvents = [];
    }

    public function exitLoop()
    {
        return $this->_eventBase->stop();
    }
}