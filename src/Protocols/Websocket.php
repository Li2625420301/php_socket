<?php

namespace Te\Protocols;

use Te\Response;
use Te\TcpConnection;
/**
// 数据帧
  0                   1                   2                   3
  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 +-+-+-+-+-------+-+-------------+-------------------------------+
 |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
 |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
 |N|V|V|V|       |S|             |   (if payload len==126/127)   |
 | |1|2|3|       |K|             |                               |
 +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
 |     Extended payload length continued, if payload len == 127  |
 + - - - - - - - - - - - - - - - +-------------------------------+
 |                               |Masking-key, if MASK set to 1  |
 +-------------------------------+-------------------------------+
 | Masking-key (continued)       |          Payload Data         |
 +-------------------------------- - - - - - - - - - - - - - - - +
 :                     Payload Data continued ...                :
 + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
 |                     Payload Data continued ...                |
 +---------------------------------------------------------------+

 */
class Websocket implements Protocol
{
    public $_http;
    public $_websocket_handshake_status;
    const WEBSOCKET_START_STATUS = 10;
    const WEBSOCKET_RUNNING_STATUS = 11;
    const WEBSOCKET_CLOSE_STATUS = 12;

    public $_fin;
    public $_opcode;
    public $_mask;
    public $_payload_len;
    public $_masKey = [];
    const OPCODE_TEXT = 0x01; //文本针
    const OPCODE_BINARY = 0x02; //二进制帧
    const OPCODE_CLOSED = 0x08; //连接关闭
    const OPCODE_PING = 0x09; //ping帧
    const OPCODE_PONG = 0x0A; //pong帧

    public $_headerLen;
    public $_dataLen;

    public function __construct()
    {
        $this->_http = new Http();
        $this->_websocket_handshake_status = self::WEBSOCKET_START_STATUS;
    }

    public function encode($data = '')
    {
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            $handshakeData = $this->handshake();
            if ($handshakeData) {
                $this->_websocket_handshake_status = self::WEBSOCKET_RUNNING_STATUS;
                return $this->_http->encode($handshakeData);
            } else {
                $this->_websocket_handshake_status = self::WEBSOCKET_CLOSE_STATUS;
                return $this->_http->encode($this->response400(""));
            }
            
        }
    }

    public function decode($data = '')
    {
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            return $this->_http->decode($data);
        } else {
            $this->test();
        }
    }

    public function test()
    {
        $text = sprintf("fin:%d,opcode:%d,mask:%d,datalen:%d\r\n", $this->_fin, $this->_opcode, $this->_mask, $this->_dataLen);
        fwrite(STDOUT, $text);
    }

    public function Len($data)
    {
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            return $this->_http->Len($data);
        } else {
            // 1Bytes | 1Bytes | 2Bytes/8Bytes | 4Bytes | nBytes
            // FIN|RSV1|RSV2|RSV3|OPCODE|MASK|
            if (strlen($data) < 2) {
                return false;
            }
            $this->_headerLen = 2;

            // $data 它的内存事连续的，是多个字节
            $fristByte = ord($data[0]); //二进制转10进制 = 129
            // $fristByte  = 1000 0001 &
            // 0x80[16进制] = 1000 0000 
            //               1000 0000 = 128    
            // fin的位置是第一个字节的第一位，所以要计算结果需要&0x80
            $this->_fin = ($fristByte&0x80) == 0x80 ? 1 : 0;   //1
            // $fristByte  = 1000 0001 &
            // 0x0F[16进制] = 0000 1111
            //               0000 0001
            // opcode的位置是第一个字节的后四位，所以要计算结果需要&0x0F
            $this->_opcode = ($fristByte&0x0F); //1
            if ($this->_opcode == self::OPCODE_CLOSED) {
                $this->_websocket_handshake_status = self::WEBSOCKET_CLOSE_STATUS;
                return false;
            }

            $secondByte = ord($data[1]);
            // _mask的位置是第二个字节的第一位，所以要计算后结果需要&0x80
            $this->_mask = ($secondByte&0x80) == 0x80 ? 1 : 0;
            if ($this->_mask == 0) {
                $this->_websocket_handshake_status = self::WEBSOCKET_CLOSE_STATUS;
                return false;
            }

            $this->_headerLen += 4;
            //它发送的数据长度小于或者等于125长度就这么多
            //如果超过125就用2个字节来表示数据长度
            //如果超过了2个字节的长度，就用8个字节来存储数据长度
            // 0x7F[16进制] = 0111 1111
            $this->_payload_len = $secondByte&0x7F;
            if ($this->_payload_len == 126) {
                $this->_headerLen += 2;
            } else if ($this->_payload_len == 127) {
                $this->_headerLen += 8;
            }
            if (strlen($data) < $this->_headerLen) {
                return false;
            }
            if ($this->_payload_len == 126) { //2bytes
                // 65536 65k的数据
                $len  = 0;
                $len |= ord($data[2] << 8);
                $len |= ord($data[3] << 0);
                $this->_dataLen = $len;
            } else if ($this->_payload_len == 127) { //8bytes
                $len  = 0;
                $len |= ord($data[2] << 56);
                $len |= ord($data[3] << 48);
                $len |= ord($data[4] << 40);
                $len |= ord($data[5] << 32);
                $len |= ord($data[6] << 24);
                $len |= ord($data[7] << 16);
                $len |= ord($data[8] << 8);
                $len |= ord($data[9] << 0);
                $this->_dataLen = $len;
            } else {
                $this->_dataLen = $this->_payload_len;
            }

            $this->_masKey[0] = $data[$this->_headerLen-4];
            $this->_masKey[1] = $data[$this->_headerLen-3];
            $this->_masKey[2] = $data[$this->_headerLen-2];
            $this->_masKey[3] = $data[$this->_headerLen-1];

            // 小于的话，表示后面的数据载荷还没有接受完，要继续接受
            if (strlen($data) < $this->_headerLen + $this->_dataLen) {
                return false;
            }
            return true;
        }
    }

    public function msgLen($data = '')
    {
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            return $this->_http->msgLen($data);
        } else {
            return $this->_headerLen + $this->_dataLen;
        }
    }

    public function handshake()
    {
        if (isset($_REQUEST['Connection']) && $_REQUEST['Connection'] == "Upgrade"
            && isset($_REQUEST['Upgrade']) && $_REQUEST['Upgrade'] == "websocket"
        ) {
            $key = $_REQUEST['Sec_WebSocket_Key'];
            if (isset($key)) {
                $acceptkey = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
                $text  = sprintf("HTTP/1.1 101 Switching Protocols\r\n");
                $text .= sprintf("Upgrade: websocket\r\n");
                $text .= sprintf("Connection: Upgrade\r\n");
                $text .= sprintf("Sec-Websocket-Accept: %s\r\n\r\n", $acceptkey);
                return $text;
            }
        }   

        return false;
    }

    /**
     * 这个是失败的http报文
     * websocket客户端会判断
     * 成功的时候必须返回101
     */
    public function response400($data='')
    {
        $len   = strlen($data);
        $text  = sprintf("HTTP/1.1 %d %s\r\n", 200, "ok");
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("OS: %s\r\n", PHP_OS);
        $text .= sprintf("Server: %s\r\n", "Te/1.0");
        $text .= sprintf("Connection-Language: %s\r\n", "zh-CN,zh;q=0.9");
        $text .= sprintf("Connection: %s\r\n", "Close");//keep-alive | close
        $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
        $text .= sprintf("Content-Type: %s\r\n", "text/html;charset=utf-8");
        $text .= sprintf("Content-Length: %d\r\n", $len);
        $text .= "\r\n";
        $text .= $data;
        return $text;
    }
}