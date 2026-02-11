<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class User extends \Illuminate\Foundation\Auth\User
{
    use \Illuminate\Notifications\Notifiable;
    protected $table = 'users';
}
