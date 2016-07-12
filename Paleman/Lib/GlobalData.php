<?php
/**
 * Lib/GlobalData.php
 *
 * Use GlobalData client to access data stored on GlobalData server.
 * Remind that PHP global variables is only available within the process.
 * Furthermore, it is recommended that you use redis or ssdb when data is large or data operation is frequent.
 */
require_once WORKERMAN_PATH . '/Lib/GlobalData/Server.php';
require_once WORKERMAN_PATH . '/Lib/GlobalData/Client.php';
/**
 * Get data from GlobalData server.
 *
 * @param string $task_id
 * @param string $key
 *
 * @return mixed
 *
 */
function getGlobalData($task_id, $key) {
    global $global;
    if(!isset($global->$task_id))
        return false;
    $data = $global->$task_id[$key];
    return $data;
}
/**
 * Get multiple data from GlobalData server.
 *
 * @param string $task_id
 * @param array $keys
 *
 * @return array|bool
 */
function getGlobalData_array($task_id, array $keys) {
    global $global;
    if(!isset($global->$task_id))
        return false;
    $data = array();
    foreach ($keys as $key) {
        $data[$key] = $global->$task_id[$key];
    }
    return $data;
}
/**
 * Set or unset GlobalData. (atomic operation)
 *
 * @param string $task_id
 * @param string $key
 * @param mixed $data
 *
 * @return bool
 */
function setGlobalData($task_id, $key = null, $data = null) {
    global $global;
    if (!isset($global->$task_id))
        return false;
    //Unset key instead of set key to null.
    if ($key === null) {
        unset($global->$task_id);
        return true;
    }
    do {
        $old_data = $new_data = $global->$task_id;
        if ($data === null)
            unset($new_data[$key]);
        else
            $new_data[$key] = $data;
    } while(!$global->cas($task_id, $old_data, $new_data));
    return true;
}
/**
 * Multiple set or unset GlobalData.
 *
 * @param string $task_id
 * @param array $data - data with keys
 *
 * @return bool
 */
function setGlobalData_array($task_id, array $data = []) {
    global $global;
    if (!isset($global->$task_id))
        return false;
    if(count($data) == 0) {
        unset($global->$task_id);
        return true;
    }
    do {
        $old_data = $new_data = $global->$task_id;
        foreach ($data as $key => $value) {
            if ($value === null)
                unset($new_data[$key]);
            else
                $new_data[$key] = $value;
        }
    } while(!$global->cas($task_id, $old_data, $new_data));
    return true;
}