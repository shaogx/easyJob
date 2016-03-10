<?php
/**
 *@author
 * Daemon
 *
 * @TODO
 * 1,限制进程数(防止fork太多).
 * 2,限制执行时间(防止进程异常，长时间挂起).
 * 3,
 * 4,
 *
 */
class Worker extends Command {

	const DELAY = 50000; //0.005s

	private $daemon = false;

	private $title = 'php-daemon-xidibuy';
	private $user = 'nobody'; //设置运行的用户 默认情况下nobody

	//['worker'=>['class','method'] or 'callback',
	//  'fork'=>1/N, default 1
	//]
	private $workers = []; //workers
	private $workerPool = []; //worker池
	private $childPool = []; //进程池
	private $forkMax = []; //最大进程数

	private $lifeTime = 3600; //进程生命周期

	public function __construct($title = '', $daemon = false) {
		parent::__construct();

		$title = trim($title);
		if (!empty($title)) {
			$this->title = $title;
		}

		$this->daemon = $daemon;

		$this->pidFile = $this->infoDir . $this->title . ".pid";
		$this->output = $this->infoDir . $this->title . ".log";

		if (false === is_dir($this->infoDir)) {
			if (false === mkdir($this->infoDir, 0777, true)) {
				die("\nfailed to create dir({$this->infoDir})\n");
			}
		}

		if (false === file_exists($this->output)) {
			if (false === touch($this->output)) {
				die("\nfailed to create dir({$this->output})\n");
			}
		}

		$this->initSignal();
	}

	/**
	 * setup worker and validate
	 * $workerInfo
	 *
	 * 'someMethod'
	 * 'STATICCLASS::METHOD'
	 * array($Object, 'Method');
	 *
	 * @return
	 */
	public function setup($workerInfo = null) {
		if (empty($workerInfo)) {
			$this->_log("the worker you set is empty");
			return null;
		}

		if (is_array($workerInfo)) {
			list($worker, $fork) = $workerInfo;
		} else {
			$worker = $workerInfo;
			$fork = 1;
		}

		$ident = $this->getIdent($worker);
		if (is_callable($worker, false, $called_name)) {
			$this->workers[$ident] = $workerInfo;
			//$this->_log("success to setup worker ({$ident})!");
		} else {
			$this->_log("failed to setup worker ({$ident})!");
		}

		unset($workerInfo);
	}

	/**
	 * @return
	 */
	public function init() {
		if (empty($this->workers)) {
			$this->_log("no worker to init.");
			return null;
		}

		foreach ($this->workers as $ident => $workerInfo) {
			if (is_array($workerInfo)) {
				list($worker, $fork) = $workerInfo;
			} else {
				$worker = $workerInfo;
				$fork = 1;
			}

			$this->workerPool[$ident] = $worker;
			$this->childPool[$ident] = array();
			$this->forkMax[$ident] = $fork;
		}
	}

	/**
	 * get ident of worker
	 *
	 * @return boolean
	 */
	public function getIdent($worker) {
		if (empty($worker)) {
			return false;
		}
		if (is_array($worker)) {
			list($o, $m) = $worker;
			$ident = is_object($o) ? get_class($o) . '->' . $m : $o . '->' . $m;
		} else {
			$ident = $worker;
		}
		return $ident;
	}

	/**
	 * fork process with job
	 *
	 * @return
	 */
	public function spawn() {

		$worker = $this->ready();
		$ident = $this->getIdent($worker);

		//use Process
		$p = new Process($worker);
		$p->start();
		$pid = $p->getPid();
		if ($pid) {
			$this->childPool[$ident][$pid] = $pid;
		}

		// //@TODO
		// $pid = pcntl_fork();

		// if ($pid == -1) {
		// } elseif ($pid) {
		//     $this->childPool[$ident][$pid] = $pid;
		// } else {

		//     //@TODO
		//     //$this->setOwner($name = 'www');
		//     //$this->setTitle($title);

		//     $this->childPool[$ident] = [];

		//     //$cid = getmypid();
		//     //echo "\ngetmypid={$cid}\n";

		//     // 这个符号表示恢复系统对信号的默认处理
		//     pcntl_signal(SIGTERM, SIG_DFL);
		//     pcntl_signal(SIGCHLD, SIG_DFL);

		//     call_user_func_array($worker, array());
		//     //call_user_func_array($worker);
		//     exit(0);
		// }
	}

	/**
	 * get ready worker for forking
	 *
	 * @return
	 */
	private function ready() {
		foreach ($this->workerPool as $ident => $worker) {
			if (false === isset($this->childPool[$ident]) || count($this->childPool[$ident]) < $this->forkMax[$ident]) {
				return $worker;
			}
		}
		//return false;
	}

	/**
	 * Removes closed process from childPool
	 *
	 */
	private function tidyUp() {
		while ($chkPid = pcntl_waitpid(-1, $status, WNOHANG)) {
			//echo "\nchkPid={$chkPid}\n";
			if ($chkPid == -1) {
				//echo "\nerror\n";
				$this->childPool = array();
				break;
			} else {
				//echo "\nchkPid={$chkPid}\n";
				foreach ($this->childPool as $ident => $children) {
					if ($children && isset($children[$chkPid])) {
						unset($this->childPool[$ident][$chkPid]);
					}
				}
			}
		}
	}

	/**
	 *开始开启进程
	 */
	public function flying() {
		if (empty($this->workerPool)) {
			die("\nset worker please!\n");
		}

		$this->_log("the process is running now");

		//信号分发
		if (function_exists('pcntl_signal_dispatch')) {
			pcntl_signal_dispatch();
		}

		while (true) {

			if (count($this->childPool, COUNT_RECURSIVE) - count($this->childPool) < array_sum($this->forkMax)) {
				$this->spawn();
			}

			if (false === $this->daemon) {
				//exit when finished non-daemon mode
				while (count($this->childPool)) {
					$this->tidyUp();
					usleep(self::DELAY);
				}
				$this->stop = true;
			} else {
				$this->tidyUp();
				//$this->_log("\ntidyUp.\n");
			}

			if (true === $this->stop) {
				$this->_log("\nstop the process after all finished.\n");
				break;
			}

			usleep(self::DELAY);
		}
		exit(0);
	}

}

?>