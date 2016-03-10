<?php
/*
 * many worker kill tasks from a stack parallelly.
 *
 */

require_once "Stack.php";
require_once "Process.php";

class Pool {

    const TICK_DELAY = 50000; //0.0005s delay after tick, in millisecs

    public $worker; // the function name to call. can be a method of a static class as well
    public $pool = array(); // to store instance
    public $max; // number of worker for killing tasks parallelly.

    public $stack; // Stack instance.

    /**
     * Constructor
     *
     * @param string $worker  function name
     * @param integer $max  number of worker
     */

    public function __construct($worker, $max = 1) {
        $this->worker = $worker;
        $this->max = $max;
        $this->stack = new Stack();
    }

    /**
     * start new processes if needed
     *
     * @return int stack size
     */

    public function waiting() {
        $this->tick();
        while ($num = $this->countPool()) {
            usleep(Pool::TICK_DELAY); //mandatory
            //echo "\nnum={$num}\n";
            $this->tick(); // mandatory!
            $this->tidy(); // mandatory!
        }
    }

    /**
     * start new processes if needed
     *
     * @return int num of job
     */
    public function tick() {
        if (($this->countPool() < $this->max) && $this->countJob()) {
            $job = $this->getJob();
            if ($job) {
                $szal = new Process($this->worker);
                $szal->setLifeTime(4); //NOTICE
                $this->pool[] = $szal;
                $szal->start($job);
            }
        }
        return $this->countJob();
    }

    /**
     * remove closed instance from pool
     *
     */
    public function tidy() {
        foreach ($this->pool as $i => $szal) {
            if (!$szal->isAlive()) {
                unset($this->pool[$i]);
            } else {
                $pid = $szal->getPid();
                //$timeout = $szal->timeout();
                //var_dump($timeout);
                if ($szal->timeout()) {
                    print_r("\npid=>{$pid} is timeout and try to kill it..\n");
                    $szal->kill(SIGKILL);
                    //sleep(1);
                }
            }
        }

        return $this->countPool();
    }

    /**
     * count all alive instances
     *
     * @return array of instance
     */
    public function countPool() {
        return count($this->pool);
    }

    /**
     *  fetch a job
     *
     * @return mixed job
     */
    public function getJob() {
        return $this->stack->pop();
    }

    /**
     * add job
     *
     * @param mixed $job
     * @return int jobs size
     */
    public function setJob($job) {
        if (empty($job)) {
            return false;
        }
        $this->stack->push($job);
        return $this->countJob();
    }

    /**
     * add job
     *
     * @param mixed $jobs
     * @return int jobs size
     */
    public function addJobs($jobs) {
        if (empty($jobs)) {
            return false;
        }
        foreach ($jobs as $job) {
            $this->stack->push($job);
        }
        return $this->countJob();
    }

    /**
     * count job
     *
     * @return int num of job
     */
    public function countJob() {
        return $this->stack->size();
    }

}
