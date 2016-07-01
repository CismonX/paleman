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
define('DEFAULT_ARGS_ADD', array());
define('DEFAULT_RETURN_ADD', array('task_id', 'interval', 'task_name'));
define('DEFAULT_ARGS_SET', array());
define('DEFAULT_RETURN_SET', array('task_name'));

//Require files.
require_once WORKERMAN_PATH . '/Lib/Channel/Server.php';
require_once WORKERMAN_PATH . '/Lib/GlobalData/Server.php';
require_once WORKERMAN_PATH . '/Lib/Channel/Client.php';
require_once WORKERMAN_PATH . '/Lib/GlobalData/Client.php';

//Require files created by user.
foreach (glob(APPLICATION_PATH.'/*.php') as $require_file) {
    require_once $require_file;
}