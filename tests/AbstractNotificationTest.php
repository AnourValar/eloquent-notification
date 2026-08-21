<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class AbstractNotificationTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_via_soft_delete()
    {
        $user1 = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Database\Eloquent\SoftDeletes;
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user1->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'foo', 'channels' => ['sms', 'telegram']]);

        $user2 = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user2->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'foo', 'channels' => ['mail']]);

        // Iteration 1
        $this->assertSame(['sms', 'telegram'], (new FooNotification())->via($user1));

        // Iteration 2
        $user1->deleted_at = now();
        $this->assertSame([], (new FooNotification())->via($user1));

        // Iteration 3
        $user1->deleted_at = null;
        $this->assertSame(['sms', 'telegram'], (new FooNotification())->via($user1));

        // Iteration 4
        $user1->deleted_at = now();
        $this->assertSame([], (new FooNotification())->via($user1));

        // Iteration 5
        $this->assertSame(['mail'], (new FooNotification())->via($user2));

        // Iteration 6
        $user2->deleted_at = now();
        $this->assertSame(['mail'], (new FooNotification())->via($user2));
    }

    /**
     * @return void
     */
    public function test_via_duplicate()
    {
        $user1 = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user1->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'foo', 'channels' => ['mail']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'bar', 'channels' => ['sms']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'baz', 'channels' => ['telegram']]);

        $user2 = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user2->save();
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'foo', 'channels' => ['mail']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'bar', 'channels' => ['sms']]);
        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'baz', 'channels' => ['telegram']]);


        $this->assertSame(['mail'], (new FooNotification())->via($user1));
        $this->assertSame(['mail'], (new FooNotification())->via($user1));
        $this->assertSame(['mail'], (new FooNotification())->via($user1));

        $this->assertSame(['mail'], (new FooNotification())->via($user2));
        $this->assertSame(['mail'], (new FooNotification())->via($user2));
        $this->assertSame(['mail'], (new FooNotification())->via($user2));


        $this->assertSame(['sms'], (new BarNotification(1))->via($user1));
        $this->assertSame([], (new BarNotification(1))->via($user1));
        $this->assertSame(['sms'], (new BarNotification(2))->via($user1));
        $this->assertSame([], (new BarNotification(2))->via($user1));

        $this->assertSame(['sms'], (new BarNotification(1))->via($user2));
        $this->assertSame([], (new BarNotification(1))->via($user2));
        $this->assertSame(['sms'], (new BarNotification(2))->via($user2));
        $this->assertSame([], (new BarNotification(2))->via($user2));


        $this->assertSame(['telegram'], (new BazNotification())->via($user1));
        $this->assertSame([], (new BazNotification())->via($user1));
        $this->assertSame(['telegram'], (new BazNotification())->via($user2));
        $this->assertSame([], (new BazNotification())->via($user2));


        $this->assertSame(['telegram'], (new BazNotification(['a']))->via($user1));
        $this->assertSame([], (new BazNotification(['a']))->via($user1));
        $this->assertSame(['telegram'], (new BazNotification(['a']))->via($user2));
        $this->assertSame([], (new BazNotification(['a']))->via($user2));
    }

    /**
     * @return void
     */
    public function test_via_cache()
    {
        $user1 = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user2 = clone $user1;
        $user1->save();
        $user2->save();

        $user1->created_at = now()->subHour(); // settings are cached for "old" notifiables only
        $user2->created_at = now()->subHour();

        $userNotification1a = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'foo', 'channels' => ['mail']]);
        $userNotification1b = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user1->id, 'trigger' => 'bar', 'channels' => ['sms']]);
        $userNotification2a = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'foo', 'channels' => ['telegram']]);
        $userNotification2b = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user2->id, 'trigger' => 'bar', 'channels' => ['database']]);


        $this->assertSame(['mail'], (new FooNotification())->via($user1));
        $userNotification1a->delete();
        $this->assertSame([], (new FooNotification())->via($user1));

        $this->assertSame(['telegram'], (new FooNotification())->via($user2));
        $userNotification2a->delete();
        $this->assertSame([], (new FooNotification())->via($user2));


        $this->assertSame(['sms'], (new BarNotification(1))->via($user1));
        $userNotification1b->delete();
        $this->assertSame(['sms'], (new BarNotification(2))->via($user1));

        $this->assertSame(['database'], (new BarNotification(1))->via($user2));
        $userNotification2b->delete();
        $this->assertSame(['database'], (new BarNotification(2))->via($user2));
    }

    /**
     * @return void
     */
    public function test_via_cache_channels_created_at()
    {
        $this->freezeSecond();

        // title => [created_at, $timestamps, "settings are cached"]
        $cases = [
            'just created' => [now(), true, false],
            'the border' => [now()->subMinutes(2), true, false],
            'right after the border' => [now()->subMinutes(2)->subSecond(), true, true],
            'an hour ago' => [now()->subHour(), true, true],
            'in a minute (the difference is absolute)' => [now()->addMinute(), true, false],
            'in an hour' => [now()->addHour(), true, true],
            'created_at is empty' => [null, true, true],
            'timestamps are disabled' => [now()->toDateTimeString(), false, true],
        ];

        foreach ($cases as $title => [$createdAt, $timestamps, $cached]) {
            $user = new class () extends \Illuminate\Foundation\Auth\User
            {
                use \Illuminate\Notifications\RoutesNotifications;
                protected $table = 'users';
            };
            $user->save();
            $user->timestamps = $timestamps;
            $user->created_at = $createdAt;

            $userNotification = \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user->id, 'trigger' => 'bar', 'channels' => ['sms']]);

            $this->assertSame(['sms'], (new BarNotification(1))->via($user), $title);

            $userNotification->delete();
            $this->assertSame($cached ? ['sms'] : [], (new BarNotification(2))->via($user), $title);
        }
    }

    /**
     * @return void
     */
    public function test_via_empty()
    {
        $user = new class () extends \Illuminate\Foundation\Auth\User
        {
            use \Illuminate\Notifications\RoutesNotifications;
            protected $table = 'users';
        };
        $user->save();

        \AnourValar\EloquentNotification\UserNotification::factory()->create(['user_id' => $user->id, 'trigger' => 'bar', 'channels' => ['telegram', 'sms']]);

        $this->assertSame([], (new FooNotification())->via($user));
        $this->assertSame(['sms', 'telegram'], (new BarNotification(1))->via($user));
        $this->assertSame([], (new BazNotification())->via($user));
        $this->assertSame([], (new FoobarNotification())->via($user));
    }
}
