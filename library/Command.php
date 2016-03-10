<?php

class Command {

    private $debug = true;

    public $stop = false;

    public $background = false;

    public $infoDir = '/tmp/';

    private $title = 'php-daemon';

    public $pidFile = '/tmp/php-daemon.pid';

    public $output = '/tmp/php-daemon.log';

    private $gc = null;

    // D 无法中断的休眠状态（通常 IO 的进程）；
    // R 正在运行可中在队列中可过行的；
    // S 处于休眠状态；
    // T 停止或被追踪；
    // W 进入内存交换（从内核2.6开始无效）；
    // X 死掉的进程（从来没见过）；
    // Z 僵尸进程；
    public $okStats = array('R', 'S', 'D');

    public function __construct() {

        $this->checkPcntl();

        // Enable PHP 5.3 garbage collection
        if (function_exists('gc_enable')) {
            gc_enable();
            $this->gc = gc_enabled();
        }

    }

    /**
     * start
     */
    public function start() {

        // if running
        if (true === $this->checkRunning()) {
            $line = "\nThe daemon is running already!\n";
            echo $line;
            //$this->_log($line);
            exit(0);
        }

        //init worker
        $this->init();

        //sent to background
        if ($this->background) {
            $this->background();
        }

        //start to run
        $this->flying();
        exit(0);
    }

    /**
     * stop
     */
    public function stop() {
        $this->stop = true;
        exit(0);
    }

    //restart
    public function restart() {
        //$this->stop = true;
        $this->kill();
        $this->start();
        exit(0);
    }

    /**
     * kill
     */
    public function kill() {
        if (file_exists($this->pidFile)) {
            echo "\n{$this->pidFile}\n";
            $pid = intval(file_get_contents($this->pidFile));
            if ($pid) {
                posix_kill($pid, SIGKILL);
                $this->_log("try to delete " . $this->pidFile);
                unlink($this->pidFile);
            } else {
                system('kill -9' . getmypid());
            }
            $this->_log("daemon killed");
        } else {
            $this->_log("cannot locate " . $this->pidFile);
            system('kill -9' . getmypid());
        }
        exit(0);
    }

    //status
    public function status() {
        // if running
        if (true === $this->checkRunning()) {
            $line = "\nThe daemon is running!\n";
        } else {
            $line = "\nThe daemon is not running !\n";
        }
        echo $line;
        exit(0);
    }

    /**
     * check if pcntl is supported
     *
     * @return
     */
    public function checkPcntl() {

        set_time_limit(0);

        // 只允许在cli下面运行
        if (php_sapi_name() != "cli") {
            die("only run in command line mode\n");
        }

        if (!function_exists('pcntl_signal_dispatch')) {
            // PHP < 5.3 uses ticks to handle signals instead of pcntl_signal_dispatch
            // call sighandler only every 10 ticks
            declare (ticks = 10);
        }

        // Make sure PHP has support for pcntl
        if (!function_exists('pcntl_signal')) {
            $message = 'PHP does not appear to be compiled with the PCNTL extension.';
            $this->_log($message);
            throw new Exception($message);
        }

    }

    /**
     * init signal
     *
     * @return
     */
    public function initSignal() {
        //信号处理
        //pcntl_signal(SIGCHLD, array(&$this, "signal"), false);
        pcntl_signal(SIGHUP, array(&$this, "signal"));
        pcntl_signal(SIGTERM, array(&$this, "signal"));
        pcntl_signal(SIGINT, array(&$this, "signal"));
        pcntl_signal(SIGQUIT, array(&$this, "signal"));
    }

    /**
     * signal handler
     *
     * @return
     */
    public function signal($signo) {

        switch ($signo) {

        //用户自定义信号
        case SIGUSR1: //busy
            echo "\nBUSY SIGUSR1\n";
            break;
        //child finished
        case SIGCHLD:
            echo "\ntidy up with SIGCHLD\n";
            $this->tidyUp();
            break;
        //CTRL+C
        case SIGINT:
        case SIGTERM:
            echo "\ntry to stop it...\n";
            //$this->stop();
            posix_kill(0, SIGTERM);
            exit(0);
            break;
        //KILL
        case SIGKILL:
            $this->kill();
            break;
        //hup or quit
        case SIGHUP:
        case SIGQUIT:
            echo "\ntry to kill it and quit...\n";
            $this->kill();
            break;
        default:
            echo "\ndefalut SIGNAL{$signo}\n";
            break;
        }

    }

    /**
     * set to background and create pid
     *
     * @return boolean
     */
    public function background() {

        global $stdin, $stdout, $stderr;
        global $argv;

        umask(0); //把文件掩码清0

        if ($pid = pcntl_fork() != 0) {
            //$this->_log("\n是父进程，父进程退出!\n");
            //是父进程，父进程退出
            exit();
        }

        //设置新会话组长，脱离终端
        if (posix_setsid() == -1) {
            $line = "\nfailed to run background (setsid fail)!\n";
            //echo $line;
            $this->_log($line);
            exit($line);
        }

        $this->_log("\nsuccess to run background now!\n");

        $this->setTitle($this->title);

        if ($pid = pcntl_fork() != 0) {
            //$this->_log("\n是第一子进程，结束第一子进程!\n");
            //是第一子进程，结束第一子进程
            exit();
        }

        // pcntl_signal(SIGHUP, SIG_IGN);
        // pcntl_signal(SIGTTIN, SIG_IGN);
        // pcntl_signal(SIGTTOU, SIG_IGN);
        // pcntl_signal(SIGQUIT, SIG_IGN);
        // pcntl_signal(SIGINT, SIG_IGN);
        // pcntl_signal(SIGTERM, SIG_IGN);

        chdir("/tmp/"); //改变工作目录

        //关闭打开的文件描述符
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        $stdin = fopen('/dev/null', 'r');
        //$stdin = fopen($this->output, 'r');
        $stdout = fopen($this->output, 'a');
        $stderr = fopen($this->output, 'a');
        //$stderr = fopen($this->error, 'a');

        // create pid file & save pid to it
        $this->createPidfile();
    }

    /**
     * check the process if running
     *
     * @return boolean
     */
    public function checkRunning() {
        if (file_exists($this->pidFile)) {
            echo "\n{$this->pidFile}\n";
            $pid = intval(file_get_contents($this->pidFile));
            echo "\npid=>{$pid}\n";
            $state = $this->statInfo($pid, $k = 2);
            if ($state && in_array($state, $this->okStats)) {
                return true;
            } else {
                $this->_log("pid ({$pid}) is inactive and delete file ($this->pidFile)");
                unlink($this->pidFile);
            }
        }
        return false;
    }

    /**
     * create pid file
     *
     * @return boolean
     */
    public function createPidfile() {
        if (!is_dir($this->infoDir)) {
            mkdir($this->infoDir);
        }

        $fp = fopen($this->pidFile, 'w') or die("\ncannot create pid file\n");

        $pid = posix_getpid();
        fwrite($fp, $pid);
        fclose($fp);
        $this->_log("pid file ($this->pidFile) created");
    }

    /**
     * get stat of pid from /proc/PID/stat
     * 0 => PID
     * 1 => cmd
     * 2 => state of process
     * 3 => ...
     * 41=> ...
     *
     * @return boolean
     */
    public function runState($pid) {
        if ($pid) {
            return $this->statInfo($pid, 2);
        }
        return false;
    }

    /**
     * get stat of pid from /proc/PID/stat
     * 0 => PID
     * 1 => cmd
     * 2 => state of process
     * 3 => ...
     * 41=> ...
     *
     * @return boolean
     */
    public function statInfo($pid, $k = 2) {
        if ($k < 0 || $k > 41) {
            return false;
        }

        $pid = intval($pid);
        if ($pid) {

            $content = null;
            $file = "/proc/{$pid}/stat";
            if (is_file($file)) {
                $content = file_get_contents($file);
            }

            if ($content) {
                $stats = explode(' ', $content);
                return $stats[$k];
            }
            return false;
        }
        return false;
    }

    /**
     * set title of process
     *
     * @return
     */
    public function setTitle($title) {
        if (!empty($title)) {
            return false;
        }

        $this->title = $title;
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($this->title);
        }
    }

    /**
     * set owner(user,group) of process
     *
     * @return
     */
    public function setOwner($name) {
        $set = false;
        if (empty($name)) {
            return true;
        }
        $user = posix_getpwnam($name);
        if ($user) {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $set = posix_setuid($uid);
            posix_setgid($gid);
        }

        if (!$set) {
            $this->_log("cannot change owner ({$name})\n");
        }

        return $set;
    }

    /**
     * display info
     *
     * @return
     */
    public function _log($line, $flag = true) {
        if ($flag) {
            printf("%s\t%d\t%d\t%s\n", date('Y-m-d H:i:s'), posix_getpid(), posix_getppid(), $line);
        } else {
            printf("\n%s\n", $line);
        }
    }

    public function help() {
        $line = "\nusage: start [bg] | stop | status | help \n";
        if ($this->background) {
            echo $line;
        } else {
            $this->_log($line, false);
        }
    }

    public function bootstrap($argv) {
        $argv = (array) $argv;
        //print_r($argv);

        //if send to background
        $this->background = (isset($argv[2]) && trim($argv[2]) == 'bg') ? true : false;
        //action
        $action = isset($argv[1]) ? trim($argv[1]) : '';

        switch ($action) {
        case 'start':
            $this->start();
            break;
        // case 'restart':
        //     $this->restart();
        case 'stop':
            //$this->stop();
            $this->kill();
            break;
        case 'status':
            $this->status();
            break;
        default:
            $this->help();
            break;
        }
    }

}