<?php
/**
 * Implements process in PHP
 *
 * Process class
 *
 * @category  Process
 */

/**
 * Process class
 *
 * @package   Process
 * @author    shaogx <shaogx@gmail.com>
 * @copyright 2016
 * @link      https://github.com/shaogx
 */

class Process {
    const FUNCTION_NOT_CALLABLE = 10;
    const COULD_NOT_FORK = 15;

    /**
     * Possible errors
     *
     * @var array
     */
    private $_errors = array(
        Process::FUNCTION_NOT_CALLABLE => 'You must specify a valid function name that can be called from the current scope.',
        Process::COULD_NOT_FORK => 'pcntl_fork() returned a status of -1. No new process was created',
    );

    /**
     * Callback for the function that should run as a separate process
     *
     * @var callback
     */
    protected $runnable;

    /**
     * Holds the current process id
     *
     * @var integer
     */
    private $_pid;

    /**
     * start time of process
     *
     * @var float
     */
    private $startTime = 0;

    /**
     * lifetime of process
     *
     * @var float
     */
    private $lifeTime = 3; //6s

    /**
     * start time of process
     *
     * @var float
     */
    private $deadTime = 0; //time() + $this->lifeTime

    /**
     * Exits with error
     *
     * @return void
     */

    private function fatalError($errorCode) {
        die($this->getError($errorCode));
        throw new Exception($this->getError($errorCode));
    }

    /**
     * Checks if process is supported by the current PHP configuration
     *
     * @return boolean
     */
    public static function isAvailable() {
        $required_functions = array(
            'pcntl_fork',
        );

        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Class constructor - you can pass
     * the callback function as an argument
     *
     * @param callback $runnable Callback reference
     */
    public function __construct($runnable = null) {
        // if (!Process::isAvailable()) {
        //     throw new Exception("Process not supported");
        // }

        if ($runnable !== null) {
            $this->setRunnable($runnable);
        }

        $this->startTime = microtime(true);
    }

    /**
     * Sets the callback
     *
     * @param callback $runnable Callback reference
     *
     * @return callback
     */
    public function setRunnable($runnable) {
        if (self::isRunnableOk($runnable)) {
            $this->runnable = $runnable;
        } else {
            $this->fatalError(Process::FUNCTION_NOT_CALLABLE);
        }
    }

    /**
     * Gets the callback
     *
     * @return callback
     */
    public function getRunnable() {
        return $this->runnable;
    }

    /**
     * Checks if the callback is ok (the function/method
     * is runnable from the current context)
     *
     * can be called statically
     *
     * @param callback $runnable Callback
     *
     * @return boolean
     */
    public static function isRunnableOk($runnable) {
        return (is_callable($runnable));
    }

    /**
     * Returns the process id (pid) of the process
     *
     * @return int
     */
    public function getPid() {
        return $this->_pid;
    }

    /**
     * Checks if the child process is alive
     *
     * @return boolean
     */
    public function isAlive() {
        $pid = pcntl_waitpid($this->_pid, $status, WNOHANG);
        //echo "\npid=>{$this->_pid},{$pid}\n";
        return ($pid === 0);
    }

    /**
     * Set lifeTime
     *
     * @param float $deadTime
     *
     * @return boolean
     */
    public function setLifeTime($lifeTime) {
        if ($lifeTime) {
            $this->lifeTime = $lifeTime;
        }
        return $this->lifeTime;
    }

    /**
     * Set deadTime
     *
     * @param float $deadTime
     *
     * @return boolean
     */
    public function setDeadTime($lifeTime) {
        if ($lifeTime) {
            $this->lifeTime = $lifeTime;
        }
        $this->deadTime = $this->lifeTime + microtime(true);
        return $this->deadTime;
    }

    /**
     * check if timeout
     *
     *
     * @return boolean
     */
    public function timeout() {
        // $now = microtime(true);
        // echo "\nstartTime=>{$this->startTime}\n";
        // echo "\nlifeTime=>{$this->lifeTime}\n";
        // echo "\nNowTime=>{$now}\n";
        return $this->startTime + $this->lifeTime < microtime(true) ? true : false;
    }

    /**
     * Starts the process, all the parameters are
     * passed to the callback function
     *
     * @return void
     */
    public function start() {
        $pid = @pcntl_fork();
        if ($pid == -1) {
            $this->fatalError(Process::COULD_NOT_FORK);
        }
        if ($pid) {
            // parent
            $this->_pid = $pid;
        } else {
            // child
            // pcntl_signal(SIGTERM, array($this, 'handleSignal'));
            // pcntl_signal(SIGTERM, SIG_DFL);
            // pcntl_signal(SIGCHLD, SIG_DFL);
            $arguments = func_get_args();
            if (!empty($arguments)) {
                call_user_func_array($this->runnable, $arguments);
            } else {
                call_user_func($this->runnable);
            }

            exit(0);
        }
    }

    /**
     * Attempts to stop the process
     * returns true on success and false otherwise
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function stop($signal = SIGKILL, $wait = false) {
        if ($this->isAlive()) {
            posix_kill($this->_pid, $signal);
            if ($wait) {
                pcntl_waitpid($this->_pid, $status = 0);
            }
        }
    }

    /**
     * Alias of stop();
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function kill($signal = SIGKILL, $wait = false) {
        return $this->stop($signal, $wait);
    }

    /**
     * Gets the error's message based on its id
     *
     * @param integer $code The error code
     *
     * @return string
     */
    public function getError($code) {
        if (isset($this->_errors[$code])) {
            return $this->_errors[$code];
        } else {
            return "No such error code $code ! Quit inventing errors!!!";
        }
    }

    /**
     * Signal handler
     *
     * @param integer $signal Signal
     *
     * @return void
     */
    protected function handleSignal($signal) {
        switch ($signal) {
        case SIGTERM:
            exit(0);
            break;
        }
    }
}
