<?php

//Constants.
define('WORKERMAN_PATH', __DIR__ . '/../Workerman');
define('APPLICATION_PATH', __DIR__ . '/../Application');
define('WEB_PATH', APPLICATION_PATH . '/web');
define('CONTROL_WORKER_COUNT', 4);
define('TASK_WORKER_COUNT', 8);
define('SEND_WORKER_COUNT', 4);
define('CHANNEL_ADDR', '127.0.0.1');
define('GLOBALDATA_ADDR', '127.0.0.1');
define('WEB_PORT', 80);
define('SEND_PORT', 2000);
define('CONTROL_PORT', 2001);
define('CHANNEL_PORT', 2206);
define('GLOBALDATA_PORT', 2207);
define('CORS_DOMAIN', '*');
define('DEFAULT_INTERVAL', 60);
define('DEFAULT_ARGS_ADD', []);
define('DEFAULT_RETURN_ADD', ['task_id', 'interval', 'task_name']);
define('DEFAULT_ARGS_SET', []);
define('DEFAULT_RETURN_SET', ['task_name']);

//Require Paleman library.
foreach (glob(__DIR__ . '/Lib/*.php') as $require_file) {
    require_once $require_file;
}

//Application.
foreach (glob(APPLICATION_PATH.'/*.php') as $require_file) {
    require_once $require_file;
}