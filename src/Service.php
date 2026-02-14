<?php

namespace AnourValar\EloquentNotification;

use AnourValar\EloquentValidation\Exceptions\ValidationException;
use AnourValar\EloquentNotification\Jobs\CollectNotificationJob;

class Service
{
    /**
     * Settings batch modify
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param array $data
     * @param mixed $validatePrefix
     * @param string|null $group
     * @return void
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function sync(\Illuminate\Contracts\Auth\Authenticatable $user, array $data, $validatePrefix = null, ?string $group = null): void
    {
        // Current
        $class = config('eloquent_notification.model');
        $collection = $class::where('user_id', '=', $user->getAuthIdentifier())->get();


        // New
        foreach ($data as $trigger => $channels) {
            $curr = $collection->where('trigger', '=', $trigger);
            if ($group && config("eloquent_notification.trigger.{$trigger}") && ! config("eloquent_notification.trigger.{$trigger}.{$group}")) {
                throw new ValidationException(
                    ['trigger' => trans('eloquent-validation::validation.unchangeable', ['attribute' => trans(config("eloquent_notification.trigger.{$trigger}.title"))])],
                    prefix: [$validatePrefix, $trigger]
                );
            }

            if ($curr->count()) {
                $curr->first()->forceFill(['channels' => $channels])->validate([$validatePrefix, $trigger])->save();
                $collection->pull($curr->keys()->first());
            } else {
                (new $class())
                    ->forceFill([
                        'user_id' => $user->getAuthIdentifier(),
                        'trigger' => $trigger,
                        'channels' => $channels,
                    ])
                    ->validate([$validatePrefix, $trigger])
                    ->save();
            }
        }


        // Left (not actual)
        foreach ($collection as $item) {
            if ($group && ! config("eloquent_notification.trigger.{$item->trigger}.{$group}")) {
                continue;
            }

            $item->validateDelete()->delete();
        }
    }

    /**
     * List of channels
     *
     * @return array
     */
    public function channels(): array
    {
        $channels = [];
        foreach (config('eloquent_notification.trigger') as $details) {
            $channels = array_merge($channels, $details['channels']);
        }
        $channels = array_values(array_unique($channels));

        $result = [];
        foreach ($channels as $channel) {
            $result[$channel] = trans('eloquent_notification::user_notification.channels.' . $channel);
        }
        return $result;
    }

    /**
     * Delayed group notification
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string $notificationClass
     * @param array $notificationArguments
     * @return void
     */
    public function collectNotify(\Illuminate\Foundation\Auth\User $user, string $notificationClass, array $notificationArguments): void
    {
        \Atom::exchangerPush(
            CollectNotificationJob::EXCHANGER_KEY . $user->id,
            ['notification' => $notificationClass, 'arguments' => $notificationArguments]
        );

        CollectNotificationJob::dispatch($user)->delay(now()->addSeconds(config('eloquent_notification.collect_delay_seconds')));
    }
}
