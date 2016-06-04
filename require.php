<?php

require_once __DIR__ . '/Workerman/Lib/Channel/Client.php';
require_once __DIR__ . '/Workerman/Lib/GlobalData/Client.php';

//Path for files created by user.
foreach(glob(__DIR__ . '/../Application/*.php') as $require_file) {
    require_once $require_file;
}