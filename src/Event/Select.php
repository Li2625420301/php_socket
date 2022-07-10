<?php
// libevent库在linux下它是自动选择epoll的I/O复用函数来实现的
// epoll 非阻塞方式

namespace Te\Event;

class Select implements Event
{
    public $_eventBase;
    public $_allEvents = [];    //i/o事件
    public $_signalEvents = []; //信号事件
    public $_timers = []; //定时事件

    public $_readFds = []; //读事件
    public $_writeFds = []; //写事件
    public $_exptFds = []; //异常事件
    public $_timeout = 100000000; //超时时间 100秒钟
    static public $_timerId = 1; //定时器id
    public $_run = true;

    public function __construct()
    {
        // $this->_eventBase = new \EventBase();
    }
    public function add($fd, $flag, $func, $arg = [])
    {
        if ($flag) {
            // stream_set_blocking($fd, 0); //设置非阻塞

            switch ($flag) {
                case self::EV_READ:
                    $fdKey = (int)($fd);
                    $this->_readFds[$fdKey] = $fd;
                    $this->_allEvents[$fdKey][self::EV_READ] = [$func, [$fd, $func, $arg]];

                    return true;
                break;

                case self::EV_WRITE:
                    $fdKey = (int)($fd);
                    $this->_writeFds[$fdKey] = $fd;
                    $this->_allEvents[$fdKey][self::EV_WRITE] = [$func, [$fd, $func, $arg]];
                    return true;
                break;

                case self::EV_SIGNAL:
                    $param = [$func, $arg];
                    // pcntl_signal($fd, $func);
                    pcntl_signal($fd, [$this, "installSignalHandle"], false); //installSignalHandle方法传递的参数就是pcntl_signal的第一个参数$fd

                    $this->_signalEvents[$fd] = $param;
                    return true;
                break;
                
                case self::EV_TIME:      //定时事件
                case self::EV_TIME_ONCE: //定时事件
                    //$fd //现在是当作微秒
                    $timerId = static::$_timerId;
                    $runTime = microtime(true) + $fd;
                    $param = [$func, $runTime, $flag, $timerId, $fd, $arg];

                    // $this->_timers[$timerId][$flag] = $param;
                    $this->_timers[$timerId] = $param;
                    $selectTime = $fd * 1000000; //秒

                    if ($this->_timeout >= $selectTime) {
                        $this->_timeout = $selectTime;
                    }
                    // echo "创建定时事件成功\n";
                    ++ static::$_timerId;
                    return $timerId;
                break;
            }
        }
    }

    public function installSignalHandle($sigNum)
    {
        $callback = $this->_signalEvents[$sigNum];
        if (is_callable($callback[0])) {
            call_user_func_array($callback[0], [$sigNum]);
        }
    }

    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
                $fdKey = (int)$fd;
                unset($this->_allEvents[$fdKey][self::EV_READ]);
                unset($this->_readFds[$fdKey]);

                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }
                return true;
            break;

            case self::EV_WRITE:
                $fdKey = (int)$fd;
                unset($this->_allEvents[$fdKey][self::EV_WRITE]);
                unset($this->_writeFds[$fdKey]);

                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }
                return true;
            break;

            case self::EV_SIGNAL:
                if (isset($this->_timers[$fd])) {
                    unset($this->_timers[$fd]);
                    pcntl_signal($fd, SIG_IGN, false);
                } 

            break;

            case self::EV_TIME:      
            case self::EV_TIME_ONCE: 
                if (isset($this->_timers[$fd])) {
                    unset($this->_timers[$fd]);
                } 
            break;
        }
    }

    /**
     * select i/o 轮询
     */
    public function loop()
    {
        while ($this->_run) {
            pcntl_signal_dispatch(); //进程捕捉信号

            $reads = $this->_readFds;
            $writes = $this->_writeFds;
            $expts = $this->_exptFds;//发送紧急数据有用，URG tcp头部结构标志 FIN|RST|ACK|PSH|URG

            set_error_handler(function () {}); //隐藏错误
            $ret = stream_select($reads, $writes, $expts, 0, $this->_timeout); //非阻塞i/o，设置超时时间0秒，100微秒
            restore_error_handler();
            
            // if ($ret === false) {
            //     break;
            // }
            // select设置了非阻塞，隔一段时间没有监听到事件的产生就返回0,如果是中断信号产生时会返回false，但是socket并不是关闭了
            // select是可重入函数【中断系统】可中断系统调用
            if (!$ret) {
                continue;
            }

            if (!empty($this->_timers)) {
                $this->timerCallBack();
            }
            if ($reads) {
                foreach ($reads as $fd) {
                    $fdKey = (int)$fd;
                    if (isset($this->_allEvents[$fdKey][self::EV_READ])) {
                        $callback = $this->_allEvents[$fdKey][self::EV_READ];
                        call_user_func_array($callback[0], $callback[1]);
                    }
                }
            }

            if ($writes) {
                foreach ($writes as $fd) {
                    $fdKey = (int)$fd;
                    if (isset($this->_allEvents[$fdKey][self::EV_WRITE])) {
                        $callback = $this->_allEvents[$fdKey][self::EV_WRITE];
                        call_user_func_array($callback[0], $callback[1]);
                    }
                }
            }
        }
    }

    public function timerCallBack()
    {
        // $param = [$func, $runTime, $flag, $timerId, $arg];
        foreach ($this->_timers as $k => $timer) {
            $func    = $timer[0];
            $runTime = $timer[1]; //未来时间
            $flag    = $timer[2];
            $timerId = $timer[3];
            $fd      = $timer[4];
            $arg     = $timer[5];

            if ($runTime - microtime(true) <= 0) {
                if ($flag == Event::EV_TIME_ONCE) {
                    unset($this->_timers[$timerId]);
                } else {
                    $runTime = microtime(true) + $fd; //取得下一个时间点
                    $this->_timers[$k][1] = $runTime;
                }
                call_user_func_array($func, [$timerId, $arg]);
            }
        }   
    }

    public function loop1()
    {
        $reads = $this->_readFds;
        $writes = $this->_writeFds;
        $expts = $this->_exptFds;//发送紧急数据有用，URG tcp头部结构标志 FIN|RST|ACK|PSH|URG

        set_error_handler(function () {}); //隐藏错误
        // $ret = stream_select($reads, $writes, $expFds, 0, $this->_timeout); //非阻塞i/o，设置超时时间0秒，100微秒
        $ret = stream_select($reads, $writes, $expFds, 0, 0); //非阻塞i/o，设置超时时间0秒，100微秒
        restore_error_handler();
        
        if ($ret === false) {
            return false;
        }
        if ($reads) {
            foreach ($reads as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->_allEvents[$fdKey][self::EV_READ])) {
                    $callback = $this->_allEvents[$fdKey][self::EV_READ];
                    call_user_func_array($callback[0], $callback[1]);
                }
            }
        }
        if ($writes) {
            foreach ($writes as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->_allEvents[$fdKey][self::EV_WRITE])) {
                    $callback = $this->_allEvents[$fdKey][self::EV_WRITE];
                    call_user_func_array($callback[0], $callback[1]);
                }
            }
        }
        return true;
    }

    public function clearTimer()
    {
        $this->_timers = [];
    }

    public function clearSignalevents()
    {
        foreach ($this->_signalEvents as $fd => $arg) {
            pcntl_signal($fd, SIG_IGN, false);
        }
        $this->_signalEvents = [];
    }

    public function exitLoop()
    {
        $this->_run = false;
        $this->_readFds = [];
        $this->_writeFds = [];
        $this->_exptFds = [];
        $this->_allEvents = [];
        return true;
    }
}