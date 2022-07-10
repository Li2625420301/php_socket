<?php
namespace Te;

use Te\Event\Epoll;
use Te\Event\Event;
use Te\Event\Select;
use Te\Protocols\Stream;

class Client
{
    public $_mainSocket;
    public $_events = [];
    
    public $_readBufferSize = 102400; //1k数据
    public $_recvBufferSize = 1024 * 100; // 100k 表示当前的连接接收缓冲区的大小
    public $_recvLen = 0;                 //表示当前连接目前接收到的字节数大小
    public $_recvBuffer = ''; //它是一个缓冲区，可以接受多条消息[数据包]

    public $_protocol;//应用层的协议
    public $_local_socket;

    public $_sendLen = 0;       
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public $_sendNum = 0;
    public $_sendMsgNum = 0;

    const STATUS_CLOSED = 10;
    const STATUS_CONNECTED = 11;
    public $_status;

    static public $_eventLoop;

    public function __construct($local_socket)
    {
        // $this->_mainSocket = stream_socket_client($local_socket, $errno, $errstr);
        // $this->_protocol = new Stream();
        // if (is_resource($this->_mainSocket)) {
        //     $this->runEventCallBack("connect", [$this]);
        // } else {
        //     $this->runEventCallBack("error", [$this, $errno, $errstr]);
        //     exit(0);
        // }

        $this->_local_socket = $local_socket;
        $this->_protocol = new Stream();
        // static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用
        if (PHP_OS == 'Linux') {
            static::$_eventLoop = new Epoll(); //linux系统调用
        } else {
            static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用
        }
    }

    public function onSendWrite()
    {
        ++$this->_sendNum;
    }

    public function onSendMsg()
    {
        ++$this->_sendMsgNum;
    }

    public function socketFd()
    {
        return $this->_mainSocket;
    }

    /**
     * 回调存储
     */
    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    /**
     * 回调函数
     */
    public function runEventCallBack($eventName, $args=[])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        } else {
            fprintf(STDOUT, "not found %s event call\n", $eventName);
        }
    }

    /**
     * 读事件
     */
    public function recv4socket()
    {
        if ($this->isConnected()) {
            $data = fread($this->_mainSocket, $this->_readBufferSize);
            if ($data === '' || $data === false) {
                if (feof($this->_mainSocket) || !is_resource($this->_mainSocket)) {
                    $this->Close();
                }
            } else {
                // 把接收到的数据放到缓冲区
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);
            }
            if ($this->_recvLen > 0) {
                $this->handleMessage();
            }
        }
    }

    public function Close()
    {
        fclose($this->_mainSocket);
        $this->runEventCallBack("close", [$this]);
        $this->_status = self::STATUS_CLOSED;
        $this->_mainSocket = NULL;
    }

    public function eventLoop()
    {
        // while (1) {
        if (is_resource($this->_mainSocket)) {
            $readFds = [$this->_mainSocket];
            // if ($this->needWrite()) {
            //     $writeFds = [$this->_mainSocket];
            // } else {
            //     $writeFds = [];
            // }
            $writeFds = [$this->_mainSocket];
            $exptFds = [$this->_mainSocket];

            $ret = stream_select($readFds, $writeFds, $exptFds, NULL, NULL);
            if ($ret <= 0 || $ret === false) {
                // break;
                return false;
            }

            if ($readFds) {
                $this->recv4socket();
            }

            if ($writeFds) {
                $this->write2socket();
            }

            return true;
        } else {
            return false;
        }
        // }
    }

    /**
     * 判断是否链接有效
     */
    public function isConnected()
    {
        return $this->_status == self::STATUS_CONNECTED && is_resource($this->_mainSocket);
    }

    /**
     * 写事件
     */
    public function write2socket()
    {
        if ($this->needWrite() && $this->isConnected()) {
            $writelen = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);
            $this->onSendWrite();
            if ($writelen == $this->_sendLen) {
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                static::$_eventLoop->del($this->_mainSocket, Event::EV_WRITE);
                return true;
            } else if ($writelen > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $writelen);
                $this->_sendLen -= $writelen;
            } else {
                if (!is_resource($this->_mainSocket) || feof($this->_mainSocket)) {
                    $this->Close();
                }
            }
        }
    }

    public function send($data)
    {
        $len = strlen($data);
        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            $bin = $this->_protocol->encode($data);
            $this->_sendBuffer .= $bin[1];
            $this->_sendLen += $bin[0];
            
            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
            $this->onSendMsg();
        } else {
            $this->runEventCallBack("recviveBufferFull", [$this]);
        }

        //fwrite 在发送数据会存在以下两种情况，1只发送一半 2完整的发送 3对端关闭
        set_error_handler(function () {});
        $writeLen = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);
        restore_error_handler();
        if ($writeLen == $this->_sendLen) { //完整的发送
            $this->_sendBuffer = '';
            $this->_sendLen = 0;
            $this->_sendBufferFull = 0;
            $this->onSendWrite();
            return true;

        } else if ($writeLen > 0) { //发送一半
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;
            $this->_sendBufferFull --;

            static::$_eventLoop->add($this->_mainSocket, Event::EV_WRITE, [$this, "write2socket"]);

        }  else { //对端关闭
            if (!is_resource($this->_mainSocket) || feof($this->_mainSocket)) {
                $this->Close();
            }
        }
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function Start()
    {
        $this->_mainSocket = stream_socket_client($this->_local_socket, $errno, $errstr);
        if (is_resource($this->_mainSocket)) {
            stream_set_blocking($this->_mainSocket, 0); //设置非阻塞I/O
            stream_set_read_buffer($this->_mainSocket, 0);//设置读缓冲区大小为0，你的读操作很快就返回
            stream_set_write_buffer($this->_mainSocket, 0);//设置写缓冲区大小为0，你的写操作很快就返回
            $this->runEventCallBack("connect", [$this]);
            // $this->eventLoop();
            $this->_status = self::STATUS_CONNECTED;
            static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, "recv4socket"]);//客户端接收数据
            $this->loop();
        } else {
            $this->runEventCallBack("error", [$errno, $errstr]);
            exit(0);
        }
    }

    /**
     * 发送信息
     */
    public function handleMessage()
    {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);
            //截取一条消息
            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
            //剩余的数据可能有多条
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
            $this->_recvLen -= $msgLen;
            $message = $this->_protocol->decode($oneMsg);

            $this->runEventCallBack("recvive", [$message]);
        }
    }

    public function loop()
    {
        // return static::$_eventLoop->loop1();
        return static::$_eventLoop->loop();
    }
}