<?php

namespace Te\Protocols;

class Stream implements Protocol
{
    /**
     * 长度
     */
    // 4个字节存储数据的总长度
    // 检测消息是否完整
    public function Len($data)
    {
        //pack/unpack
        if (strlen($data) < 4) {
            return false;
        }
        $tmp = unpack("NtotalLen", $data);
        //目前接收到的数据包的总长度还是小于指定的长度[消息不完整]
        if (strlen($data) < $tmp['totalLen']) {
            return false;
        }
        return true;
    }

    /**
     * 封包
     */
    public function encode($data = '')
    {
        //封包|拆包[需要又一个字段来表示数据包的长度，一条消息的完整设计协议时必须能知道数据包的长度]
        //给大家演示打包好的这二进制数据在内存中长啥样
        // $data = "hello"; //5个字节
        $totalLen = strlen($data)+6; //11个字节
        $bin = pack("Nn", $totalLen, "1").$data;
        return [$totalLen, $bin];        
    }

    /**
     * 拆包
     */
    public function decode($data = '')
    {
        $cmd = substr($data, 4, 2);
        $msg = substr($data, 6);
        return $msg;
    }

    /**
     * 一条消息总长度
     */
    public function msgLen($data = '')
    {
        $tmp = unpack("Nlength", $data);
        return $tmp['length'];
    }
}