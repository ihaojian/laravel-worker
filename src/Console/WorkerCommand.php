<?php

namespace Laravel\Worker\Console;

use Laravel\Worker\Http as HttpServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class WorkerCommand extends Command
{

    protected $config = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker
                            {action=start : start|stop|restart|reload|status|connections}
                            {--H|host= : the host of workerman server.}
                            {--p|port= : the port of workerman server.}
                            {--d|daemon : Run the workerman server in daemon mode.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Workerman HTTP Server for Laravel';

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

        if ('start' == $action) {
            $this->line('Starting Workerman http server...');
        }

        $this->config = Config::get("worker");

        if (isset($this->config['context'])) {
            $context = $this->config['context'];
            unset($this->config['context']);
        } else {
            $context = [];
        }

        $host = $this->getHost();
        $port = $this->getPort();

        $worker = new HttpServer($host, $port, $context);

        if (empty($this->config['pidFile'])) {
            $this->config['pidFile'] = App::storagePath() . '/app/worker.pid';
        }

        // 避免pid混乱
        $this->config['pidFile'] .= '_' . $port;

        $worker->setBasePath(App::basePath());

        // 应用设置
        if (!empty($this->config['app_init'])) {
            $worker->appInit($this->config['app_init']);
            unset($this->config['app_init']);
        }

        if ($this->hasOption("daemon")) {
            $worker->setStaticOption('daemonize', true);
        }

        // 开启HTTPS访问
        if (!empty($this->config['ssl'])) {
            $this->config['transport'] = 'ssl';
            unset($this->config['ssl']);
        }

        // 设置网站目录
        if (empty($this->config['root'])) {
            $this->config['root'] = App::basePath() . '/public';
        }

        $worker->setRoot($this->config['root']);
        unset($this->config['root']);

        // 设置文件监控
//        if (DIRECTORY_SEPARATOR !== '\\' && (App::isDebug() || !empty($this->config['file_monitor']))) {
//            $interval = $this->config['file_monitor_interval'] ?? 2;
//            $paths    = !empty($this->config['file_monitor_path']) ? $this->config['file_monitor_path'] : [App::getAppPath(), App::getConfigPath()];
//            $worker->setMonitor($interval, $paths);
//            unset($this->config['file_monitor'], $this->config['file_monitor_interval'], $this->config['file_monitor_path']);
//        }

        // 全局静态属性设置
        foreach ($this->config as $name => $val) {
            if (in_array($name, ['stdoutFile', 'daemonize', 'pidFile', 'logFile'])) {
                $worker->setStaticOption($name, $val);
                unset($this->config[$name]);
            }
        }

        // 设置服务器参数
        $worker->option($this->config);

        if (DIRECTORY_SEPARATOR == '\\') {
            $this->line("You can exit with <info>`CTRL-C`</info>");
        }

        $worker->start();
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

    protected function getPort(string $default = '2346')
    {
        if ($this->option('port')) {
            $port = $this->option('port');
        } else {
            $port = !empty($this->config['port']) ? $this->config['port'] : $default;
        }

        return $port;
    }
}
