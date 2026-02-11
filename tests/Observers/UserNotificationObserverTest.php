<?php

namespace AnourValar\EloquentNotification\Tests\Observers;

use Tests\TestCase;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class UserNotificationObserverTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @return void
     */
    public function test_saving_delete()
    {
        \AnourValar\EloquentNotification\UserNotification::query()->delete();

        // Iteration 1
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => 1, 'channels' => null]);
        $this->assertSame(0, \AnourValar\EloquentNotification\UserNotification::count());

        // Iteration 2
        $model = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => 1, 'channels' => ['foo']]);
        $this->assertSame(1, \AnourValar\EloquentNotification\UserNotification::count());

        // Iteration 3
        $model->forceFill(['channels' => null])->save();
        $this->assertSame(0, \AnourValar\EloquentNotification\UserNotification::count());
    }
}
