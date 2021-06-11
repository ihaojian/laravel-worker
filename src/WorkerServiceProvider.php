<?php


namespace Laravel\Worker;


use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Support\ServiceProvider;
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
        $worker = realpath($raw = __DIR__.'/../config/worker.php') ?: $raw;
        $server=realpath($raw = __DIR__.'/../config/server.php') ?: $raw;

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$worker => config_path('worker.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('worker');
        }

        $this->publishes([$server => config_path('worker_server.php')]);

        $this->mergeConfigFrom($worker, 'worker');
        $this->mergeConfigFrom($server, 'worker_server');
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

        $this->commands(['command.worker','command.worker:server']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.worker','command.worker:server'];
    }
}
