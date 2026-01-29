<?php

namespace LaraSwagger;

use Illuminate\Support\ServiceProvider;
use LaraSwagger\Console\GenerateApiDocsJson;

class LaraSwaggerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laraswagger.php',
            'laraswagger'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laraswagger.php' => config_path('laraswagger.php'),
        ], 'laraswagger-config');

        if (config('laraswagger.ui.enabled', true)) {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraswagger');
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateApiDocsJson::class,
            ]);
        }
    }
}
