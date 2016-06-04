<?php

require_once __DIR__ . '/require.php';

function parse_request($request_str) {
    //Use spaces to indent strings.
    $request_arr = explode(' ', $request_str);
    $operation = $request_arr[0];
    //Operation types.
    switch($operation) {
        //Format: <operation_type> <interval> <application_type> <argument_1> <argument_2> ...
        case 'add':
            //Call user-defined parser function.
            $request = call_user_func($request_arr[2].'_parse', array_slice($request_arr, 3));
            if($request === false)
                return false;
            $request['interval'] = (int)$request_arr[1];
            break;
        //Format: <operation_type> <listener_id>
        case 'del':
            $request['listener_id'] = $request_arr[1];
            break;
        default:
            return false;
    }
    $request['operation'] = $operation;
    return $request;
}
