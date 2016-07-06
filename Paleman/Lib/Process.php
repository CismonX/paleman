<?php
/**
 * Lib/Process.php
 *
 * Create a child process with bash command, and interact with the process via stdin/stdout.
 */
use Workerman\Connection\TcpConnection;
class Process {
    /**
     * Constructor.
     *
     * @param string $cmd - command line sent to bash
     */
    function __construct($cmd) {
        if(isset($cmd))
            $this->cmd = $cmd;
    }
    /**
     * Set options.
     *
     * @param int $type - option type
     * @param mixed $value - option value
     *
     * @return $this
     */
    public function set($type, $value) {
        switch ($type) {
            case self::CMD:
                $this->cmd = $value;
                break;
            case self::ENV:
                $this->env = $value;
                break;
            case self::CWD:
                $this->cwd = $value;
                break;
            case self::TYPE:
                if ($value == self::PIPE)
                    $this->descriptor_spec = array(
                        0 => array('pipe', 'r'),
                        1 => array('pipe', 'w')
                    );
                elseif ($value == self::PTY)
                    //To execute command in pty, PHP_CAN_DO_PTS must be enabled before PHP compile.
                    //See ext/standard/proc_open.c in PHP source directory.
                    $this->descriptor_spec = array(
                        0 => array('pty'),
                        1 => array('pty')
                    );
                break;
            case self::ON_MESSAGE:
                /**
                 * onMessage callback. Call on process stdout.
                 *
                 * @param TcpConnection $connection - process stdout connection
                 * @param mixed $data data received from pipe                 *
                 */
                $this->onMessage = $value;
                break;
            case self::ON_CLOSE;
                /**
                 * onClose callback. Call on process terminate. (stdout connection lost)
                 *
                 * @param TcpConnection $connection - process stdout connection
                 */
                $this->onClose = $value;
                break;
        }
        return $this;
    }
    /**
     * Get process status. (magic method)
     *
     * @param string $str - status descriptor
     *
     * @return mixed - status
     */
    function __get($str) {
        if(isset($this->res)) {
            $status = proc_get_status($this->res);
            if ($status === false)
                return false;
            if ($str == 'status')
                return $status;
            if (isset($status[$str]))
                return $status[$str];
        }
        return null;
    }
    /**
     * Execute command in a child process.
     *
     * @return resource|bool - process resource.
     */
    public function run() {
        if (isset($this->res))
            return null;
        $res = proc_open($this->cmd, $this->descriptor_spec, $this->pipes, $this->cwd, $this->env);
        if($res !== false) {
            $this->stdout = new TcpConnection($this->pipes[1]);
            if (is_callable($this->onMessage))
                $this->stdout->onMessage = $this->onMessage;
            if (is_callable($this->onClose))
                $this->stdout->onClose = $this->onClose;
            stream_set_blocking($this->pipes[0], 0);
            $this->res = $res;
            return $res;
        }
        return false;
    }
    /**
     * Write data to stdin pipe.
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function send($data) {
        fwrite($this->pipes[0], $data);
        return $this;
    }
    /**
     * Terminate process
     *
     * @return int - exit code
     */
    public function kill() {
        if(isset($this->res)) {
            $this->stdout->close();
            fclose($this->pipes[0]);
            $status = proc_get_status($this->res);
            $exit = proc_close($this->res);
            $exit = $status['running'] ? $exit : $status['exitcode'];
            unset($this->res, $this->pipes, $this->stdout);
            return $exit;
        }
        return null;
    }
    /**
     * Destructor.
     */
    function __destruct() {
        if (isset($this->res))
            $this->kill();
    }

    private $cmd, $descriptor_spec, $cwd, $env;
    private $res, $pipes, $stdout, $onMessage, $onClose;
    //Options.
    const CMD = 1, ENV = 2, CWD = 3, TYPE = 4, ON_MESSAGE = 5, ON_CLOSE = 6;
    //Type.
    const PIPE = 1, PTY = 2;
};
