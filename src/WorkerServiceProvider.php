<?php


namespace Laravel\Worker;


use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Worker\Console\GatewayWorkerCommand;
use Laravel\Worker\Console\WorkerCommand;
use Laravel\Worker\Console\ServerCommand;

class WorkerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootWorker();
        $this->bootServer();
        $this->bootGateway();
    }

    private function bootWorker()
    {
        $worker = realpath($raw = __DIR__.'/../config/worker.php') ?: $raw;
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$worker => config_path('worker.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('worker');
        }
        $this->mergeConfigFrom($worker, 'worker');
    }

    private function bootServer()
    {
        $server=realpath($raw = __DIR__.'/../config/server.php') ?: $raw;
        $this->publishes([$server => config_path('worker_server.php')]);
        $this->mergeConfigFrom($server, 'worker_server');
    }

    private function bootGateway()
    {
        $gateway=realpath($raw = __DIR__.'/../config/gateway.php') ?: $raw;
        $this->publishes([$gateway => config_path('gateway_worker.php')]);
        $this->mergeConfigFrom($gateway, 'gateway_worker');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.worker', function () {
            return new WorkerCommand;
        });

        $this->app->singleton('command.worker:server', function () {
            return new ServerCommand;
        });

        $this->app->singleton('command.worker:gateway', function () {
            return new GatewayWorkerCommand;
        });

        $this->commands(['command.worker','command.worker:server','command.worker:gateway']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.worker','command.worker:server','command.worker:gateway'];
    }
}
