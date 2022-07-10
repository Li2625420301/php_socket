<?php
namespace Te;

use Te\Event\Event;

class UdpConnection
{
    public $_sockfd;
    public $_clientIp; //包含端口号
    public $_server;
    public $_readBufferSize = 1024; //1k数据

    public $_recvBufferSize = 1024 * 1000 * 10; // 100kb 表示当前的连接接收缓冲区的大小
    public $_recvLen = 0;                 //表示当前连接目前接收到的字节数大小
    public $_recvBufferFull = 0;          //表示当前连接接收的字节数是否超出缓冲区
    public $_recvBuffer = '';

    public $_sendLen = 0;       
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public $_heartTime = 0;
    const HEART_TIME = 10;

    const STATUS_CLOSED = 10;
    const STATUS_CONNECTED = 11;
    public $_status;

    /**
     * 连接socket
     */
    public function __construct($_sockfd, $len, $buf, $unixClientFile)
    {
        $this->_sockfd = $_sockfd;
        $this->_clientIp = $unixClientFile;
        $this->_recvBuffer .= $buf;
        $this->_recvLen += $len;
        
        $this->handleMessage();
    }


    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    /**
     * 关闭
     */
    public function Close()
    {
        //客户端关闭移除读写事件
        
    }


    /**
     * 发送信息
     */
    public function handleMessage()
    {
        /** @var Server $server */
        while ($this->_recvLen) {
            $bin = unpack("Nlength", $this->_recvBuffer);
            $length = $bin['length'];
            $oneMsg = substr($this->_recvBuffer, 0, $length);
            $this->_recvBuffer = substr($this->_recvBuffer, $length);
            $this->_recvLen -= $length;
            if ($oneMsg) {
                $data = substr($oneMsg, 4);

                // 反序列化闭包
                $wrapper = unserialize($data);
                $closure = $wrapper->getClosure();
                $closure($this);
            }
        }
        
    }

    public function send($data)
    {

    }

}