<?php
namespace Te;

use Te\Event\Event;
use Te\Protocols\Websocket;

class TcpConnection
{
    public $_sockfd;
    public $_clientIp; //包含端口号
    public $_server;
    public $_readBufferSize = 65535; //1k数据

    public $_recvBufferSize = 1024 * 1000 * 100; // 100kb 表示当前的连接接收缓冲区的大小
    public $_recvLen = 0;                 //表示当前连接目前接收到的字节数大小
    public $_recvBufferFull = 0;          //表示当前连接接收的字节数是否超出缓冲区
    public $_recvBuffer = '';

    public $_sendLen = 0;       
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000 * 100;
    public $_sendBufferFull = 0;

    public $_heartTime = 0;
    const HEART_TIME = 10;

    const STATUS_CLOSED = 10;
    const STATUS_CONNECTED = 11;
    public $_status;


    /**
     * 判断是否链接有效
     */
    public function isConnected()
    {
        return $this->_status == self::STATUS_CONNECTED && is_resource($this->_sockfd);
    }

    // 心跳
    public function resetHeartTime()
    {
        $this->_heartTime = time();
    }

    // 检测心跳
    public function checkHeartTime()
    {
        $now = time();
        if ($now - $this->_heartTime >= self::HEART_TIME) {
            // fprintf(STDOUT, "心跳时间已经超出:%d\n", $now-$this->_heartTime);
            /** @var Server $server */
            $this->_server->echoLog("心跳时间已经超出:%d", $now-$this->_heartTime);
            return true;
        }
        return false;
    }

    /**
     * 连接socket
     */
    public function __construct($_sockfd, $_clientIp, $_server)
    {
        $this->_sockfd = $_sockfd;
        stream_set_blocking($this->_sockfd, 0); //设置非阻塞I/O
        stream_set_read_buffer($this->_sockfd, 0);//设置读缓冲区大小为0，你的读操作很快就返回
        stream_set_write_buffer($this->_sockfd, 0);//设置写缓冲区大小为0，你的写操作很快就返回
        $this->_clientIp = $_clientIp;
        $this->_server = $_server;
        $this->_heartTime = time();
        $this->_status = self::STATUS_CONNECTED;

        Server::$_eventLoop->add($_sockfd, Event::EV_READ, [$this, "recv4socket"]);
        //客户端连接之后，不要立马发送数据，否则会立马发送数据，耗费服务器资源
        // Server::$_eventLoop->add($_sockfd, Event::EV_WRITE, [$this, "write2socket"]);
    }

    public function socketFd()
    {
        return $this->_sockfd;
    }

    /**
     * 读事件
     */
    public function recv4socket()
    {
        // 还可以用stream_socket_recvfrom函数
        /** @var Server $server */
        $server = $this->_server;
        if ($this->_recvLen < $this->_recvBufferSize) {
            $data = fread($this->_sockfd, $this->_readBufferSize);
            
            // 1 正常接收数据，接收的字节数肯定大于0
            // 2 对端关闭，接收的字节数为0
            // 3 socket错误
            if ($data === '' || $data === false) {
                if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                    $this->Close();
                }
            } else {
                // 把接收到的数据放到缓冲区
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);
                // echo $this->_recvLen.PHP_EOL;
                $server->onRecv();
            }
        } else {
            $this->_recvBufferFull++;
            $server->runEventCallBack("receiveBufferFull", [$this]);
        }
        if ($this->_recvLen > 0) {
            // Stream字节流协议
            // 封包和拆包的条件：必须有相应的字段来表示这条消息的完整长度
            $this->handleMessage();
        }
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    /**
     * 写事件
     */
    // public function write2socket($data)
    public function write2socket()
    {
        // $len = strlen($data);
        // //还可以用stream_socket_sendto函数
        // $writelen = fwrite($this->_sockfd, $data, $len);
        // fprintf(STDOUT, "我写了:%d字节\n", $len);

        /** @var Server $server */
        // $server = $this->_server;
        // $bin = $server->_protocol->encode($data);        
        // $writelen = fwrite($this->_sockfd, $bin[1], $bin[0]);
        // fprintf(STDOUT, "我写了:%d字节\n", $writelen);

        if ($this->needWrite()) {
            set_error_handler(function () {});
            $writelen = fwrite($this->_sockfd, $this->_sendBuffer, $this->_sendLen);
            restore_error_handler();
            if ($writelen == $this->_sendLen) {
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                Server::$_eventLoop->del($this->_sockfd, Event::EV_WRITE);

                /** @var Websocket $protocol */
                $protocol = $this->_server->_protocol;
                if ($protocol instanceof Websocket) {
                    if ($protocol->_websocket_handshake_status == Websocket::WEBSOCKET_RUNNING_STATUS) {
                        $this->_server->runEventCallBack("open", [$this]);
                    }
                } else {
                    $this->Close();
                }
                // return true;
            } else if ($writelen > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $writelen);
                $this->_sendLen -= $writelen;
            } else {
                if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                    $this->Close();
                }
            }
        }
    }

    /**
     * 关闭
     */
    public function Close()
    {
        /** @var Server $server */
        $this->_server->echoLog("移除<socket:%d>连接", (int)posix_getpid());

        //客户端关闭移除读写事件
        Server::$_eventLoop->del($this->_sockfd, Event::EV_READ);
        Server::$_eventLoop->del($this->_sockfd, Event::EV_WRITE);

        if (is_resource($this->_sockfd)) {
            fclose($this->_sockfd);
        }
        /** @var Server $server */
        $server = $this->_server;
        $server->runEventCallBack("close", [$this]);
        $server->removeClient($this->_sockfd);
        $this->_status = self::STATUS_CLOSED;
        $this->_sockfd = NULL;
        $this->_sendLen = 0;
        $this->_sendBuffer = '';
        $this->_sendBufferFull = 0;
        $this->_sendBufferSize = 0;

        $this->_recvLen = 0;
        $this->_recvBuffer = '';
        $this->_recvBufferFull = 0;
        $this->_recvBufferSize = 0;
    }

    /**
     * 发送信息
     */
    public function handleMessage()
    {
        /** @var Server $server */
        $server = $this->_server;
        if (is_object($server->_protocol) && $server->_protocol != null) {
            while ($server->_protocol->Len($this->_recvBuffer)) {
                $msgLen = $server->_protocol->msgLen($this->_recvBuffer);
                //截取一条消息
                $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
                //剩余的数据可能有多条
                $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
                $this->_recvLen -= $msgLen;
                $this->_recvBufferFull --;
                $server->onMsg();
                $this->resetHeartTime();
                $message = $server->_protocol->decode($oneMsg);
                // $server->runEventCallBack("receive", [$message, $this]);
                $this->runEventCallBack($message);
                
            }
        } else {
            // $server->runEventCallBack("receive", [$this->_recvBuffer, $this]);
            $this->runEventCallBack($this->_recvBuffer);
            $this->_recvBuffer = '';
            $this->_recvLen = 0;
            $this->_recvBufferFull = 0;
            $server->onMsg();
            $this->resetHeartTime();
        }
        
    }

    public function send($data = '')
    {
        if (!$this->isConnected()) {
            $this->Close();
            return false;
        }
        $len = strlen($data);
        /** @var Server $server */
        $server = $this->_server;
        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            if (is_object($server->_protocol) && $server->_protocol != null) {
                $bin = $server->_protocol->encode($data);
                $this->_sendBuffer .= $bin[1];
                $this->_sendLen += $bin[0];
            } else {
                $this->_sendBuffer .= $data;
                $this->_sendLen += $len;
            }
            
            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
        }

        //fwrite 在发送数据会存在以下两种情况，1只发送一半 2完整的发送 3对端关闭
        set_error_handler(function () {});
        $writeLen = fwrite($this->_sockfd, $this->_sendBuffer, $this->_sendLen);
        restore_error_handler();
        if ($writeLen == $this->_sendLen) { //完整的发送
            $this->_sendBuffer = '';
            $this->_sendLen = 0;
            $this->_sendBufferFull = 0;
            return true;

        } else if ($writeLen > 0) { //发送一半
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;
            // $this->_recvBufferFull --;
            $this->_sendBufferFull --;
            // echo "我只发送了数据:".$writeLen."bytes\n";
            Server::$_eventLoop->add($this->_sockfd, Event::EV_WRITE, [$this, "write2socket"]);
            return true;
        } else { //对端关闭
            if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                $this->Close();
            }
            // $this->Close();
        }
        return false;
    }


    public function runEventCallBack($msg = '')
    {
        /** @var Server $server */
        $server = $this->_server;
        switch ($server->_usingProtocol) {
            case "tcp":
            case "text":
            case "stream":
                $server->runEventCallBack("receive", [$msg, $this]);
                break;

            case "http":
                $request  = $this->createRequest();
                $response = new Response($this);
                if ($request->_request['method'] == "OPTIONS") {
                    $response->sendMethods();
                } else {
                    $server->runEventCallBack("request", [$request, $response]);
                }
                break;
            case "ws":
                /** @var Websocket $protocol */
                $protocol = $server->_protocol;
                if ($protocol->_websocket_handshake_status == Websocket::WEBSOCKET_START_STATUS) {
                    if ($this->send()) {
                        //握手成功
                        if ($protocol->_websocket_handshake_status == Websocket::WEBSOCKET_RUNNING_STATUS) {
                            $server->runEventCallBack("open", [$this]);
                        } else {
                            $this->Close();
                        }
                    }
                } else if ($protocol->_websocket_handshake_status == Websocket::WEBSOCKET_RUNNING_STATUS) {
                    $server->runEventCallBack("message", [$msg, $this]);
                } else {
                    // $server->runEventCallBack("close", [$this]);
                    $this->Close();
                }
                break;
            case "mqtt":

                break;
            case "redis":

                break;
        }
    }

    public function createRequest()
    {
        $request = new Request();
        $request->_get      = $_GET;
        $request->_post     = $_POST;
        $request->_request  = $_REQUEST;

        return $request;
    }
}