<?php

namespace AnourValar\EloquentNotification\Providers;

use Illuminate\Support\ServiceProvider;

class AnourValarEloquentNotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // config
        $this->mergeConfigFrom(__DIR__.'/../resources/config/eloquent_notification.php', 'eloquent_notification');

        // bindings
        foreach (config('eloquent_notification.bindings') as $interface => $implementation) {
            $this->app->singleton($interface, function ($app, $arguments = []) use ($implementation) {
                return new $implementation['bind'](...$arguments);
            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config
        $this->publishes([__DIR__.'/../resources/config/eloquent_notification.php' => config_path('eloquent_notification.php')], 'config');

        // migrations
        //$this->loadMigrationsFrom(__DIR__.'/../resources/database/migrations');
        $this->publishes([__DIR__.'/../resources/database/migrations/' => database_path('migrations')], 'migrations');

        // models
        $this->publishes([__DIR__.'/../resources/stubs/' => app_path()], 'models');

        // langs
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang/', 'eloquent_notification');
        $this->publishes([__DIR__.'/../resources/lang/' => lang_path('vendor/eloquent_notification')]);

        // views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'eloquent_notification');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/eloquent_notification'),
        ]);

        // extends - telegram channel
        \Notification::extend('telegram', function () {
            return \App::make(\AnourValar\EloquentNotification\Drivers\TelegramChannel::class);
        });

        // extends - sms channel
        \Notification::extend('sms', function () {
            return \App::make(\AnourValar\EloquentNotification\Drivers\SmsChannel::class);
        });
    }
}
