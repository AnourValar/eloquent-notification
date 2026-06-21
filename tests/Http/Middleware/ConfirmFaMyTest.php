<?php

namespace AnourValar\EloquentNotification\Tests\Http\Middleware;

use AnourValar\EloquentNotification\FaMapper;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmFaMy;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Tests\User;
use Illuminate\Support\Facades\Route;

class ConfirmFaMyTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Cache::flush();
        config(['auth.providers.users.model' => User::class]);

        Route::middleware(['web', 'auth', ConfirmFaMy::class . ':cryptograms_fa,1'])
            ->post('/_test/fa', fn () => response()->json(['ok' => true]));
    }

    /**
     * Valid cryptogram whose contact matches the user passes.
     *
     * @return void
     */
    public function test_success()
    {
        \Date::setTestNow('2025-10-03 10:00:00');
        $user = $this->makeUser('79000000000');

        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];

        $this->actingAs($user)
            ->postJson('/_test/fa', ['cryptograms_fa' => $cryptograms])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    /**
     * A verified contact that does not belong to the user is rejected (403).
     *
     * @return void
     */
    public function test_user_mismatch()
    {
        \Date::setTestNow('2025-10-03 10:00:00');
        $user = $this->makeUser('79999999999');

        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];

        $this->actingAs($user)
            ->postJson('/_test/fa', ['cryptograms_fa' => $cryptograms])
            ->assertStatus(403);
    }

    /**
     * Missing cryptograms are rejected (403).
     *
     * @return void
     */
    public function test_without_cryptograms()
    {
        $user = $this->makeUser('79000000000');

        $this->actingAs($user)
            ->postJson('/_test/fa')
            ->assertStatus(403);
    }

    /**
     * @param string $phone
     * @return \AnourValar\EloquentNotification\Tests\User
     */
    private function makeUser(string $phone): User
    {
        $user = new User();
        $user->forceFill(['phone' => $phone])->save();

        return $user;
    }
}
