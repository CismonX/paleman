<?php

require_once __DIR__ . '/config.php';

use Workerman\Worker;
use Workerman\WebServer;

require_once WORKERMAN_PATH . '/Autoloader.php';

//Initialize web Server
$web_server = new WebServer('http://0.0.0.0:'.WEB_PORT);
$web_server->count = 4;
//Path for host documents.
$web_server->addRoot('', WEB_PATH);

//Initialize Channel Server
$channel_server = new Channel\Server('0.0.0.0', CHANNEL_PORT);
//Initialize GlobalData Server
$global_server = new GlobalData\Server('0.0.0.0', GLOBALDATA_PORT);

require_once __DIR__ . '/event.php';

//Initialize Send Worker.
$send_worker = new Worker('websocket://0.0.0.0:'.SEND_PORT);
$send_worker->count = SEND_WORKER_COUNT;
$send_worker->name = 'Send Worker';
$send_worker->onWorkerStart = 'ws_onWorkerStart';
$send_worker->onConnect = 'ws_onConnect';
$send_worker->onMessage = 'ws_onMessage';

//Initialize Task Worker.
$task_worker = new Worker();
$task_worker->count = TASK_WORKER_COUNT;
$task_worker->name = 'Task Worker';
$task_worker->onWorkerStart = 'task_onWorkerStart';

//Initialize Control Worker.
$ctrl_worker = new Worker('http://0.0.0.0:'.CONTROL_PORT);
$ctrl_worker->count = CONTROL_WORKER_COUNT;
$ctrl_worker->name = 'Control Worker';
$ctrl_worker->onWorkerStart = 'http_onWorkerStart';
$ctrl_worker->onMessage = 'http_onMessage';

//Start Workerman.
Worker::runAll();