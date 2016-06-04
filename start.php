<?php

use \Workerman\Worker;
use \Workerman\WebServer;
use \Workerman\Autoloader;

require_once __DIR__ . '/Workerman/Autoloader.php';
require_once __DIR__ . '/Workerman/Lib/Channel/Server.php';
require_once __DIR__ . '/Workerman/Lib/GlobalData/Server.php';

Autoloader::setRootPath(__DIR__);

$web_server = new WebServer('http://0.0.0.0:80');
$web_server->count = 4;
$web_server->addRoot('', __DIR__.'/../Application/web');

$channel_server = new Channel\Server('0.0.0.0', 2206);
$global_server = new GlobalData\Server('0.0.0.0', 2207);

require_once __DIR__ . '/event.php';

$ws_worker = new Worker('websocket://0.0.0.0:2000');
$ws_worker->count = 4;
$ws_worker->onWorkerStart = 'ws_onWorkerStart';
$ws_worker->onConnect = 'ws_onConnect';
$ws_worker->onMessage = 'ws_onMessage';

$http_worker = new Worker('http://0.0.0.0:2001');
$http_worker->count = 4;
$http_worker->onWorkerStart = 'http_onWorkerStart';
$http_worker->onMessage = 'http_onMessage';

Worker::runAll();