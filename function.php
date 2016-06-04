<?php

require_once __DIR__ . '/require.php';

function parse_request($request_str) {
    $request_arr = explode(' ', $request_str);
    //add, del
    $operation = $request_arr[0];
    switch($operation) {
        case 'add':
            $request = call_user_func($request_arr[2].'_parse', array_slice($request_arr, 3));
            if($request === false)
                return false;
            $request['interval'] = (int)$request_arr[1];
            break;
        case 'del':
            $request['listener_id'] = $request_arr[1];
            break;
        default:
            return false;
    }
    $request['operation'] = $operation;
    return $request;
}

function timer_callback(callable $timer_func, $args, $data = null) {
    $send_data = call_user_func_array($timer_func, array_values($args));
    if($send_data === false)
        return;
    for($worker = 0; $worker < 4; $worker++){
        Channel\Client::publish('send_'.$worker, $send_data);
    }
}
