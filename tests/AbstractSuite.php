<?php

namespace AnourValar\EloquentNotification\Tests;

use Illuminate\Database\Schema\Blueprint;

abstract class AbstractSuite extends \Orchestra\Testbench\TestCase
{
    use \AnourValar\EloquentValidation\Tests\ValidationTrait;

    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../src/resources/database/migrations');
        $this->setUpDatabase($this->app);

        config(['notification.model' => \AnourValar\EloquentNotification\UserNotification::class]);
        config(['notification.trigger' => [
            'foo' => ['bind' => \AnourValar\EloquentNotification\Tests\FooNotification::class, 'title' => 'Foo', 'channels' => ['mail', 'telegram', 'sms'], 'is_public' => true],
            'bar' => ['bind' => \AnourValar\EloquentNotification\Tests\BarNotification::class, 'title' => 'Bar', 'channels' => ['mail', 'telegram', 'sms'], 'is_public' => true],
            'baz' => ['bind' => \AnourValar\EloquentNotification\Tests\BazNotification::class, 'title' => 'Baz', 'channels' => ['mail', 'telegram', 'sms'], 'is_public' => true],
            'foobar' => ['bind' => \AnourValar\EloquentNotification\Tests\FoobarNotification::class, 'title' => 'Foobar', 'channels' => ['mail', 'telegram', 'sms'], 'is_public' => false],
        ]]);

        \Illuminate\Database\Eloquent\Factories\Factory::guessModelNamesUsing(fn () => \AnourValar\EloquentNotification\UserNotification::class);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function setUpDatabase(\Illuminate\Foundation\Application $app)
    {
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \AnourValar\EloquentValidation\Providers\EloquentValidationServiceProvider::class,
            \AnourValar\LaravelAtom\Providers\LaravelAtomServiceProvider::class,
            \AnourValar\EloquentNotification\Providers\AnourValarEloquentNotificationServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Atom' => \AnourValar\LaravelAtom\Facades\AtomFacade::class,
        ];
    }
}
