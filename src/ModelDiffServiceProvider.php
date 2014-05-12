<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff;

use Illuminate\Support\ServiceProvider;

class ModelDiffServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/model-diff.php' => config_path('model-diff.php'),
            ], 'model-diff-config');
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/model-diff.php',
            'model-diff',
        );

        $this->app->singleton(ModelDiff::class, function (): ModelDiff {
            return new ModelDiff;
        });

        $this->app->alias(ModelDiff::class, 'model-diff');
    }
}
