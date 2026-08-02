<?php
declare(strict_types=1);
use App\Core\Application;use App\Services\{TaskQueueService,TaskWorker};
require dirname(__DIR__).'/vendor/autoload.php';
$app=Application::boot(dirname(__DIR__));
$worker=$app->get(TaskWorker::class);
$queue=$app->get(TaskQueueService::class);
$name=gethostname().':'.getmypid();
$once=in_array('--once',$argv,true);
$lastRecovery=0;
do{
 if(time()-$lastRecovery>=15){$queue->recoverPending();$lastRecovery=time();}
 $worked=$worker->runOne($name);
 if(!$worked&&!$once)usleep(500000);
}while(!$once);
