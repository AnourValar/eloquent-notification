<?php

namespace AnourValar\EloquentNotification\Tests\Http\Middleware;

use AnourValar\EloquentNotification\FaMapper;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmFaMyDynamic;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use Illuminate\Support\Facades\Route;

class ConfirmFaMyDynamicTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => \AnourValar\EloquentNotification\Tests\User::class]);

        Route::middleware(['web', 'auth', ConfirmFaMyDynamic::class . ':cryptograms_fa,0'])
            ->post('/_test/fa-dynamic/qty0', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', ConfirmFaMyDynamic::class . ':cryptograms_fa,1'])
            ->post('/_test/fa-dynamic/qty1', fn () => response()->json(['ok' => true]));
    }

    /**
     * qty=0 + no totp_secret → passes through without cryptograms.
     *
     * @return void
     */
    public function test_qty0_without_totp_passes_through()
    {
        $user = $this->makeUser(['totp_secret' => null]);

        $response = $this->actingAs($user)->postJson('/_test/fa-dynamic/qty0');

        $response->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * qty=0 + totp_secret → qty becomes 1, valid cryptogram required.
     *
     * @return void
     */
    public function test_qty0_with_totp_requires_one_cryptogram()
    {
        \Date::setTestNow('2025-10-03 10:00:00');
        $user = $this->makeUser(['totp_secret' => 'SECRET', 'phone' => '79000000000']);

        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];

        $response = $this->actingAs($user)->postJson('/_test/fa-dynamic/qty0', [
            'cryptograms_fa' => $cryptograms,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * qty=0 + totp_secret, but cryptograms not provided → fails (qty bumped to 1).
     *
     * @return void
     */
    public function test_qty0_with_totp_fails_without_cryptograms()
    {
        $user = $this->makeUser(['totp_secret' => 'SECRET']);

        $response = $this->actingAs($user)->postJson('/_test/fa-dynamic/qty0');

        $response->assertStatus(403);
    }

    /**
     * qty=1 + no totp_secret → one cryptogram is enough.
     *
     * @return void
     */
    public function test_qty1_without_totp_requires_one_cryptogram()
    {
        \Date::setTestNow('2025-10-03 10:00:00');
        $user = $this->makeUser(['totp_secret' => null, 'phone' => '79000000000']);

        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];

        $response = $this->actingAs($user)->postJson('/_test/fa-dynamic/qty1', [
            'cryptograms_fa' => $cryptograms,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * qty=1 + totp_secret → qty becomes 2, requires two cryptograms.
     *
     * @return void
     */
    public function test_qty1_with_totp_requires_two_cryptograms()
    {
        \Date::setTestNow('2025-10-03 10:00:00');
        $user = $this->makeUser(['totp_secret' => 'SECRET', 'phone' => '79000000000', 'email' => 'foo@example.org']);

        // Only 1 cryptogram supplied — should fail (qty bumped to 2).
        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];
        $this->actingAs($user)
            ->postJson('/_test/fa-dynamic/qty1', ['cryptograms_fa' => $cryptograms])
            ->assertStatus(403);

        // 2 cryptograms — passes (cryptograms must share a contact to be linked).
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->actingAs($user)
            ->postJson('/_test/fa-dynamic/qty1', ['cryptograms_fa' => $cryptograms])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    /**
     * @return \AnourValar\EloquentNotification\Tests\User
     */
    private function makeUser(array $attributes = []): \AnourValar\EloquentNotification\Tests\User
    {
        $user = new \AnourValar\EloquentNotification\Tests\User();
        $user->forceFill(array_merge([
            'email' => 'foo@example.org',
            'phone' => '79000000000',
            'totp_secret' => null,
        ], $attributes))->save();

        return $user;
    }
}
