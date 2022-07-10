<?php

namespace App\Controllers;

class IndexController extends BaseController
{
    public function index()
    {
        $data = ['a' => 'b'];
        // print_r(1231);
        print_r($this->_request->_get);
        print_r($this->_request->_post);

        return json_encode($data);
    }

    /**
     * 并发测试
     */
    public function test()
    {
        return "hello,word";
    }
}