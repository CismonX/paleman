<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Protocols\Http;

require_once __DIR__ . '/function.php';

function http_onWorkerStart(Worker $worker){
    //Connect to GlobalData client.
    global $global;
    $global = new GlobalData\Client('127.0.0.1:2207');
    //Connect to Channel client.
    Channel\Client::connect('127.0.0.1', 2206);
}

function ws_onWorkerStart(Worker $worker){
    //Connect to GlobalData client.
    global $global;
    $global = new GlobalData\Client('127.0.0.1:2207');
    //Connect to Channel client.
    Channel\Client::connect('127.0.0.1', 2206);
    //Callback on Channel message. Different processes of the worker are independent.
    Channel\Client::on('send_'.$worker->id, function($data) use($worker){
        foreach ($worker->connections as $connection){
            //Send to connections attached to specific timer.
            if ($connection->timer_id == $data['timer']){
                $connection->send($data['msg']);
            }
        }
    });
}

function ws_onConnect(TcpConnection $connection) {
    //Initialize 'timer_id' key on client connect.
    $connection->timer_id = -1;
    $msg = array(
        'type' => 'signal',
        'data' => 'connected'
    );
    $connection->send(json_encode($msg));
}

function ws_onMessage(TcpConnection $connection, $data){
    global $global;
    //Expected data is task_id.
    $timer_id = $global->$data['timer_id'];
    //Attach client connection to specific timer, client only receive messages posted by the corresponding timer.
    $connection->timer_id = (int)$timer_id;
    $msg = array (
        'type' => 'signal',
        'data' => 'listen',
        'id' => $connection->timer_id
    );
    $connection->send(json_encode($msg));
}

function http_onMessage(TcpConnection $connection) {
    //Enable Cross-Origin Resource Sharing (CORS).
    Http::header('Access-Control-Allow-Origin: *');
    if (!isset($_POST['request']))
        goto Bad_Request;
    $request_data = parse_request($_POST['request']);
    if ($request_data === false)
        goto Bad_Request;
    global $global;
    $operation = $request_data['operation'];
    switch ($operation) {
        case 'add':
            $arg = array();
            //Set task_id.
            $task_id = uniqid();
            //Data to be delivered to initialization function and timer function.
            foreach($request_data['args'] as $argument) {
                $arg[$argument] = $request_data[$argument];
            }
            $global->add($task_id, array());
            //Call user-defined initialization function.
            //The first is a user-defined array. The second is task_id, which can be used to access GlobalData.
            call_user_func($request_data['init'], $arg, $task_id);
            //Add timer.
            $timer_id = Timer::add ($request_data['interval'],
                function (callable $timer_func, $args, $task_id) use(&$timer_id){
                    //User-defined timer function should have two arguments.
                    //The first is a user-defined array. The second is task_id, which can be used to access GlobalData.
                    $send_data['msg'] = call_user_func($timer_func, $args, $task_id);
                    $send_data['timer'] = $timer_id;
                    //When Timer function return without having to send message to client, return false.
                    if($send_data === false)
                        return;
                    //Send message to client. Different processes of a Worker are independent.
                    for($worker = 0; $worker < 4; $worker++){
                        Channel\Client::publish('send_'.$worker, $send_data);
                    }
                }, array($request_data['timer'], $arg, $task_id)
            );
            //Store timer_id to GlobalData (atomic operation).
            do {
                $old_task = $new_task = $global->$task_id;
                $new_task['timer_id'] = $timer_id;
            } while (!$global->cas($task_id, $old_task, $new_task));
            //Default return data.
            $return_data['task_id'] = $task_id;
            $return_data['interval'] = $request_data['interval'];
            //User-defined data to be returned to Control Panel.
            foreach($request_data['return'] as $return) {
                $return_data[$return] = $request_data[$return];
            }
            $msg = array (
                'type' => 'msg',
                'data' => $return_data
            );
            $connection->send(json_encode($msg));
            break;
        case 'del';
            $task_id = $request_data['task_id'];
            global $global;
            //Check whether the task_id exists.
            if(!isset($global->$task_id['timer_id'])) {
                $del = false;
                goto Send;
            }
            //Delete Timer.
            $del = Timer::del($global->$task_id['timer_id']);
            //Free GlobalData.
            unset($global->$task_id);
            Send:
            $msg = array (
                'type' => 'msg',
                'data' => array (
                    'id' => $task_id,
                    'status' => (int)$del
                )
            );
            $connection->send(json_encode($msg));
            break;
        default:
            goto Bad_Request;
    }
    return;
    Bad_Request:
    $msg = array (
        'type' => 'err',
        'data' => 'Bad Request'
    );
    $connection->send(json_encode($msg));
}