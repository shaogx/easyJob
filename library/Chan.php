<?php

/*
 * one worker per channel kill tasks from a stack one by one.
 *
 */

require_once "Stack.php";
require_once "Queue.php";
require_once "Process.php";

class Chan {
	const TICK_DELAY = 50000; //0.0005s delay after tick, in millisecs

	public $chan = array(); // chan
	public $max = 1; //num of chan

	/**
	 * Constructor
	 *
	 * @param string $worker function name
	 * @param mixed $chanData
	 */

	public function __construct($worker, $chanData = null) {
		if ($chanData && is_array($chanData)) {
			foreach ($chanData as $chanId => $jobs) {
				$Q = new Queue($worker);
				$Q->addJobs($jobs);
				$this->chan[$chanId] = $Q;
			}
		}
	}

	/**
	 *   waiting
	 *
	 *    @return int  size
	 */

	public function waiting() {
		while ($num = $this->countChan()) {
			usleep(Chan::TICK_DELAY); // mandatory!
			//echo $num;
			$this->tick(); // mandatory!
		}
	}

	/**
	 *
	 *    @return int num of chan pool
	 */

	public function tick() {
		foreach ($this->chan as $chanId => $Q) {
			$Q->tick();
			if ($num = $Q->countPool()) {
				$Q->tick();
				$Q->tidy();
			} else {
				unset($this->chan[$chanId]);
			}
		}
		return $this->countChan();
	}

	/**
	 * count all alive instances
	 *
	 * @return array of instance
	 */
	public function countChan() {
		return count($this->chan);
	}

}
