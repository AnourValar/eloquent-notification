<?php

namespace AnourValar\EloquentNotification\Tests\Http\Middleware;

use AnourValar\EloquentNotification\ConfirmService;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmEmailInput;
use AnourValar\EloquentNotification\Http\Middleware\ConfirmEmailMy;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Tests\User;
use Illuminate\Support\Facades\Route;

class ConfirmEmailTest extends AbstractSuite
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

        Route::middleware(['web', ConfirmEmailInput::class])
            ->post('/_test/email/input', fn (\Illuminate\Http\Request $request) => response()->json(['email' => $request->input('email')]));

        Route::middleware(['web', 'auth', ConfirmEmailMy::class])
            ->post('/_test/email/my', fn () => response()->json(['ok' => true]));
    }

    /**
     * Input variant: confirmed email is normalized and merged back into the request.
     *
     * @return void
     */
    public function test_input_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('FOO@example.org');

        $this->postJson('/_test/email/input', [
            'cryptogram_email' => $cryptogram,
            'code_email' => $code,
            'email' => 'FOO@example.org',
        ])->assertOk()->assertJson(['email' => 'foo@example.org']);
    }

    /**
     * Input variant: wrong code is rejected.
     *
     * @return void
     */
    public function test_input_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram] = $this->request('foo@example.org');

        $this->postJson('/_test/email/input', [
            'cryptogram_email' => $cryptogram,
            'code_email' => 'wrong-code',
            'email' => 'foo@example.org',
        ])->assertStatus(422);
    }

    /**
     * My variant: email is taken from the authenticated user.
     *
     * @return void
     */
    public function test_my_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('foo@example.org');

        $user = new User();
        $user->forceFill(['email' => 'foo@example.org'])->save();

        $this->actingAs($user)->postJson('/_test/email/my', [
            'cryptogram_email' => $cryptogram,
            'code_email' => $code,
        ])->assertOk()->assertJson(['ok' => true]);
    }

    /**
     * My variant: user's email does not match the cryptogram.
     *
     * @return void
     */
    public function test_my_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        ['cryptogram' => $cryptogram, 'code' => $code] = $this->request('foo@example.org');

        $user = new User();
        $user->forceFill(['email' => 'other@example.org'])->save();

        $this->actingAs($user)->postJson('/_test/email/my', [
            'cryptogram_email' => $cryptogram,
            'code_email' => $code,
        ])->assertStatus(422);
    }

    /**
     * @param string $email
     * @return array{cryptogram: string, code: string}
     */
    private function request(string $email): array
    {
        \Notification::fake();

        $cryptogram = \App::make(ConfirmService::class)->requestEmail($email)['cryptogram_email'];

        return ['cryptogram' => $cryptogram, 'code' => decrypt($cryptogram)['code']];
    }
}
