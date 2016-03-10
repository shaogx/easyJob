<?php
//namespace Shop\Crontab;

/**
 * @version 1.0  2014-11-13
 * @author shaogx
 */

define('VERSION', '1.0.0');

if (!defined('DEBUG')) {
	define("DEBUG", false);
}

if (!defined("ROOT_CRON")) {
	define("ROOT_CRON", realpath(__DIR__));
}

try {

	$line = "start pid:[" . posix_getppid() . "->" . getmypid() . "] memory:[" . _memory() . "] at " . date('Y-m-d H:i:s', time()) . "\n";
	echo ($line); //echo or file

	// load class
	include_once ROOT_CRON . '/library/Command.php';
	include_once ROOT_CRON . '/library/Worker.php';
	include_once ROOT_CRON . '/library/Process.php';
	include_once ROOT_CRON . '/library/Stack.php';
	include_once ROOT_CRON . '/library/Pool.php';
	include_once ROOT_CRON . '/library/Queue.php';
	include_once ROOT_CRON . '/library/Chan.php';

	function demoWorker() {
		//code goes here...
	}

	/**
	 * worker master - Pool
	 */
	function workerPool() {
		$start = microtime(true);
		echo "\nworker start at {$start}";

		$jobs = array();

		if ($jobs) {
			$wNum = 5; //进程数
			$jobCnt = count($jobs);

			$w = ceil($jobCnt / $wNum); //
			$pool = new Pool("demoWorker", $w);
			$pool->addJobs($jobs);
			$pool->waiting();

			$stop = microtime(true);
			$cost = $stop - $start;
			echo "\nworker {$jobCnt} jobs done at {$stop}";
			echo "\nworker cost time = {$cost}";
		} else {
			//for daemon mode
			echo "\nworker no job and sleep 5s";
			sleep(5);
		}

		$end = microtime(true);
		echo "\nworker end at {$end}";
	}

	/**
	 * worker master - Chan
	 */
	function workerChan() {
		$start = microtime(true);
		echo "\nworkerChan start at {$start}";

		$jobs = array(
			'0' => array('1', '2', '3'), //first chan
			'1' => array('1', '2', '3'), //second chan
			'2' => array('1', '2', '3'), //third chan
			//....
		);

		if ($jobs) {
			$chanCnt = count($jobs);
			if ($chanCnt && $jobs) {
				$jobCnt = count($jobs, COUNT_RECURSIVE);

				$chan = new Chan("demoWorker", $jobs);
				$chan->waiting();

				$stop = microtime(true);
				$cost = $stop - $start;
				echo "\nworkerChan {$jobCnt} jobs done at {$stop}";
				echo "\nworkerChan {$jobCnt} cost time = {$cost}";
			}
		} else {
			//for daemon mode
			echo "\nworkerChan no job and sleep 5s";
			sleep(5);
		}

		$end = microtime(true);
		echo "\nworkerChan end at {$end}";
	}

	$daemon = new Worker('demo', $daemon = true);
	$daemon->setup('workerPool');
	$daemon->setup('workerChan');
	$daemon->bootstrap($argv);

	$line = "end pid:[" . posix_getppid() . "->" . getmypid() . "] memory:[" . _memory() . "] at " . date('Y-m-d H:i:s', time()) . "\n";
	echo ($line); //echo or file
} catch (\Phalcon\Exception $e) {
	$line = 'error:time:' . date('Y-m-d H:i:s', time()) . ';' . $e->getMessage() . "\n";
	echo ($line); //echo or file
}
