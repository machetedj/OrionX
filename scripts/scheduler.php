<?php
declare(strict_types=1);
use App\Core\Application;use App\Services\{TaskQueueService,WatchFolderService};
require dirname(__DIR__).'/vendor/autoload.php';$app=Application::boot(dirname(__DIR__));$queue=$app->get(TaskQueueService::class);$minute=date('YmdHi');$day=date('Ymd');$app->get(WatchFolderService::class)->dispatchDue();$queue->enqueue('sync_balancers',['heartbeat_ttl'=>90],10,'sync-balancers-'.$minute);$queue->enqueue('cleanup_sessions',[],20,'cleanup-sessions-'.$minute);if(date('H:i')==='03:00')$queue->enqueue('rotate_logs',[],200,'rotate-logs-'.$day);$queue->recoverPending();echo "Scheduler ejecutado\n";
