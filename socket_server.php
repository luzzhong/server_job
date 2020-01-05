<?php
class Server{
    protected $socket = NULL;
    public function __construct($socket_address) {
        $this->addr=$socket_address;
    }

    public  function  listen()
    {
        $context = stream_context_create([
            'socket' => [
                'backlog' => 102400
            ]
        ]);
        //监听客户端链接 + 设置地址重用
        stream_context_set_option($context, 'socket', 'so_reuseport', 1); //请求负载均衡分配到不同进程
        stream_context_set_option($context, 'socket', 'so_reuseaddr', 1); //设置连接重用
        $this->socket = stream_socket_server($this->addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        //利用swoole的事件监听扩展，监听套接字，一旦监听到读写事件
        swoole_event_add($this->socket, function ($fp) {
            $socket = stream_socket_accept($fp);
            swoole_event_add($socket, function ($fp) {
                $resp = fread($fp, 8192); //获取用户消息
                //检查连接是否关闭
                if ($resp === '') {
                    if ((feof($fp) || !is_resource($fp))) {
                        swoole_event_del($fp); //删除事件
                        fclose($fp);
                        return null;
                    }
                } else {
                    //表示一个正常的连接，已经读取到消息，交给回掉函数处理
                    call_user_func($this->onMessage,$fp,$resp);
                }
            });
        });
    }
}

