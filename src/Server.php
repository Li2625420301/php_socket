<?php
namespace Te;

use Exception;
use Te\Event\Epoll;
use Te\Event\Event;
use Te\Event\Select;
use Te\Protocols\Stream;
use Opis\Closure\SerializableClosure;

class Server
{
    public $_mainSocket;
    public $_local_socket;
    static public $_connections = [];
    public $_events = [];
    public $_protocol = NULL;
    public $_protocol_layout;

    static public $_clientNum = 0; //客户端连接数
    static public $_recvNum = 0; //执行recv/fread调用次数
    static public $_msgNum = 0; //接收了多少消息

    public $_startTime = 0;

    public $_protocols = [
        "stream" => "Te\Protocols\Stream",
        "text"   => "Te\Protocols\Text",
        "ws"     => "Te\Protocols\Websocket",
        "http"   => "Te\Protocols\Http",
        "mqtt"   => "",
    ];
    public $_usingProtocol;

    static public $_eventLoop;
    public $_setting = [];
    public $_pidMap = [];

    static public $_pidFile; //父进程表示文件
    static public $_logFile; //日志文件 
    static public $_startFile; //启动文件
    
    public $_status = ''; //进程状态
    const  STATUS_STAER = 1;
    const  STATUS_RUNNING = 2;
    const  STATUS_SHUTDOWN = 3;

    public $_unix_socket = '';
    static public $_os;

    public function __construct($local_socket)
    {
        list($protocol, $ip, $port) = explode(":", $local_socket);
        if (isset($this->_protocols[$protocol])) {
            $this->_usingProtocol = $protocol;
            $this->_protocol = new $this->_protocols[$protocol]();
        }
        // $this->_local_socket = $local_socket;
        $this->_startTime = time();
        $this->_local_socket = "tcp:".$ip.":".$port;

        if (PHP_OS == 'Linux') {
            static::$_os = 'Linux';
        } else {
            static::$_os = 'Windows';
        }

    }

    /**
     * 初始化
     */
    public function init()
    {
        date_default_timezone_set("Asia/Shanghai");
        $strace = debug_backtrace();
        $startFile = array_pop($strace)['file']; //debug_backtrace函数获取当前运行方法的文件路径、类名、函数名、等信息
        static::$_startFile = $startFile; //启动文件
        static::$_pidFile = pathinfo($startFile)['filename'].".pid";
        static::$_logFile = pathinfo($startFile)['filename'].".log";
        if (!file_exists(static::$_logFile)) {
            touch(static::$_logFile);
        }
        chown(static::$_logFile, posix_getuid());

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->echoLog("<file:%s>---<line:%s>---<info:%s>", $errfile, $errline, $errstr);
        });
    }

    public function setting($setting)
    {
        $this->_setting = $setting;
    }

    /**
     * 回调存储
     */
    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function worker()
    {
        // COW 进程在复制的时候会把父进程已经存在数据【正文段 + 数据段（bss, data, 常量数据， 静态全局数据段）】
        srand();
        mt_rand();

        if ($this->_status == static::STATUS_RUNNING) { //子进程异常退出，重启进程会复制父进程的状态【运行中】
            // 状态不用更改
            $this->runEventCallBack("workerReload", [$this]);
        } else { //子进程正常启动，
            $this->_status = static::STATUS_RUNNING; //子进程运行
        }
        cli_set_process_title("Te/worker");
        $this->Listen();

        if (static::$_os = "Linux") {
            static::$_eventLoop = new Epoll(); //linux系统调用
            if ($this->checkSetting("daemon")) {
                // $this->resetFd();
            }
        } else {
            static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用
        }

        // static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用

        pcntl_signal(SIGINT,  SIG_IGN, false);//忽略信号
        pcntl_signal(SIGTERM, SIG_IGN, false);//忽略信号
        pcntl_signal(SIGQUIT, SIG_IGN, false);//忽略信号
        
        static::$_eventLoop->add(SIGINT, Event::EV_SIGNAL, [$this, "sigHeadle"]);
        static::$_eventLoop->add(SIGTERM, Event::EV_SIGNAL, [$this, "sigHeadle"]);
        static::$_eventLoop->add(SIGQUIT, Event::EV_SIGNAL, [$this, "sigHeadle"]);

        static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, "Accept"]);
        // static::$_eventLoop->add(2, Event::EV_TIME_ONCE, [$this, "checkHeartTime"]);//定时事件1次
        // static::$_eventLoop->add(1, Event::EV_TIME, [$this, "checkHeartTime"]);//定时事件持续
        // static::$_eventLoop->add(1, Event::EV_TIME, [$this, "statistics"]);//定时事件持续
        // static::$_eventLoop->add(2, Event::EV_TIME, function ($timeId, $userArg) {
        //     echo posix_getpid()."do 定时\n";
        // });//定时事件持续
        $this->runEventCallBack("workerStart", [$this]);
        // $timerIds = static::$_eventLoop->add(2, Event::EV_TIME, function ($timerId, $arg) {
            // print_r($arg);
            // print_r($timerId);
            // echo "20".PHP_EOL;
            // static::$_eventLoop->del($timerId, Event::EV_TIME);
        // },['name'=>'tony1']);//定时事件延伸
        $this->eventLoop();
        // fprintf(STDOUT, "<workerPid:%d> exit event loop success\n", posix_getpid());
        $this->runEventCallBack("workerStop", [$this]);
        exit(0);

    }

    /**
     * 创建多进程
     */
    public function forkWorker()
    {
        // $this->Listen();
        $workerNum = 1;
        if (isset($this->_setting["workerNum"])) {
            $workerNum = $this->_setting["workerNum"];
        }
        for ($i=0; $i<$workerNum; $i++) {
            $pid = pcntl_fork();
            if ($pid == 0) { //子进程执行accept
                $this->worker();
            } else {
                $this->_pidMap[$pid] = $pid;//存储pid
            }
        }
    }

    /**
     * 创建任务
     */
    public function forkTasker()
    {
        $workerNum = 1;
        if (isset($this->_setting["taskNum"])) {
            $workerNum = $this->_setting["taskNum"];
        }
        for ($i=0; $i<$workerNum; $i++) {
            $pid = pcntl_fork();
            if ($pid == 0) { //子进程执行accept
                $this->tasker($i+1);
            } else {
                $this->_pidMap[$pid] = $pid;//存储pid
            }
        }
    }

    public function tasker($i)
    {
        // COW 进程在复制的时候会把父进程已经存在数据【正文段 + 数据段（bss, data, 常量数据， 静态全局数据段）】
        srand();
        mt_rand();

        cli_set_process_title("Te/tasker");

        $unix_socket_server_file = $this->_setting['task']['unix_socket_server_file'].$i;
        if (file_exists($unix_socket_server_file)) {
            unlink($unix_socket_server_file);
        }
        $this->_unix_socket = socket_create(AF_UNIX, SOCK_DGRAM, 0); //UNIX的upd套接字文件
        socket_bind($this->_unix_socket, $unix_socket_server_file);

        $stream = socket_export_stream($this->_unix_socket); //udp socket转换socket文件资源
        socket_set_blocking($stream, 0);

        if (static::$_os == 'Linux') {
            static::$_eventLoop = new Epoll(); //linux系统调用
            if ($this->checkSetting("daemon")) {
                // $this->resetFd();
            }
        } else {
            static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用
        }

        // static::$_eventLoop = new Select(); //window和linux系统调用， 这里在win上使用
        
        static::$_eventLoop->add(SIGINT, Event::EV_SIGNAL, [$this, "taskSigHeadle"]);
        static::$_eventLoop->add(SIGTERM, Event::EV_SIGNAL, [$this, "taskSigHeadle"]);
        static::$_eventLoop->add(SIGQUIT, Event::EV_SIGNAL, [$this, "taskSigHeadle"]);

        static::$_eventLoop->add($stream, Event::EV_READ, [$this, "acceptUdpClient"]);
        // $udpConnect = new UdpConnection($this->_unix_socket);
        static::$_eventLoop->loop();

        // while (1) {
            // $len = socket_recvfrom($this->_unix_socket, $buf, 1024, 0, $uninXlientFile);
        //     if ($len) {
        //         fprintf(STDOUT, "recv data:%s,file=%s\n", $buf, $uninXlientFile);
        //         // socket_sendto($$this->_unix_socket, "server:".$buf, 7+strlen($buf), 0, $uninXlientFile);
        //     }
        
        //     // if (strncasecmp($buf, "quit", 4) == 0) {
        //     //     break;
        //     // }
        // }
        exit(0);
    }

    public function getUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());
    }

    /**
     * 服务端启动
     */
    public function Start()
    {
        $this->_status = static::STATUS_STAER; //启动
        $this->init();

        global $argv;
        $command = $argv[1];
        switch ($command) {
            case "start":
                if (is_file(static::$_pidFile)) {
                    $masterPid = file_get_contents(static::$_pidFile);
                } else {
                    $masterPid = 0;
                }
                cli_set_process_title("Te/master"); //设置进程名称

                //当前进程启动后，会从pidFile取出服务器的进程号，如果进程号存活并且当前进程不是已经启动的服务器进程
                // posix_kill   // 第一个参数 进程的标识PID
                                // 第二个参数 信号的编号｜信号的名字
                                // 如果 第一个参数为0则会向整个进程组发送信号
                //posix_kill($masterPid, 0)检测当前进程是否存活，如果主进程PID存在情况下，向该进程发送信号0，实际上并没有发送任何信息，只是检测该进程（或进程组）是否存活，同时也检测当前用户是否有权限发送系统信号；
                $masterPidIsAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();

                if ($masterPidIsAlive) {
                    $this->echoLog("server already running....");
                }
                $this->runEventCallBack("masterStart", [$this]);

                // 是否开始守护进程
                if ($this->checkSetting("daemon")) {
                    $this->daemon(); //主进程设置守护进程
                    $this->resetFd();
                }

                $this->saveMasterPid();
                $this->installSignalHandle();
                // $this->Listen();
                $this->forkWorker();
                $this->forkTasker();

                $this->_status = static::STATUS_RUNNING; //主进程运行
                $this->displayStartInfo(); //输出服务运行相关信息
                $this->masterWorker();
            break;

            case "stop":
                $masterPid = file_get_contents(static::$_pidFile);
                if ($masterPid && posix_kill($masterPid, 0)) {
                    //15) SIGTERM 程序结束(terminate)信号, 与SIGKILL不同的是该信号可以被阻塞和处理。通常用来要求程序自己正常退出，shell命令kill缺省产生这个信号。如果进程终止不了，我们才会尝试SIGKILL。
                    // posix_kill($masterPid, SIGTERM);
                    posix_kill($masterPid, SIGINT);
                    echo "发送了SIGTERM信号\n";
                    echo $masterPid."\n";
                    $timeout = 5;
                    $stoptime = time();
                    while (1) {
                        $masterPidIsAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();
                        if ($masterPidIsAlive) {
                            if (time() - $stoptime >= $timeout) {
                                // fprintf(STDOUT, "server stop failure\n");
                                $this->echoLog("server stop failure");
                                break;
                            }
                            sleep(1);
                            continue;
                        }
                        // fprintf(STDOUT, "server stop success\n");
                        $this->echoLog("server stop success");
                        // exit(0);
                        break;
                    }
                } else {
                    $this->echoLog("server not exits....");
                }

            break;

            case "restart":

            break;

            default :
                $usage = "php ".pathinfo(static::$_startFile)['filename'].".php [start|top]";
                $this->echoLog($usage);
        }
        
    }
    
    /** 
     * 主进程处理信号函数
     */
    public function installSignalHandle()
    {
        pcntl_signal(SIGINT,  [$this, "sigHeadle"], false);
        pcntl_signal(SIGTERM, [$this, "sigHeadle"], false);
        pcntl_signal(SIGQUIT, [$this, "sigHeadle"], false);

        pcntl_signal(SIGPIPE, SIG_IGN, false);//主要是读写socket文件时产生该信号是忽略
    }

    /**
     * 主进程和子进程收到中断信号会执行该函数
     */
    public function sigHeadle($sigNum)
    {
        // print_r($sigNum);
        $masterPid = file_get_contents(static::$_pidFile);
        switch ($sigNum) {
            case SIGINT :
            case SIGTERM :
            case SIGQUIT :
                // print_r($sigNum);
                if ($masterPid == posix_getpid()) { //主进程
                    //向所有子进程发送信号
                    foreach ($this->_pidMap as $pid => $pid) {
                        posix_kill($pid, $sigNum);
                    }
                    $this->_status = static::STATUS_SHUTDOWN; //主进程关闭

                } else { //子进程
                    //子进程接收到信号，停掉工作任务
                    static::$_eventLoop->del($this->_mainSocket, Event::EV_READ);
                    set_error_handler(function () {});
                    fclose($this->_mainSocket);
                    restore_error_handler();
                    $this->_mainSocket = NULL;
                    /** @var TcpConnection $connection */
                    foreach (static::$_connections as $fd => $connection) {
                        $connection->Close();
                    }
                    static::$_connections = [];

                    static::$_eventLoop->clearSignalevents();
                    static::$_eventLoop->clearTimer();

                    if (static::$_eventLoop->exitLoop()) {
                        // fprintf(STDOUT, "<pid:%d> exit event loop success\n", posix_getpid());
                        $this->echoLog("<pid:%d> server exit event loop success", posix_getpid());
                    }
                }
            break;
        }
    }

    /**
     * 任务进程收到中断信号会执行该函数
     */
    public function taskSigHeadle($sigNum)
    {
        static::$_eventLoop->del($this->_unix_socket, Event::EV_READ);
        set_error_handler(function () {});
        fclose($this->_unix_socket);
        restore_error_handler();
        $this->_unix_socket = NULL;
        
        static::$_eventLoop->clearSignalevents();
        static::$_eventLoop->clearTimer();

        if (static::$_eventLoop->exitLoop()) {
            // fprintf(STDOUT, "<pid:%d> task event loop success\n", posix_getpid());
            $this->echoLog("<pid:%d> task event loop success", posix_getpid());

        }
    }

    /**
     * 回收子进程
     */
    public function masterWorker()
    {
        while (1) {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status); //等待子进程
            pcntl_signal_dispatch();
            if ($pid > 0) {
                unset($this->_pidMap[$pid]);
                if ($this->_status != static::STATUS_SHUTDOWN) { //主进程在运行，子进程异常退出重启子进程
                    $this->reloadWoker();
                }
                // fprintf(STDOUT, "<pid:%d>子进程退出\n", posix_getpid());
                $this->echoLog("<pid:%d>子进程退出", posix_getpid());
            }
            if (empty($this->_pidMap)) {
                // fprintf(STDOUT, "子进程全部退出\n");
                $this->echoLog("子进程全部退出");
                break;
            }
        }
        // fprintf(STDOUT, "master server exit ok\n");
        $this->echoLog("master server exit ok");
        $this->runEventCallBack("masterShutDown", [$this]);
        exit(0);//父进程退出
    }

    /**
     * 重启进程
     */
    public function reloadWoker()
    {
        $pid = pcntl_fork();
        if ($pid == 0) { //子进程执行accept
            $this->worker();
        } else {
            $this->_pidMap[$pid] = $pid;//存储pid
        }
    }

    /**
     *  存储主进程pid文件
     */
    public function saveMasterPid()
    {
        $masterPid = posix_getpid();
        file_put_contents(static::$_pidFile, $masterPid);
    }

    /**
     * 服务端监听
     */
    public function Listen()
    {
        
        $flag = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;//tcp  监听｜绑定
        $option['socket']['backlog'] = 102400; //epoll select [1024]

        // socket惊群
        // 解决方案：1、创建单独的socket 
        //         2、设置so_reuseport【复用端口】
        $option['socket']['so_reuseport'] = 1;
        $context = stream_context_create($option); //创建

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        // socket设置禁用Nagle算法
        // Nagle算法在宽带资源小的情况下传输大块包数据会出现延迟情况
        $socket = socket_import_stream($this->_mainSocket);//socket_import_stream函数可以将使用stream_socket_server创建stream socket句柄转换为标准的socket句柄
        socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);//socket_set_option设置标准的socket参数

        stream_set_blocking($this->_mainSocket, 0); //设置非阻塞I/O

        if (!is_resource($this->_mainSocket)) {
            // fprintf(STDOUT, "server create fail:%s", $errstr);
            $this->echoLog("server create fail:%s", $errstr);
            exit(0);
        }
        // fprintf(STDOUT, "listen on:%s\n", $this->_local_socket);
        $this->echoLog("listen on:%s", $this->_local_socket);

        // IO复用函数【select,epoll】
        // 监听socket,连接socket
        // 监听socket是指已经【socket,bind,listen】listen会创建一个监听socket
        // accept 函数会从监听socket中获取一个客户端连接，这个连接成为 连接socket
    }

    /**
     * epoll I/O复用 轮询
     */
    public function eventLoop()
    {
        static::$_eventLoop->loop();
    }

    /**
     * select I/O复用 轮询
     */
    public function loop()
    {
        // 中断信号 “可中断系统调用”、“可重入函数”
        // 主要根据传递的参数[read=[socket file...],write,exp文件描述符]
        // 当socket file 文件描述符上有事件发生[读写事件]，该函数就会返回就绪[已经发生的事件]的
        // 文件描述符个数，系统内部就会修改read[读事件发生时]数组中的某个或是某几个文件描述符
        // 简称I/O事件
        // fd_set 数组来存储
        // fd_set 位域来存储
        // 假如 传入 2 3 4 5 [socket 文件]  $read = [socket文件2,3,4,5]
        // 执行     1 1 1 1   每一个文件分配相同的1
        // 当4执行时 0 0 1 0   其他是位域是0  $read = [4] socket 4执行
        // $readFds[] = $this->_mainSocket;
        // $writeFds = [];
        // $expFds = [];
        // 1) 0 会很快返回，相当于非阻塞，但是本质上是阻塞的，所以要加死循环，缺点就是导致cpu占用大量的时间
        // 2）NULL 会阻塞到客户端连接为止

        $readFds[] = $this->_mainSocket;
        while (1) {
            $reads = $readFds;
            $writes = [];
            $expts = [];

            $this->statistics(); //统计
            // $this->checkHeartTime();//心跳 

            // $this->_mainSocket 监听socket 我们只会关注它的读事件[客户端连接]
            if (!empty(static::$_connections)) {
                foreach (static::$_connections as $idx => $connection) {
                    /** @var TcpConnection $connection */
                    $sockfd = $connection->socketFd();
                    if (is_resource($sockfd)) {
                        $reads[] = $sockfd;  //监听读事件 [in]
                        $writes[] = $sockfd; //监听写事件 [out]   读和写就是I/O复用
                    }
                }
            }
            set_error_handler(function () {}); //隐藏错误
            // $ret = stream_select($reads, $writes, $expFds, NULL, NULL); //c语言中执行函数就是select函数 这一行是阻塞i/o

            $ret = stream_select($reads, $writes, $expFds, 0, 100); //非阻塞i/o，设置超时时间0秒，100微秒

            restore_error_handler();
            if ($ret === false) {
                break;
            }
            if ($reads) {
                foreach ($reads as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->Accept();
                    } else {
                        if (isset(static::$_connections[(int)$fd])) {
                            /** @var TcpConnection $connection */
                            $connection = static::$_connections[(int)$fd];
                            if ($connection->isConnected()) {
                                $connection->recv4socket();
                            }
                        }
                    }
                    /*
                        else {
                            $data = fread($fd, 1024);
                            if ($data) {
                                fprintf(STDOUT, "接收到<%d>客户端的数据了:%s\n", (int)$fd, $data);
                                fwrite($fd, "hello,world");
                            }
                        }
                    */
                }
            }

            if ($writes) {
                foreach ($writes as $fd) {
                    if (isset(static::$_connections[(int)$fd])) {
                        /** @var TcpConnection $connection */
                        $connection = static::$_connections[(int)$fd];
                        if ($connection->isConnected()) {
                            $connection->write2socket();
                        }
                    }
                }
            }
        }
    }

    /**
     * 服务端接收
     */
    public function Accept()
    {
        // 接收
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peername);
        // if (is_resource($this->_mainSocket)) {
        if (is_resource($connfd)) {
            $connection = new TcpConnection($connfd, $peername, $this);
            $this->onClientJoin();
            static::$_connections[(int)$connfd] = $connection;
            // if (isset($_events['connect']) && is_callable($_events['connect'])) {
            //     $this->_events['connect']($this, $connection);
            // }
            $this->runEventCallBack("connect", [$connection]);
            // echo "接收到客户端连接了\n";
            // print_r($connfd);
        }
    }

    
    /**
     * 回调函数
     */
    public function runEventCallBack($eventName, $args=[])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }
    
    /**
     * 统计数据
     */
    public function onClientJoin()
    {
        ++ static::$_clientNum;
    }
    
    /**
     * 客户端关闭清除连接
     */
    public function removeClient($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])) {
            unset(static::$_connections[(int)$sockfd]);
            -- static::$_clientNum;
        } 
    }
    
    public function onRecv()
    {
        ++ static::$_recvNum;
    }
    
    public function onMsg()
    {
        ++ static::$_msgNum;
    }
    
    public function statistics()
    {
        $nowTime = time();
        $diffTime = $nowTime - $this->_startTime;
        $this->_startTime = $nowTime;
        if ($diffTime >= 1) {
            // fprintf(STDOUT, "pid<%d>--time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>\r\n", 
            $this->echoLog("pid<%d>--time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>", 
            posix_getpid(), $diffTime, (int)$this->_mainSocket, static::$_clientNum, static::$_recvNum, static::$_msgNum);
            
            static::$_recvNum = 0;
            static::$_msgNum = 0;
        }
    }
    
    /**
     * 检测心跳
     */
    public function checkHeartTime()
    {
        // echo "定时时间到了\n";
        foreach (static::$_connections as $idx => $connection) {
            /** @var TcpConnection $connection */
            if ($connection->checkHeartTime()) {
                $connection->Close();
            }
        }
    }
    
    /**
     * 服务端接收
     */
    public function acceptUdpClient()
    {
        // 接收
        set_error_handler(function () {});
        $len = socket_recvfrom($this->_unix_socket, $buf, 65535, 0, $unixClientFile);
        restore_error_handler();
        if ($buf&&$unixClientFile) {
            $udpConnect = new UdpConnection($this->_unix_socket, $len, $buf, $unixClientFile);
            // $this->runEventCallBack("task", [$udpConnect, $buf]);
            // 反序列化闭包
            // $wrapper = unserialize($buf);
            // $closure = $wrapper->getClosure();
            // $closure($this);
        }
        return false;
    }

    /**
     * 投递任务
     */
    public function task($taskFunc)
    {
        $taskNum = $this->_setting['taskNum'];
        $index = rand(1, $taskNum);
        // $index = mt_rand(1, 2);
        $unix_socket_client_file = $this->_setting['task']['unix_socket_client_file'];
        $unix_socket_server_file = $this->_setting['task']['unix_socket_server_file'].$index;
        if (file_exists($unix_socket_client_file)) {
            unlink($unix_socket_client_file);
        }

        // 匿名函数使用
        $factorial = function ($n) use (&$taskFunc) {
            return $taskFunc($n);
        };
        //序列化闭包
        $wrapper = new SerializableClosure($factorial);
        $serialized = serialize($wrapper);

        $sockfd = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($sockfd, $unix_socket_client_file);

        $len = strlen($serialized);
        $bin = pack("N", $len+4).$serialized;

        socket_sendto($sockfd, $bin, $len+4, 0, $unix_socket_server_file);
        socket_close($sockfd);
    }

    /**
     * 守护进程开启
     */
    public function daemon()
    {
        umask(000);//设置文件权限
        $pid = pcntl_fork();
        if ($pid > 0) {
            //父进程结束
            exit(0);
        }

        if (-1 == posix_setsid()) {
            //子进程创建失败
            throw new Exception("setsid failure");
            exit(0);
        }

        $pid = pcntl_fork();
        if ($pid > 0) {
            //父进程结束
            exit(0);
        }

    }

    /**
     * 守护进程设置属性
     */
    public function checkSetting($item)
    {   
        if (isset($item) && $this->_setting[$item] == true) {
            return true;
        }
        return false;
    }

    /**
     * 是否开启守护进程
     */
    public function resetFd()
    {
        // 守护进程关闭标准输入｜标准输出｜标准报错输出，没有效果，弃用
        /*
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        fopen("/dev/null", "a");
        fopen("/dev/null", "a");
        fopen("/dev/null", "a");
        */
    }

    /**
     * 守护进程输出日志文件
     */
    public function echoLog($format, ...$data)
    {
        if ($this->checkSetting("daemon")) {
            $info = sprintf($format, ...$data);
            $msg  = "[pid:".posix_getpid()."]-[".date("Y-m-d H:i:s")."]-[info:".$info."]\r\n";
            file_put_contents(static::$_logFile, $msg, FILE_APPEND);
        } else {
            fprintf(STDOUT, $format."\r\n", ...$data);
        }
        $this->resetFd();
    }

    public function displayStartInfo()
    {
        
        $info  = "\r\n\e[32;40m".file_get_contents('logo.txt')." \e[0m";
        $info .= "\e[33;40mTe workerNum:".$this->_setting['workerNum']." \e[0m \r\n";
        $info .= "\e[33;40m Te taskNum:".$this->_setting['taskNum']." \e[0m \r\n";
        $info .= "\e[33;40m Te run mode:".($this->checkSetting("daemon") ? "daemon" : "debug")." \e[0m \r\n";
        $info .= "\e[33;40m Te working with:".$this->_usingProtocol." protocol \e[0m \r\n";//应用层协议
        $info .= "\e[33;40m Te server listen on:".$this->_local_socket." \e[0m \r\n";
        $info .= "\e[33;40m Te run on:".static::$_os." platform \e[0m \r\n";

        fwrite(STDOUT, $info);
    }
}