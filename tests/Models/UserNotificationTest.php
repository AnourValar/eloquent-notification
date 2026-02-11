<?php

namespace AnourValar\EloquentNotification\Tests\Models;

use Tests\TestCase;
use AnourValar\EloquentNotification\Tests\AbstractSuite;

class UserNotificationTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_scope_light()
    {
        $userNotification = \AnourValar\EloquentNotification\UserNotification::factory()->create([
            'user_id' => 1,
            'trigger' => 'foo',
            'channels' => ['bar', 'baz'],
            'created_at' => '2024-05-29 12:00:00',
        ]);

        // light
        $this->assertSame(
            [
                'id' => $userNotification->id,
                'user_id' => 1,
                'trigger' => 'foo',
                'channels' => ['bar', 'baz'],
                'created_at' => '2024-05-29T12:00:00.000000Z',
            ],
            \AnourValar\EloquentNotification\UserNotification::light()->find($userNotification->id)->toArray()
        );
    }

    /**
     * @return void
     */
    public function test_channels_jsonNested()
    {
        $this->assertSame(
            ['1', '3'],
            (new \AnourValar\EloquentNotification\UserNotification())->forceFill(['channels' => [
                'c' => '3',
                'a' => 1,
                2 => null,
            ]])->channels
        );
    }
}
