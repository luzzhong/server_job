<?php
include  __DIR__.'/socket_server.php';
class Job{
     protected  $worker_num=3;
     protected  $worker_max_num=10;
     protected  $table;
     protected  $manager_num=2;
     protected  $msg_queue;
     public  function  __construct()
     {
         $this->master_pid=getmypid(); //主进程id
         $this->createTable();
         $this->run();
         $this->monitor(); //进程监听,回收

     }

    public  function  createTable(){
        $this->table=new Swoole\Table(1024);
        $this->table->column('idle',swoole_table::TYPE_INT,1); //0代表空闲,1忙碌
        $this->table->create(); //
    }
     //负责执行任务处理中心
     public  function  run(){
         $msg_key=ftok(__DIR__,'x'); //注意在php创建消息队列，第二个参数会直接转成字符串，可能会导致通讯失败
         //echo $msg_key;
         $this->msg_queue=msg_get_queue($msg_key);
         //manager进程组
         for ($i=0;$i<$this->manager_num;$i++){
                $this->create_manager();
         }
         //worker进程组
         for ($i=0;$i<$this->worker_num;$i++){
              $this->create_worker();
         }
     }
     public  function  create_worker(){
         $process=new Swoole\Process(function ($process){
             $this->table->set(getmypid(),['idle'=>0]);
             while (true){
                //从消息队列当中去读取数据
                msg_receive($this->msg_queue,0,$message_type,1024,$message,false);
                $this->table->set(getmypid(),['idle'=>1]);
                //业务逻辑
                sleep(2);
                echo "worker进程".posix_getpid().":".$message.PHP_EOL;
                $this->table->set(getmypid(),['idle'=>0]); //空闲的
            }
         });

         $process->start();
     }
    public  function  create_manager(){

        $process=new Swoole\Process(function ($process){
             $this->check_tick($process->pid);
             $server=new Server('tcp://0.0.0.0:9800');
             $server->onMessage=function ($socket,$message){
                   //echo getmypid().'接收到消息:'.$message.PHP_EOL;
                 msg_send($this->msg_queue,1,$message,false,false);
             };

             $server->listen();
        });
        $process->start();
    }

    //ps -ef | grep php  | awk '{print $2}' | xargs kill -9
    public function monitor(){

        swoole_process::signal(SIGRTMAX-4, function($sig) {
            $this->create_worker();
            var_dump("进程创建");
        });

        //信号的监听
        swoole_process::signal(SIGCHLD, function($sig) {
            while ($res=swoole_process::wait(false)){
                var_dump("回收子进程",$res);
            }
            //重启进程
        });
        //回收正常结束子进程
        while ($res=swoole_process::wait(false)){
            var_dump($res);
        }

        //检测队列长度,来决定是否要开启多个进程
        swoole_timer_tick(10,function (){
            $stat=msg_stat_queue($this->msg_queue);
           // var_dump($stat['msg_qnum']);
            //当前的进程数不得大于最大的允许的进程个数
            if($this->table->count()<=($this->worker_max_num-$this->worker_num)){
                //超过10个开启一个子进程
                if($stat['msg_qnum']>10 ){
                    //直接创建有问题swoole禁止了，触发信号创建,
                    //可以使用swoole的信号监听机制
                    swoole_process::kill($this->master_pid,SIGRTMAX-4);
                }
            }
        });

        //定时检测是否有空闲进程,只清除空闲
        //当进程个数大于初始化的worker进程数的时候
        //队列为任务数不多的情况下才去清除
        swoole_timer_tick(1000,function (){
            $stat=msg_stat_queue($this->msg_queue);
            $queueNum=$stat['msg_qnum'];
           // var_dump($this->table);
            foreach ($this->table as $k=>$v){
                 if($queueNum==0 && $this->table->count()>$this->worker_num && $v['idle']==0){
                     var_dump("空闲进程清除");
                     swoole_process::kill($k);
                      $this->table->del($k);
                 }
            }

        });
    }

    public  function  checkPid(){
        return swoole_process::kill($this->master_pid,0);
    }

   //检测master子进程是否退出
    public  function  check_tick($manger_pid){
        swoole_timer_tick(1000,function ()use($manger_pid){
            if(!swoole_process::kill($this->master_pid,0)){
                $stat=msg_stat_queue($this->msg_queue);
                $queueNum=$stat['msg_qnum'];
                foreach ($this->table as $k=>$v){
                    var_dump($queueNum,$queueNum==0 && $v['idle']==0);
                    if($queueNum==0 && $v['idle']==0){
                        var_dump("子进程退出");
                        $this->table->del($k);
                        swoole_process::kill($k,SIGKILL);
                    }
                }
                //主进程退出并且子进程也都结束了
                if($stat && $this->table->count()==0){
                    var_dump("manager进程也退出");
                    swoole_process::kill($manger_pid,SIGKILL);
                }
            }
        });

    }
}
$job=new job();
