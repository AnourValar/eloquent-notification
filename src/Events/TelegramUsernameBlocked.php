<?php

namespace AnourValar\EloquentNotification\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramUsernameBlocked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var object
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(object $user)
    {
        $this->user = $user;
    }
}
