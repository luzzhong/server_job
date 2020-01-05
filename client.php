<?php
//生产任务
$socket=new Co\Socket(AF_INET,SOCK_STREAM,0);
go(function ()use($socket){
    while (true){
        $socket->connect('127.0.0.1',9800);
        $socket->send('hello');
        co::sleep(0.1);
    }
});

$socket=new Co\Socket(AF_INET,SOCK_STREAM,0);
go(function ()use($socket){
    while (true){
        $socket->connect('127.0.0.1',9800);
        $socket->send('hello');
        co::sleep(0.1);
    }

});

