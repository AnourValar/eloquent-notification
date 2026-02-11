<?php

namespace AnourValar\EloquentNotification\resources\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $triggers = array_keys(config('notification.trigger'));
        shuffle($triggers);

        return [
            'user_id' => function (array $attributes) {
                $class = config('auth.providers.users.model');
                return $class::factory()->create();
            },
            'trigger' => $triggers[0],
            'channels' => function (array $attributes) {
                $channels = config('notification.trigger.' . $attributes['trigger'] . '.channels');
                shuffle($channels);

                return array_slice($channels, 0, mt_rand(1, count($channels)));
            },
            'created_at' => $this->faker->dateTimeBetween('-1 months'),
        ];
    }

    /**
     * From existing users
     *
     * @return static
     */
    public function existingUser()
    {
        $users = \Cache::driver('array')->rememberForever(__METHOD__, function () {
            $class = config('auth.providers.users.model');
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($class))) {
                $user = $class::withTrashed();
            } else {
                $user = $class::query();
            }

            return $user->get(['id']);
        });

        return $this->state(function (array $attributes) use ($users) {
            return [
                'user_id' => function (array $attributes) use ($users) {
                    return $users->shuffle()->first();
                },
            ];
        });
    }
}
