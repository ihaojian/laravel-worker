<?php


namespace Laravel\Worker;


use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Worker\Console\WorkerCommand;

class WorkerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $source = realpath($raw = __DIR__.'/../config/worker.php') ?: $raw;

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('worker.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('worker');
        }

        $this->mergeConfigFrom($source, 'worker');
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

        $this->commands(['command.worker']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.worker'];
    }
}
