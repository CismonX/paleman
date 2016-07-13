<?php

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Protocols\Http;

function ws_onWorkerStart(Worker $worker){
    //Connect to GlobalData server.
    global $global;
    $global = new GlobalData\Client(GLOBALDATA_ADDR.':'.GLOBALDATA_PORT);
    //Connect to Channel client.
    Channel\Client::connect(CHANNEL_ADDR, CHANNEL_PORT);
    //Callback on Channel message. Different processes of the worker are independent.
    Channel\Client::on ('send', function($data) use($worker) {
        $msg = [
            'type' => 'msg',
            'data' => $data['msg']
        ];
        foreach ($worker->connections as $connection) {
            //Send to connections attached to specific task.
            if ($connection->task_id == $data['task']){
                $connection->send(json_encode($msg));
            }
        }
    });
}

function ws_onConnect(TcpConnection $connection) {
    $connection->task_id = null;
    $msg = ws_on_connect();
    //Do not send message if return true.
    if ($msg === true)
        return;
    //Close connection if error message is set;
    if (isset($msg['err'])) {
        $connection->close(json_encode($msg));
        return;
    }
    $connection->send(json_encode($msg));
}

function ws_onMessage(TcpConnection $connection, $data) {
    $data_arr = json_decode($data, true);
    //Data format: 'request' => request_str, 'verify' => verify_str.
    if (!isset($data_arr['request']) || !isset($data_arr['verify'])) {
        $msg = [
            'type' => 'err',
            'data' => 'Invalid request.'
        ];
        goto Send;
    };
    $verify = ws_connection_verify($data_arr['request'], $data_arr['verify']);
    if (isset($verify['err'])) {
        $msg = [
            'type' => 'err',
            'data' => $verify['err']
        ];
        goto Send;
    }
    //Attach client connection to specific task, client only receive messages posted by the corresponding task.
    $connection->task_id = $verify['task_id'];
    $msg = [
        'type' => 'signal',
        'data' => $verify['task_id']
    ];
    Send:
    $connection->send(json_encode($msg));
}

function task_onWorkerStart(Worker $worker) {
    //Connect to GlobalData server.
    global $global;
    $global = new GlobalData\Client(GLOBALDATA_ADDR.':'.GLOBALDATA_PORT);
    //Connect to Channel client.
    Channel\Client::connect(CHANNEL_ADDR, CHANNEL_PORT);
    //Register Channel events.
    Channel\Client::on ('add_'.$worker->id, function($data) use($worker) {
        $task_id = $data['task_id'];
        //Data to be delivered to initialization function and timer function.
        $arg_list = array_merge (
            DEFAULT_ARGS_ADD,
            isset($data['args']) ? $data['args'] : []
        );
        $arg = [];
        foreach($arg_list as $argument) {
            $arg[$argument] = $data[$argument];
        }
        //Call user-defined initialization function if exist.
        if (isset($data['init'])) {
            $init_func = $data['init'];
            if (!is_callable($init_func)) {
                echo "Error: Initialization function not callable.\n";
                setGlobalData($task_id);
                return;
            }
            //User-defined initialization function should have two arguments.
            //The first is a user-defined array. The second is task_id, which can be used to access GlobalData.
            $init = call_user_func($init_func, $arg, $task_id);
            //If error occurs in Initialization function, terminate current task.
            if (isset($init['err'])) {
                echo $init['err'];
                setGlobalData($task_id);
                return;
            }
            if($init !== true) {
                $arg['init'] = $init;
            }
        }
        //Store timer function callback in GlobalData.
        if (isset($data['timer'])) {
            setGlobalData($task_id, 'timer_func', $data['timer']);
        }
        //Add timer.
        if ($data['interval'])
            $timer_id = Timer::add ($data['interval'],
                function ($args, $task_id) use(&$timer_id){
                    //User-defined timer function should have two arguments.
                    //The first is a user-defined array. The second is task_id, which can be used to access GlobalData.
                    $timer_func = getGlobalData($task_id, 'timer_func');
                    if ($timer_func === null)
                        return;
                    if (!is_callable($timer_func)) {
                        echo "Error: Timer function not callable.\n";
                        return;
                    }
                    $msg = call_user_func($timer_func, $args, $task_id);
                    //When Timer function return without having to send message to client, return true.
                    if ($msg === true)
                        return;
                    $send_data['msg'] = $msg;
                    $send_data['task'] = $task_id;
                    //Send message to client.
                    Channel\Client::publish('send', $send_data);
                }, [$arg, $task_id]
            );
        else
            $timer_id = -1;
        setGlobalData($task_id, 'task_name', $data['task_name']);
        setGlobalData($task_id, 'timer_id', $timer_id);
        setGlobalData($task_id, 'worker_id', $worker->id);
    });
    Channel\Client::on ('set_'.$worker->id, function($data) use($worker) {
        //Data to be delivered to Configure function and timer function.
        $arg_list = array_merge(DEFAULT_ARGS_SET, $data['args']);
        $arg = [];
        foreach ($arg_list as $argument) {
            $arg[$argument] = $data[$argument];
        }
        if (isset($data['set_func'])) {
            $set_func = $data['set_func'];
            if (!is_callable($set_func)) {
                echo "Error: Configuration function not callable.\n";
                return;
            }
            call_user_func($set_func, $arg);
        }
    });
    Channel\Client::on ('del_'.$worker->id, function($data) use($worker) {
        $task_id = $data['task_id'];
        $timer_id = $data['timer_id'];
        $func = $data['task_name'].'_on_delete';
        //Call a function before dying. The function is expected to have one argument: task_id.
        if (is_callable($func)) {
            call_user_func($func, $task_id);
        }
        Timer::del($timer_id);
        //Free GlobalData.
        setGlobalData($task_id);
    });
}

function http_onWorkerStart(){
    //Connect to GlobalData server.
    global $global;
    $global = new GlobalData\Client(GLOBALDATA_ADDR.':'.GLOBALDATA_PORT);
    //Connect to Channel client.
    Channel\Client::connect(CHANNEL_ADDR, CHANNEL_PORT);
}

function http_onMessage(TcpConnection $connection) {
    //Enable Cross-Origin Resource Sharing (CORS).
    Http::header('Access-Control-Allow-Origin: '.CORS_DOMAIN);
    if (!isset($_POST['request'])) {
        $request_data['err'] = 'Bad Request.';
        goto err;
    }
    $request_data = parse_request($_POST['request']);
    global $global;
    $operation = $request_data['operation'];
    switch ($operation) {
        case 'add':
            //Set task_id.
            $task_id = uniqid();
            $request_data['task_id'] = $task_id;
            $global->add($task_id, []);
            if(!isset($request_data['interval'])) {
                //Never call timer function if not specified.
                $request_data['interval'] = 0;
            }
            //Select a random worker if not specified.
            if (!isset($request_data['worker_id'])) {
                mt_srand();
                $request_data['worker_id'] = mt_rand(0, TASK_WORKER_COUNT - 1);
            }
            elseif ($request_data['worker_id'] < 0 || $request_data['worker_id'] >= TASK_WORKER_COUNT) {
                $request_data['err'] = 'Invalid worker ID';
                goto err;
            }
            Channel\Client::publish ('add_'.$request_data['worker_id'], $request_data);
            //Return data to Control Panel.
            $return_list = array_merge(
                isset($request_data['return']) ? $request_data['return'] : array(),
                DEFAULT_RETURN_ADD
            );
            $return_data = [];
            foreach ($return_list as $return_msg) {
                $return_data[$return_msg] = $request_data[$return_msg];
            }
            $msg = [
                'type' => 'add',
                'data' => $return_data
            ];
            $connection->send(json_encode($msg));
            break;
        case 'set':
            //Select a random worker if not specified.
            if (!isset($request_data['worker_id'])) {
                mt_srand();
                $request_data['worker_id'] = mt_rand(0, TASK_WORKER_COUNT - 1);
            }
            if ($request_data['worker_id'])
            Channel\Client::publish ('set_'.$request_data['worker_id'], $request_data);
            //Return data to Control Panel.
            $return_list = array_merge($request_data['return'], DEFAULT_RETURN_SET);
            $return_data = [];
            foreach ($return_list as $return_msg) {
                $return_data[$return_msg] = $request_data[$return_msg];
            }
            $msg = [
                'type' => 'set',
                'data' => $return_data
            ];
            $connection->send(json_encode($msg));
            break;
        case 'del':
            $worker_id = $request_data['worker_id'];
            $task_id = $request_data['task_id'];
            $task_name = $request_data['task_name'];
            Channel\Client::publish ('del_'.$worker_id, $request_data);
            $msg = [
                'type' => 'del',
                'data' => [
                    'worker' => $worker_id,
                    'task' => $task_id,
                    'type' => $task_name
                ]
            ];
            $connection->send(json_encode($msg));
            break;
        case 'err':
            goto err;
    }
    return;
    err:
    $msg = [
        'type' => 'err',
        'data' => $request_data['err']
    ];
    $connection->send(json_encode($msg));
}