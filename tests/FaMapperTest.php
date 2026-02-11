<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;

class FaMapperTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @return void
     */
    public function test_smoke()
    {
        $mapper = new \AnourValar\EloquentNotification\FaMapper('foo', ['id' => 123, 'phone' => '79000000000', 'email' => 'foo@example.org']);
        $this->assertEquals($mapper, decrypt(encrypt($mapper)));

        $mapper = new \AnourValar\EloquentNotification\FaMapper('bar', ['phone' => '79000000000']);
        $this->assertEquals($mapper, decrypt($mapper->encrypt()));
    }
}
