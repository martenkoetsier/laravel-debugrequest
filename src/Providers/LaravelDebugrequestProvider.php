<?php

namespace Martenkoetsier\LaravelDebugrequest\Providers;

use Illuminate\Support\ServiceProvider;
use Martenkoetsier\LaravelDebugrequest\DebugRequest;

class LaravelDebugrequestProvider extends ServiceProvider {
    /**
     * Bootstrap console command
     * 
     * @return void
     */
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/debugrequest.php' => config_path('debugrequest.php'),
            ], 'config');
        }
        if (!$this->app->runningUnitTests() && !$this->app->runningInConsole()) {
            foreach (config('debugrequest.middleware_groups', ['web', 'api']) as $group) {
                $this->app['router']->pushMiddlewareToGroup($group, DebugRequest::class);
            }

            // $this->app['router']->aliasMiddleware('debugrequest', DebugRequest::class);
        }
    }
}
