<?php

namespace Te\Protocols;

//data1\ndata2\ndata3\n
//$a = substr($data, strpos($data, "\n"));
class Text implements Protocol
{
    /**
     * 长度
     */
    // 4个字节存储数据的总长度
    // 检测消息是否完整
    public function Len($data)
    {
        //pack/unpack
        if (strlen($data)) {
            return strpos($data, "\n");
        }
        return false;
    }

    /**
     * 封包
     */
    public function encode($data = '')
    {
        $data = $data."\n";
        return [strlen($data), $data];        
    }

    /**
     * 拆包
     */
    public function decode($data = '')
    {
        return rtrim($data, "\n");
    }

    /**
     * 一条消息总长度
     */
    public function msgLen($data = '')
    {
        return strpos($data, "\n")+1;
    }
}