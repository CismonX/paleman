<?php

require_once WORKERMAN_PATH . '/Lib/Channel/Server.php';
require_once WORKERMAN_PATH . '/Lib/Channel/Client.php';
/**
 * Parse request string sent by control panel.
 *
 * @param string $request_str
 *
 * @return array parse result
 */
function parse_request($request_str) {
    //Use spaces to indent strings.
    $request_arr = explode(' ', $request_str);
    $operation = $request_arr[0];
    //Operation type.
    switch ($operation) {
        //Format: add <task_name> <argument_1> <argument_2> ...
        case 'add':
            if (count($request_arr) < 3){
                $operation = 'err';
                $request['err'] = 'Invalid arguments.';
                break;
            }
            //Call user-defined function to parse add request.
            $task_name = $request_arr[1];
            if (!is_callable($task_name.'_add')){
                $operation = 'err';
                $request['err'] = 'Add function not callable.';
                break;
            }
            $request = call_user_func($task_name.'_add', array_slice($request_arr, 2));
            if (isset($request['err'])) {
                $operation = 'err';
                break;
            }
            $request['task_name'] = $task_name;
            break;
        //Format: cfg <configure_type> <argument_1> <argument_2> ...
        case 'cfg':
            if (count($request_arr) < 2) {
                $operation = 'err';
                $request['err'] = 'Invalid arguments.';
                break;
            }
            $func = $request_arr[1] . '_cfg';
            if (!is_callable($func)) {
                $operation = 'err';
                $request['err'] = 'Configure function not callable.';
                break;
            }
            //Call user-defined function to parse configure request.
            $request = call_user_func($func, array_slice($request_arr, 2));
            $request['task_name'] = $request_arr[1];
            break;
        //Format: del <task_id>
        case 'del':
            if (count($request_arr) < 2) {
                $operation = 'err';
                $request['err'] = 'Invalid arguments.';
                break;
            }
            $data = getGlobalData_array($request_arr[1], array('timer_id', 'worker_id', 'task_name'));
            if ($data === false) {
                $operation = 'err';
                $request_arr[1] = addslashes($request_arr[1]);
                $request['err'] = "Task ID $request_arr[1] do not exist.";
                break;
            }
            $request['task_id'] = $request_arr[1];
            $request['timer_id'] = $data['timer_id'];
            $request['worker_id'] = $data['worker_id'];
            $request['task_name'] = $data['task_name'];
            break;
        default:
            $operation = 'err';
            $request['err'] = 'Invalid operation type.';
            break;
    }
    $request['operation'] = $operation;
    return $request;
}