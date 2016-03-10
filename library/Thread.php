<?php

/**
 * Thread class
 *
 * @package   Thread
 * @author    shaogx <shaogx@gmail.com>
 * @copyright 2016
 * @link      https://github.com/shaogx
 */

class Thread extends Thread {
    const FUNCTION_NOT_CALLABLE = 10;
    const COULD_NOT_FORK = 15;

    /**
     * Possible errors
     *
     * @var array
     */
    private $_errors = array(
        Thread::FUNCTION_NOT_CALLABLE => 'You must specify a valid function name that can be called from the current scope.',
        Thread::COULD_NOT_FORK => 'Thread returned a status of -1. No new process was created',
    );

    /**
     * Exits with error
     *
     * @return void
     */

    private function fatalError($errorCode) {
        throw new Exception($this->getError($errorCode));
    }

    /**
     * Checks if process is supported by the current PHP configuration
     *
     * @return boolean
     */
    public static function isAvailable() {

        if (@class_exists('Threaded')) {
            return false;
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
        if (!Thread::isAvailable()) {
            throw new Exception("Thread not supported");
        }

        if ($runnable !== null) {
            $this->setRunnable($runnable);
        }
    }

}
