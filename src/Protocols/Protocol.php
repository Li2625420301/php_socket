<?php

namespace Te\Protocols;

/**
 * 接口函数
 */
interface Protocol
{
    /**
     * 长度接口
     */
    public function Len($data);
    
    /**
     * 封包接口
     */
    public function encode($data = '');

    /**
     * 拆包接口
     */
    public function decode($data = '');
    

    /**
     * 消息长度接口
     */
    public function msgLen($data = '');
    
}