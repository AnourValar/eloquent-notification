<?php

namespace AnourValar\EloquentNotification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectNotificationJob implements ShouldBeUniqueUntilProcessing, ShouldQueue // must be dispatch with delay!
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @var string
     */
    public const EXCHANGER_KEY = 'notification_package:collect_notify:';

    /**
     * Create a new job instance.
     */
    public function __construct(public \Illuminate\Foundation\Auth\User $user)
    {

    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->getKey();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $notifications = [];
        foreach (\Atom::exchangerPull(static::EXCHANGER_KEY . $this->user->id) as $item) {
            foreach ($item['arguments'] as $key => $value) {
                $notifications[$item['notification']][$key][] = $value;
            }
        }

        foreach ($notifications as $class => $arguments) {
            $this->user->notify(new $class(...$arguments));
        }
    }
}
