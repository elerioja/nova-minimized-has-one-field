<?php

namespace OptimistDigital\NovaHasoneFieldMinimizer;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        $this->publishes([
            __DIR__ . '/../config/nova-minimized-has-one-field.php' => config_path('nova-minimized-has-one-field.php'),
        ], 'config');


        Nova::serving(function (ServingNova $event) {
            Nova::script('nova-hasone-field-minimizer', __DIR__ . '/../dist/js/field.js');
            Nova::style('nova-hasone-field-minimizer', __DIR__ . '/../dist/css/field.css');
        });

        Nova::provideToScript([
            'resource_property' =>  config('nova-hasone-field-minimizer.resource_property', 'id'),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
