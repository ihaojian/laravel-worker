<?php


namespace Laravel\Worker\Console;


use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Workerman\Worker;

class GatewayWorkerCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:gateway
                            {action=start : start|stop|restart|reload|status|connections}
                            {--H|host= : the host of workerman server.}
                            {--p|port= : the port of workerman server.}
                            {--d|daemon : Run the workerman server in daemon mode.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GatewayWorker Server for Laravel';

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
        } else {
            $this->error("GatewayWorker Not Support On Windows.");
            exit(1);
        }

        if ('start' == $action) {
            $this->line('Starting GatewayWorker server...');
        }

        $config = Config::get("gateway_worker");

        if ($this->hasOption("host")) {
            $host = $this->option("host");
        }else{
            $host = !empty($option['host']) ? $option['host'] : '0.0.0.0';
        }

        if ($this->hasOption("port")) {
            $host = $this->option("port");
        }else{
            $host = !empty($option['port']) ? $option['port'] : '2347';
        }

        $this->start($host, $port, $this->config);
    }

    /**
     * 启动
     * @access public
     * @param  string   $host 监听地址
     * @param  integer  $port 监听端口
     * @param  array    $option 参数
     * @return void
     */
    protected function start(string $host,int $port,array $option=[])
    {
        $registerAddress = !empty($option['registerAddress']) ? $option['registerAddress'] : '127.0.0.1:1236';

        if (!empty($option['register_deploy'])) {
            // 分布式部署的时候其它服务器可以关闭register服务
            // 注意需要设置不同的lanIp
            $this->register($registerAddress);
        }

        // 启动businessWorker
        if (!empty($option['businessWorker_deploy'])) {
            $this->businessWorker($registerAddress, $option['businessWorker'] ?? []);
        }

        // 启动gateway
        if (!empty($option['gateway_deploy'])) {
            $this->gateway($registerAddress, $host, $port, $option);
        }

        Worker::runAll();
    }

    /**
     * 启动register
     * @access public
     * @param  string   $registerAddress
     * @return void
     */
    public function register(string $registerAddress)
    {
        // 初始化register
        new Register('text://' . $registerAddress);
    }

    /**
     * 启动businessWorker
     * @access public
     * @param  string   $registerAddress registerAddress
     * @param  array    $option 参数
     * @return void
     */
    public function businessWorker(string $registerAddress, array $option = [])
    {
        // 初始化 bussinessWorker 进程
        $worker = new BusinessWorker();

        $this->setOption($worker, $option);

        $worker->registerAddress = $registerAddress;
    }

    /**
     * 启动gateway
     * @access public
     * @param  string  $registerAddress registerAddress
     * @param  string  $host 服务地址
     * @param  integer $port 监听端口
     * @param  array   $option 参数
     * @return void
     */
    public function gateway(string $registerAddress, string $host, int $port, array $option = [])
    {
        // 初始化 gateway 进程
        if (!empty($option['socket'])) {
            $socket = $option['socket'];
            unset($option['socket']);
        } else {
            $protocol = !empty($option['protocol']) ? $option['protocol'] : 'websocket';
            $socket   = $protocol . '://' . $host . ':' . $port;
            unset($option['host'], $option['port'], $option['protocol']);
        }

        $gateway = new Gateway($socket, $option['context'] ?? []);

        // 以下设置参数都可以在配置文件中重新定义覆盖
        $gateway->name                 = 'Gateway';
        $gateway->count                = 4;
        $gateway->lanIp                = '127.0.0.1';
        $gateway->startPort            = 2000;
        $gateway->pingInterval         = 30;
        $gateway->pingNotResponseLimit = 0;
        $gateway->pingData             = '{"type":"ping"}';
        $gateway->registerAddress      = $registerAddress;

        // 全局静态属性设置
        foreach ($option as $name => $val) {
            if (in_array($name, ['stdoutFile', 'daemonize', 'pidFile', 'logFile'])) {
                Worker::${$name} = $val;
                unset($option[$name]);
            }
        }

        $this->setOption($gateway, $option);
    }

    /**
     * 设置参数
     * @access protected
     * @param  Worker $worker Worker对象
     * @param  array  $option 参数
     * @return void
     */
    protected function setOption(Worker $worker, array $option = [])
    {
        // 设置参数
        if (!empty($option)) {
            foreach ($option as $key => $val) {
                $worker->$key = $val;
            }
        }
    }
}
