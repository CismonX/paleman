<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Protocols\Http;

require_once __DIR__ . '/function.php';

function http_onWorkerStart(Worker $worker){
    global $global;
    $global = new GlobalData\Client('127.0.0.1:2207');
    Channel\Client::connect('127.0.0.1', 2206);
}

function ws_onWorkerStart(Worker $worker){
    global $global;
    $global = new GlobalData\Client('127.0.0.1:2207');
    Channel\Client::connect('127.0.0.1', 2206);
    Channel\Client::on('send_'.$worker->id, function($data) use($worker){
        foreach($worker->connections as $connection){
            if($connection->timer_id == $data['timer']){
                $connection->send($data['msg']);
            }
        }
    });
}

function ws_onConnect(TcpConnection $connection) {
    $connection->timer_id = -1;
    $msg = array(
        'type' => 'signal',
        'data' => 'connected'
    );
    $connection->send(json_encode($msg));
}

function ws_onMessage(TcpConnection $connection, $data){
    global $global;
    //data: listener_id
    $timer_id = $global->$data['timer_id'];
    $connection->timer_id = (int)$timer_id;
    $msg = array(
        'type' => 'signal',
        'data' => 'listen',
        'id' => $connection->timer_id
    );
    $connection->send(json_encode($msg));
}

function http_onMessage(TcpConnection $connection) {
    //CORS
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
            //set listener id
            $listener_id = time();
            $arg['target'] = $request_data['target'];
            $arg['listener'] = $listener_id;
            //add globaldata
            $global->add($listener_id, array());
            //initialize
            call_user_func_array($request_data['init'], $arg);
            $timer_id = Timer::add(
                $request_data['interval'],
                'timer_callback',
                array($request_data['timer'], $arg)
            );
            do {
                $old_listener = $new_listener = $global->$listener_id;
                $new_listener['timer_id'] = $timer_id;
            } while (!$global->cas($listener_id, $old_listener, $new_listener));
            //return: listener_id, interval, type, target
            $return_data['listener_id'] = $listener_id;
            $return_data['interval'] = $request_data['interval'];
            foreach($request_data['return'] as $return){
                $return_data[$return] = $request_data[$return];
            }
            $msg = array(
                'type' => 'msg',
                'data' => $return_data
            );
            $connection->send(json_encode($msg));
            break;
        case 'del';
            $listener_id = $request_data['listener_id'];
            global $global;
            $del = Timer::del($global->$listener_id['timer_id']);
            unset($global->$listener_id);
            $msg = array(
                'type' => 'msg',
                'data' => array(
                    'id' => $listener_id,
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
    $msg = array(
        'type' => 'err',
        'data' => 'Bad Request'
    );
    $connection->send(json_encode($msg));
}

