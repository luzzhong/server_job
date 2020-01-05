# server_job

#### 利用swoole实现任务投递，多进程处理任务

##### demo中利用client投递任务，socket_server负责建立通讯，Job负责拉起manager进程、worker进程，进程内部通过消息队列传递数据


###### 实现根据任务量大小来决定是否fork新worker进程以及回收多余worker进程，监听master进程异常退出，当woker处理完成任务后终止程序运行
