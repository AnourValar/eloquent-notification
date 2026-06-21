<?php

namespace AnourValar\EloquentNotification\Tests\Http\Middleware;

use AnourValar\EloquentNotification\ConfirmService;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmTotpInput;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmTotpMy;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Tests\User;
use Illuminate\Support\Facades\Route;

class ConfirmTotpTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @var string
     */
    private string $secret = '73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6';

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Cache::flush();
        config(['auth.providers.users.model' => User::class]);

        Route::middleware(['web', ConfirmTotpInput::class])
            ->post('/_test/totp/input', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', ConfirmTotpMy::class])
            ->post('/_test/totp/my', fn () => response()->json(['ok' => true]));
    }

    /**
     * Input variant: cryptogram + valid code passes.
     *
     * @return void
     */
    public function test_input_success()
    {
        \Date::setTestNow('2025-09-17 08:33:00');
        $service = \App::make(ConfirmService::class);

        $this->postJson('/_test/totp/input', [
            'cryptogram_totp' => $service->cryptogramTotp($this->secret),
            'code_totp' => $service->codeTotp($this->secret),
        ])->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * Input variant: wrong code is rejected.
     *
     * @return void
     */
    public function test_input_failure()
    {
        \Date::setTestNow('2025-09-17 08:33:00');
        $service = \App::make(ConfirmService::class);

        $this->postJson('/_test/totp/input', [
            'cryptogram_totp' => $service->cryptogramTotp($this->secret),
            'code_totp' => '000000',
        ])->assertStatus(422);
    }

    /**
     * My variant: secret cryptogram is taken from the authenticated user.
     *
     * @return void
     */
    public function test_my_success()
    {
        \Date::setTestNow('2025-09-17 08:33:00');
        $service = \App::make(ConfirmService::class);

        $user = new User();
        $user->forceFill(['totp_secret' => $service->cryptogramTotp($this->secret)])->save();

        $this->actingAs($user)->postJson('/_test/totp/my', [
            'code_totp' => $service->codeTotp($this->secret),
        ])->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * My variant: wrong code is rejected.
     *
     * @return void
     */
    public function test_my_failure()
    {
        \Date::setTestNow('2025-09-17 08:33:00');
        $service = \App::make(ConfirmService::class);

        $user = new User();
        $user->forceFill(['totp_secret' => $service->cryptogramTotp($this->secret)])->save();

        $this->actingAs($user)->postJson('/_test/totp/my', [
            'code_totp' => '000000',
        ])->assertStatus(422);
    }
}
