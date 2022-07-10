<?php
namespace Te;

use Te\Event\Event;

class Response
{
    private $_statusReason = [
        '200' => "OK",
        '400' => 'Bad, Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '404' => 'not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
    ];
    public $_connection;
    public $_headers = [];
    public $_status_code = 200;
    public $_status_info = "OK";
    public $_start_chunked = 1;

    public function __construct(TcpConnection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * http其他方式请求处理
     */
    public function write($data = "")
    {
        // print_r($data);
        $len   = strlen($data);
        $text  = sprintf("HTTP/1.1 %d %s\r\n", $this->_status_code, $this->_status_info);
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("OS: %s\r\n", PHP_OS);
        $text .= sprintf("Server: %s\r\n", "Te/1.0");
        $text .= sprintf("Connection-Language: %s\r\n", "zh-CN,zh;q=0.9");
        // $text .= sprintf("Connection: %s\r\n", $_REQUEST['Connection']);
        $text .= sprintf("Connection: %s\r\n", isset($_REQUEST['Connection']) ? $_REQUEST['Connection'] : "close");
        $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
        foreach ($this->_headers as $k => $v) {
            $text .= sprintf("%s: %s\r\n", $k, $v);    
        }
        
        if (!isset($this->_headers['Content-Type'])) {
            $text .= sprintf("Content-Type: %s\r\n", "text/html;charset=utf-8");
        }
        if (isset($_REQUEST['Accept-Encoding'])) {
            $encoding = $_REQUEST['Accept-Encoding'];
            if (preg_match("/gzip/", $encoding)) {
                $data = gzencode($data);
                $len  = strlen($data);
                $text .= sprintf("Content-Encoding: %s\r\n", "gzip");
            }
        }
        $text .= sprintf("Content-Length: %d\r\n", $len);
        $text .= "\r\n";
        $text .= $data;
        $this->_connection->send($text);
        if (!isset($_REQUEST['Connection']) || strtolower($_REQUEST['Connection']) == "close") {
            $this->_connection->Close();
        }
    }

    /**
     * 分块传输
     */
    public function chunked($data = "")
    {
        // $len   = strlen($data);
        $text = "";
        if ($this->_start_chunked == 1) {
            $this->_start_chunked == 0;
            $text  = sprintf("HTTP/1.1 %d %s\r\n", $this->_status_code, $this->_status_info);
            $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
            $text .= sprintf("OS: %s\r\n", PHP_OS);
            $text .= sprintf("Connection-Language: %s\r\n", "zh-CN,zh;q=0.9");
            $text .= sprintf("Connection: %s\r\n", $_REQUEST['Connection']);
            $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
            foreach ($this->_headers as $k => $v) {
                $text .= sprintf("%s: %s\r\n", $k, $v);    
            }
            //$text .= sprintf("Content-Length: %d\r\n", $len);
            if (!isset($this->_headers['Content-Type'])) {
                $text .= sprintf("Content-Type: %s\r\n", "text/html;charset=utf-8");
            }
            $text .= sprintf("Transfer-Encoding: chunked\r\n");
            $text .= "\r\n";
        }


        $dataLen = dechex(strlen($data));
        $text .= $dataLen."\r\n";
        $text .= $data."\r\n";
        // $text .= "0\r\n"; //用它来结束，表示响应实体结束了
        // $text .= "\r\n";
        $this->_connection->send($text);
        // if (strtolower($_REQUEST['Connection']) == "close") {
        //     $this->_connection->Close();
        // }
    }

    public function end()
    {
        $text  = "0\r\n"; //用它来结束，表示响应实体结束了
        $text .= "\r\n";
        $this->_connection->send($text);
        $this->_start_chunked == 1;
        if (strtolower($_REQUEST['Connection']) == "close") {
            $this->_connection->Close();
        }
    }

    public function header($key, $value)
    {
        $this->_headers[$key] = $value;
    }

    /**
     * 返回上传的文件
     */
    public function sendFile($file)
    {
        if (!file_exists($file)) {
            $this->status(404);
            $this->write("Not found file");
            return false;
        }
        $data = file_get_contents($file);
        if (!class_exists("finfo", false)) {
            return false;
        }
        $fi   = new \finfo(FILEINFO_MIME_TYPE);
        $len   = strlen($data);
        $text  = sprintf("HTTP/1.1 %d %s\r\n", $this->_status_code, $this->_status_info);
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("OS: %s\r\n", PHP_OS);
        $text .= sprintf("Server: %s\r\n", "Te/1.0");
        $text .= sprintf("Connection-Language: %s\r\n", "zh-CN,zh;q=0.9");
        $text .= sprintf("Connection: %s\r\n", $_REQUEST['Connection']);
        $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
        foreach ($this->_headers as $k => $v) {
            $text .= sprintf("%s: %s\r\n", $k, $v);    
        }
        $text .= sprintf("Content-Type: %s\r\n", $fi->file($file));
        
        if (isset($_REQUEST['Accept-Encoding'])) {
            $encoding = $_REQUEST['Accept-Encoding'];
            if (preg_match("/gzip/", $encoding)) {
                $data = gzencode($data);
                $len  = strlen($data);
                $text .= sprintf("Content-Encoding: %s\r\n", "gzip");
            }
        }
        $text .= sprintf("Content-Length: %d\r\n", $len);
        $text .= "\r\n";
        $text .= $data;
        $this->_connection->send($text);
        if (strtolower($_REQUEST['Connection']) == "close") {
            $this->_connection->Close();
        }
    }

    public function status($code)
    {
        $this->_status_code = $code;
        if (isset($this->_statusReason[$code])) {
            $this->_status_info = $this->_statusReason[$code];
        }
    }

    /**
     * ajax options方法请求处理跨域请求
     */
    public function sendMethods()
    {
        $text  = "HTTP/1.1 200 ok\r\n";
        $text .= sprintf("Service: %s\r\n", "Te");
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("Content-Length: 0\r\n");
        $text .= sprintf("Connection: Keep-alive\r\n");
        // $text .= sprintf("ALLOW: GET, HEAD, POST, PUT, DELETE, TRACE, OPTIONS, PATCH\r\n");
        $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
        $text .= sprintf("Access-Control-Allow-Method: POST, GET\r\n");
        $text .= sprintf("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept\r\n\r\n");

        $this->_connection->send($text);
    }
}