<?php
/*
 * tasks to a queue.
 */

class Stack {

    public $bucket = array(); // parameters to pass to $worker

    /**
     * push to bucket
     *
     * @param mixed $arg parameter to pass to $callable
     * @return int stack size
     */
    public function push($arg) {
        $this->bucket[] = $arg;
        return $this->size();
    }

    /**
     *  pop job
     *
     * @return int stack size
     */
    public function pop() {
        return array_shift($this->bucket);
    }

    /**
     * return stack size with waiting jobs
     *
     * @return int
     */
    public function size() {
        return count($this->bucket);
    }

    /**
     * remove all remaining jobs (empty queue)
     *
     * @return int number of removed jobs
     */
    public function flush() {
        $this->bucket = array();
        return $this->size();
    }

}
