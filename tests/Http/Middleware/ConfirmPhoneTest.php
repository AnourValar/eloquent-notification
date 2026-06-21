<?php

namespace AnourValar\EloquentNotification\Tests\Http\Middleware;

use AnourValar\EloquentNotification\ConfirmService;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmPhoneInput;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmPhoneMy;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Tests\User;
use Illuminate\Support\Facades\Route;

class ConfirmPhoneTest extends AbstractSuite
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

        Route::middleware(['web', ConfirmPhoneInput::class])
            ->post('/_test/phone/input', fn (\Illuminate\Http\Request $request) => response()->json(['phone' => $request->input('phone')]));

        Route::middleware(['web', 'auth', ConfirmPhoneMy::class])
            ->post('/_test/phone/my', fn () => response()->json(['ok' => true]));
    }

    /**
     * Input variant: confirmed phone is merged back into the request.
     *
     * @return void
     */
    public function test_input_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('79001234567');

        $this->postJson('/_test/phone/input', [
            'cryptogram_phone' => $cryptogram,
            'code_phone' => $code,
            'phone' => '79001234567',
        ])->assertOk()->assertJson(['phone' => '79001234567']);
    }

    /**
     * Input variant: wrong code is rejected.
     *
     * @return void
     */
    public function test_input_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram] = $this->request('79001234567');

        $this->postJson('/_test/phone/input', [
            'cryptogram_phone' => $cryptogram,
            'code_phone' => '000000',
            'phone' => '79001234567',
        ])->assertStatus(422);
    }

    /**
     * My variant: phone is taken from the authenticated user.
     *
     * @return void
     */
    public function test_my_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('79001234567');

        $user = new User();
        $user->forceFill(['phone' => '79001234567'])->save();

        $this->actingAs($user)->postJson('/_test/phone/my', [
            'cryptogram_phone' => $cryptogram,
            'code_phone' => $code,
        ])->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * My variant: user's phone does not match the cryptogram.
     *
     * @return void
     */
    public function test_my_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('79001234567');

        $user = new User();
        $user->forceFill(['phone' => '79007654321'])->save();

        $this->actingAs($user)->postJson('/_test/phone/my', [
            'cryptogram_phone' => $cryptogram,
            'code_phone' => $code,
        ])->assertStatus(422);
    }

    /**
     * @param string $phone
     * @return array{cryptogram: string, code: string}
     */
    private function request(string $phone): array
    {
        \Notification::fake();

        $cryptogram = \App::make(ConfirmService::class)->requestPhone($phone, 'regex:/^7\d{10}$/')['cryptogram_phone'];

        return ['cryptogram' => $cryptogram, 'code' => decrypt($cryptogram)['code']];
    }
}
