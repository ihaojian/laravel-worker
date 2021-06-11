<?php


namespace Laravel\Worker\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Laravel\Worker\Server as WorkerServer;
use Workerman\Worker;

class ServerCommand extends Command
{
    protected $config = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:server
                            {action=start : start|stop|restart|reload|status|connections}
                            {--H|host= : the host of workerman server.}
                            {--p|port= : the port of workerman server.}
                            {--d|daemon : Run the workerman server in daemon mode.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Workerman Server for Laravel';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');
        if (DIRECTORY_SEPARATOR !== '\\') {
            if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
                $this->error("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
                return false;
            }

            global $argv;
            array_shift($argv);
            array_shift($argv);
            array_unshift($argv, 'artisan', $action);
        } elseif ('start' != $action) {
            $this->error("<error>Not Support action:{$action} on Windows.</error>");
            return false;
        }

        $this->config = Config::get("worker_server");

        if ('start' == $action) {
            $this->line('Starting Workerman server...');
        }

        // 自定义服务器入口类
        if (!empty($this->config['worker_class'])) {
            $class = (array) $this->config['worker_class'];

            foreach ($class as $server) {
                $this->startServer($server);
            }

            // Run worker
            Worker::runAll();
            return;
        }

        if (!empty($this->config['socket'])) {
            $socket            = $this->config['socket'];
            list($host, $port) = explode(':', $socket);
        } else {
            $host     = $this->getHost();
            $port     = $this->getPort();
            $protocol = !empty($this->config['protocol']) ? $this->config['protocol'] : 'websocket';
            $socket   = $protocol . '://' . $host . ':' . $port;
            unset($this->config['host'], $this->config['port'], $this->config['protocol']);
        }

        if (isset($this->config['context'])) {
            $context = $this->config['context'];
            unset($this->config['context']);
        } else {
            $context = [];
        }

        $worker = new Worker($socket, $context);

        if (empty($this->config['pidFile'])) {
            $this->config['pidFile'] = App::storagePath() . '/app/worker.pid';
        }

        // 避免pid混乱
        $this->config['pidFile'] .= '_' . $port;

        // 开启守护进程模式
        if ($this->hasOption("daemon")) {
            Worker::$daemonize = true;
        }

        if (!empty($this->config['ssl'])) {
            $this->config['transport'] = 'ssl';
            unset($this->config['ssl']);
        }

        // 设置服务器参数
        foreach ($this->config as $name => $val) {
            if (in_array($name, ['stdoutFile', 'daemonize', 'pidFile', 'logFile'])) {
                Worker::${$name} = $val;
            } else {
                $worker->$name = $val;
            }
        }

        // Run worker
        Worker::runAll();
    }

    protected function startServer(string $class)
    {
        if (class_exists($class)) {
            $worker = new $class;
            if (!$worker instanceof WorkerServer) {
                $this->line("<error>Worker Server Class Must extends \\Laravel\\Worker\\Server</error>");
            }
        } else {
            $this->line("<error>Worker Server Class Not Exists : {$class}</error>");
        }
    }

    protected function getHost(string $default = '0.0.0.0')
    {
        if ($this->option('host')) {
            $host = $this->option('host');
        } else {
            $host = !empty($this->config['host']) ? $this->config['host'] : $default;
        }

        return $host;
    }

    protected function getPort(string $default = '2345')
    {
        if ($this->option('port')) {
            $port = $this->option('port');
        } else {
            $port = !empty($this->config['port']) ? $this->config['port'] : $default;
        }

        return $port;
    }
}
