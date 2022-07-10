<?php

namespace Te\Protocols;

class Http implements Protocol
{
    public $_headerlen = 0;
    public $_bodylen = 0;
    public $_get;
    public $_post;

    public function parseHeader($data)
    {   
        $_REQUEST = $_GET = [];
        $temp = explode("\r\n", $data);
        $startLine = $temp[0]; //第一行比较特殊 POST /index.heml HTTP/1.1
        list($method, $uri, $schema) = explode(" ", $startLine);
        $_REQUEST['uri'] = parse_url($uri)['path'];
        $query = parse_url($uri, PHP_URL_QUERY);
        parse_str($query, $_GET);
        // print_r($query);
        $_REQUEST['method'] = $method;
        $_REQUEST['schema'] = $schema;

        unset($temp[0]);

        foreach ($temp as $item) {
            $kv     = explode(": ", $item, 2);
            $key    = str_replace("-", "_", $kv[0]);
            $_REQUEST[$key] = rtrim($kv[1]);
        }

        $ipAddr = explode(":", $_REQUEST["Host"], 2);
        $_REQUEST['ip']   = $ipAddr[0];
        $_REQUEST['port'] = $ipAddr[1];

        // return $temp;
    }

    public function parseBody($data)
    {
        $_POST = [];
        $content_type = $_REQUEST['Content_Type'];
        $boundary = "";//边界 \S是匹配非空白字符
        if (preg_match("/boundary=(\S+)/i", $content_type, $matches)) {
            $boundary = "--".$matches[1];
            $content_type = "multipart/form-data";
        }
        switch ($content_type) {
            case "multipart/form-data":
                $this->parseFromData($boundary, $data);
                break;
            case "application/x-www-form-urlencoded":
                parse_str($data, $_POST);
                break;
            case "application/json":
                // print_r($data);
                $_POST = json_decode($data, true);
                break;
        }
    }
    /**
     * 长度
     */
    public function Len($data)
    {
        if (strpos($data, "\r\n\r\n")) {
            $this->_headerlen = strpos($data, "\r\n\r\n");
            $this->_headerlen += 4;

            $bodylen = 0;
            if (preg_match("/\r\nContent-Length: ?(\d+)/i", $data, $matches)) {
                // [0] => Content-Length: 10
                // [1] => 10
                $bodylen = $matches[1]; //10
            }
            $this->_bodylen = $bodylen;

            $totalLen = $this->_headerlen + $this->_bodylen;
            if (strlen($data) >= $totalLen) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * 封包
     */
    public function encode($data = '')
    {
        return [strlen($data), $data];
    }

    /**
     * 拆包
     */
    public function decode($data = '')
    {
        $header = substr($data, 0, $this->_headerlen - 4);//-4 是去掉\r\n
        $body   = substr($data, $this->_headerlen);
        $this->parseHeader($header);
        if ($body) {
            $this->parseBody($body);
        }

        return $body;
    }

    /**
     * 一条消息总长度
     */
    public function msgLen($data = '')
    {
        return $this->_headerlen + $this->_bodylen;
    }

    public function parseFromData($boundary, $data)
    {
        $data = substr($data, 0, -4);
        $formData = explode($boundary, $data);
        // print_r($formData);
        $_FILES = [];
        $key = 0;
        foreach ($formData as $field) {
            if ($field) {
                $kv = explode("\r\n\r\n", $field, 2);
                $value = rtrim($kv[1], "\r\n");

                if (preg_match('/name="(.*)"; filename="(.*)"/', $kv[0], $matches)) {
                    $_FILES[$key]['name'] = $matches[1];
                    $_FILES[$key]['file_name'] = $matches[2];
                    $_FILES[$key]['file_value'] = $value;
                    $_FILES[$key]['file_size']  = strlen($value);

                    file_put_contents("www/".$matches[2], $value);//保存上传文件

                    $fileType = explode("\r\n", $kv[0], 2);
                    $fileType = explode(": ", $fileType[1]);
                    $_FILES[$key]['file_type'] = $fileType[2];
                    ++ $key;
                } else if (preg_match('/name="(.*)"/', $kv[0], $matches)) {
                    $_POST[$matches[1]] = $value;
                }
            } 
        }
    }
}