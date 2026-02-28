<?php

namespace AnourValar\EloquentNotification\Tests;

use Tests\TestCase;
use AnourValar\EloquentNotification\FaMapper;

class ConfirmServiceTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @var \AnourValar\EloquentNotification\ConfirmService
     */
    private \AnourValar\EloquentNotification\ConfirmService $confirmService;

    /**
     * {@inheritDoc}
     * @see \Illuminate\Foundation\Testing\TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->confirmService = \App::make(\AnourValar\EloquentNotification\ConfirmService::class);
    }

    /**
     * @return void
     */
    public function test_pow_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');

        $pow = $this->confirmService->requestPow(40);
        $puzzle = $this->resolvePuzzle(40, $pow);
        $this->assertCount(10, $puzzle);

        // Iteration 1
        \Date::setTestNow('2025-08-07 10:01:00');
        $this->assertSame(['type' => 'confirm.pow', 'puzzle' => $puzzle, 'expired_at' => 1754560860], decrypt($pow['cryptogram_pow']));
        $this->assertTrue($this->confirmService->validatePow($puzzle, $pow['cryptogram_pow']));

        // Iteration 2
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, $pow['cryptogram_pow']), trans('eloquent_notification::confirm.expired'));
    }

    /**
     * @return void
     */
    public function test_pow_failure()
    {
        config(['eloquent_notification.confirm.pow_cost' => 20]);
        \Date::setTestNow('2025-08-07 10:00:00');

        $pow = $this->confirmService->requestPow();
        $puzzle = $this->resolvePuzzle(20, $pow);

        // Incorrect
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, 'foo'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, null), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, ['foo']), trans('eloquent_notification::confirm.incorrect'));

        // Mismatch
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, encrypt(['type' => 'foo'])), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, encrypt(['foo' => 'bar'])), trans('eloquent_notification::confirm.incorrect'));

        // Lose
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow(['foo' => 'bar'], $pow['cryptogram_pow']), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow(null, $pow['cryptogram_pow']), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow('foo', $pow['cryptogram_pow']), trans('eloquent_notification::confirm.incorrect_code'));

        // Expired
        \Date::setTestNow('2025-08-07 10:01:01');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePow($puzzle, $pow['cryptogram_pow']), trans('eloquent_notification::confirm.expired'));

        // Success
        \Date::setTestNow('2025-08-07 10:00:00');
        $this->assertTrue($this->confirmService->validatePow($puzzle, $pow['cryptogram_pow']));
    }

    /**
     * @return void
     */
    public function test_requestEmail_success_1()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $email = $this->confirmService->requestEmail('FOO@example.org');
        $this->assertSame([], array_diff_key($email, ['cryptogram_email' => true]));
        $cryptogramEmail = decrypt($email['cryptogram_email']);

        $this->assertIsString($cryptogramEmail['code']);
        $this->assertSame(['type' => 'confirm.email', 'code' => $cryptogramEmail['code'], 'email' => 'foo@example.org', 'expired_at' => 1754562600], $cryptogramEmail);

        \Notification::assertCount(1);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
        };
        $user->forceFill(['email' => 'foo@example.org'])->save();

        $cryptogramEmail = decrypt($this->confirmService->requestEmail(encrypt('FOO@example.org'))['cryptogram_email']);
        $this->assertIsString($cryptogramEmail['code']);
        $this->assertSame(['type' => 'confirm.email', 'code' => $cryptogramEmail['code'], 'email' => 'foo@example.org', 'expired_at' => 1754562600], $cryptogramEmail);

        \Notification::assertCount(2);
    }

    /**
     * @return void
     */
    public function test_requestEmail_success_2()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $cryptogramEmail = decrypt($this->confirmService->requestEmail('FOO@example.org', false)['cryptogram_email']);
        $this->assertIsString($cryptogramEmail['code']);
        $this->assertSame(['type' => 'confirm.email', 'code' => $cryptogramEmail['code'], 'email' => 'foo@example.org', 'expired_at' => 1754562600], $cryptogramEmail);

        \Notification::assertCount(1);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
        };
        $user->forceFill(['email' => 'foo@example.org'])->save();

        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('FOO@example.org', false), trans('eloquent_notification::confirm.email_already_exists'));
        \Notification::assertCount(1);
    }

    /**
     * @return void
     */
    public function test_requestEmail_success_3()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('FOO@example.org', true), trans('eloquent_notification::confirm.email_not_exists'));
        \Notification::assertCount(0);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            use \Illuminate\Database\Eloquent\SoftDeletes;
            protected $table = 'users';
        };
        $user->forceFill(['email' => 'foo@example.org'])->save();
        $user->delete();

        $cryptogramEmail = decrypt($this->confirmService->requestEmail('FOO@example.org', true)['cryptogram_email']);
        $this->assertIsString($cryptogramEmail['code']);
        $this->assertSame(['type' => 'confirm.email', 'code' => $cryptogramEmail['code'], 'email' => 'foo@example.org', 'expired_at' => 1754562600], $cryptogramEmail);

        \Notification::assertCount(1);
    }

    /**
     * @return void
     */
    public function test_requestEmail_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Validation
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('foo'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail(null));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail(['bar']));
        \Notification::assertCount(0);

        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('foo@example.org', ['foo']));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('foo@example.org', ''));
        \Notification::assertCount(0);


        // Throttle
        $this->confirmService->requestEmail('foo@example.org');
        $this->confirmService->requestEmail('foo@example.org');
        \Notification::assertCount(2);
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('FOO@example.org'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        \Notification::assertCount(2);

        $this->confirmService->requestEmail('bar@example.org');
        $this->confirmService->requestEmail('bar@example.org');
        \Notification::assertCount(4);
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestEmail('BAR@EXAMPLE.ORG'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        \Notification::assertCount(4);
    }

    /**
     * @return void
     */
    public function test_validateEmail_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        $cryptogramEmail = $this->confirmService->requestEmail('FOO@example.org')['cryptogram_email'];
        $code = decrypt($cryptogramEmail)['code'];

        // Iteration 1
        $this->assertSame('foo@example.org', $this->confirmService->validateEmail($cryptogramEmail, $code, 'foo@example.org'));

        // Iteration 2
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:15:00');
        $this->assertSame('foo@example.org', $this->confirmService->validateEmail($cryptogramEmail, $code, 'FOO@example.org'));

        // Iteration 3
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:30:00');
        $this->assertSame('foo@example.org', $this->confirmService->validateEmail($cryptogramEmail, $code, encrypt('foo@EXAMPLE.ORG')));

        // Iteration 4
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:30:01');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, 'foo@example.org'), trans('eloquent_notification::confirm.expired'));
    }

    /**
     * @return void
     */
    public function test_validateEmail_failure()
    {
        $cryptogramEmail = $this->confirmService->requestEmail('FOO@example.org')['cryptogram_email'];
        $code = decrypt($cryptogramEmail)['code'];

        // Incorrect cryptogram
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail('foo', $code, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail(null, $code, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail(['bar'], $code, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail(encrypt(['type' => 'baz']), $code, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail(encrypt(['foobar']), $code, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect'));

        // Input email
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, 'bar@example.org'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, 'baz'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, null), trans('eloquent_notification::confirm.email_is_empty'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, ['foobar']), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, implode(',', range(0, 100))), trans('eloquent_notification::confirm.incorrect_code'));

        // Input code email
        \Cache::clear();
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, 'foo', 'foo@example.org'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, null, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, ['bar'], 'foo@example.org'), trans('eloquent_notification::confirm.incorrect_code'));

        // Invalid throttle
        $cryptogramEmail = $this->confirmService->requestEmail('FOO@example.org')['cryptogram_email'];
        $code = decrypt($cryptogramEmail)['code'];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, false, 'foo@example.org'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertSame('foo@example.org', $this->confirmService->validateEmail($cryptogramEmail, $code, 'foo@example.org'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, $code, 'foo@example.org'), trans('eloquent_notification::confirm.expired'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateEmail($cryptogramEmail, false, 'foo@example.org'), trans('eloquent_notification::confirm.expired'));
    }

    /**
     * @return void
     */
    public function test_requestPhone_success_1()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $phone = $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/');
        $this->assertSame([], array_diff_key($phone, ['cryptogram_phone' => true]));
        $cryptogramPhone = decrypt($phone['cryptogram_phone']);

        $this->assertIsString($cryptogramPhone['code']);
        $this->assertSame(['type' => 'confirm.phone', 'code' => $cryptogramPhone['code'], 'phone' => '79001234567', 'expired_at' => 1754561700], $cryptogramPhone);

        \Notification::assertCount(1);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
        };
        $user->forceFill(['phone' => '79001234567'])->save();

        $cryptogramPhone = decrypt($this->confirmService->requestPhone(encrypt('79001234567'), 'regex:/^7\d{10}$/')['cryptogram_phone']);
        $this->assertIsString($cryptogramPhone['code']);
        $this->assertSame(['type' => 'confirm.phone', 'code' => $cryptogramPhone['code'], 'phone' => '79001234567', 'expired_at' => 1754561700], $cryptogramPhone);

        \Notification::assertCount(2);
    }

    /**
     * @return void
     */
    public function test_requestPhone_success_2()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $cryptogramPhone = decrypt($this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', false)['cryptogram_phone']);
        $this->assertIsString($cryptogramPhone['code']);
        $this->assertSame(['type' => 'confirm.phone', 'code' => $cryptogramPhone['code'], 'phone' => '79001234567', 'expired_at' => 1754561700], $cryptogramPhone);

        \Notification::assertCount(1);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
        };
        $user->forceFill(['phone' => '79001234567'])->save();

        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', false), trans('eloquent_notification::confirm.phone_already_exists'));
        \Notification::assertCount(1);
    }

    /**
     * @return void
     */
    public function test_requestPhone_success_3()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Iteration 1
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', true), trans('eloquent_notification::confirm.phone_not_exists'));
        \Notification::assertCount(0);


        // Iteration 2
        \Cache::flush();
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            use \Illuminate\Database\Eloquent\SoftDeletes;
            protected $table = 'users';
        };
        $user->forceFill(['phone' => '79001234567'])->save();
        $user->delete();

        $cryptogramPhone = decrypt($this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', true)['cryptogram_phone']);
        $this->assertIsString($cryptogramPhone['code']);
        $this->assertSame(['type' => 'confirm.phone', 'code' => $cryptogramPhone['code'], 'phone' => '79001234567', 'expired_at' => 1754561700], $cryptogramPhone);

        \Notification::assertCount(1);
    }

    /**
     * @return void
     */
    public function test_requestPhone_failure()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        \Notification::fake();


        // Validation
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('foo', 'regex:/^7\d{10}$/'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone(null, 'regex:/^7\d{10}$/'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone(['bar'], 'regex:/^7\d{10}$/'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('89001234567', 'regex:/^7\d{10}$/'));
        \Notification::assertCount(0);

        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', ['foo']));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/', ''));
        \Notification::assertCount(0);


        // Throttle
        $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/');
        $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/');
        \Notification::assertCount(2);
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79001234567', 'regex:/^7\d{10}$/'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        \Notification::assertCount(2);

        $this->confirmService->requestPhone('79101234567', 'regex:/^7\d{10}$/');
        $this->confirmService->requestPhone('79101234567', 'regex:/^7\d{10}$/');
        \Notification::assertCount(4);
        $this->assertCustomValidationFailed(fn () => $this->confirmService->requestPhone('79101234567', 'regex:/^7\d{10}$/'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        \Notification::assertCount(4);
    }

    /**
     * @return void
     */
    public function test_validatePhone_success()
    {
        \Date::setTestNow('2025-08-07 10:00:00');
        $cryptogramPhone = $this->confirmService->requestPhone('79001234567', 'string')['cryptogram_phone'];
        $code = decrypt($cryptogramPhone)['code'];

        // Iteration 1
        $this->assertSame('79001234567', $this->confirmService->validatePhone($cryptogramPhone, $code, '79001234567'));

        // Iteration 2
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:10:00');
        $this->assertSame('79001234567', $this->confirmService->validatePhone($cryptogramPhone, $code, '79001234567'));

        // Iteration 3
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:15:00');
        $this->assertSame('79001234567', $this->confirmService->validatePhone($cryptogramPhone, $code, encrypt('79001234567')));

        // Iteration 4
        \Cache::clear();
        \Date::setTestNow('2025-08-07 10:15:01');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, '79001234567'), trans('eloquent_notification::confirm.expired'));
    }

    /**
     * @return void
     */
    public function test_validatePhone_failure()
    {
        $cryptogramPhone = $this->confirmService->requestPhone('79001234567', 'string')['cryptogram_phone'];
        $code = decrypt($cryptogramPhone)['code'];

        // Incorrect cryptogram
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone('foo', $code, '79001234567'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone(null, $code, '79001234567'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone(['bar'], $code, '79001234567'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone(encrypt(['type' => 'baz']), $code, '79001234567'), trans('eloquent_notification::confirm.incorrect'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone(encrypt(['foobar']), $code, '79001234567'), trans('eloquent_notification::confirm.incorrect'));

        // Input phone
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, '79011234567'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, 'baz'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, null), trans('eloquent_notification::confirm.phone_is_empty'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, ['foobar']), trans('eloquent_notification::confirm.incorrect_code'));

        // Input code phone
        \Cache::clear();
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, 'foo', '79001234567'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, null, '79001234567'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, ['bar'], '79001234567'), trans('eloquent_notification::confirm.incorrect_code'));

        // Invalid throttle
        $cryptogramPhone = $this->confirmService->requestPhone('79001234567', 'string')['cryptogram_phone'];
        $code = decrypt($cryptogramPhone)['code'];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, false, '79001234567'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertSame('79001234567', $this->confirmService->validatePhone($cryptogramPhone, $code, '79001234567'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, $code, '79001234567'), trans('eloquent_notification::confirm.expired'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validatePhone($cryptogramPhone, false, '79001234567'), trans('eloquent_notification::confirm.expired'));
    }

    /**
     * @return void
     */
    public function test_generateTotp()
    {
        $this->assertTrue(strlen($this->confirmService->generateTotp()) >= 20);
    }

    /**
     * @return void
     */
    public function test_validateTotp()
    {
        \Cache::flush();
        \Date::setTestNow('2025-09-17 08:32:00');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 557275), trans('eloquent_notification::confirm.incorrect_code'));


        \Cache::flush();
        \Date::setTestNow('2025-09-17 08:32:30');
        $this->assertTrue($this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 557275));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 557276), trans('eloquent_notification::confirm.incorrect_code'));


        \Cache::clear();
        \Date::setTestNow('2025-09-17 08:33:00');
        $this->assertTrue($this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'));
        $this->assertTrue($this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 557275));

        \Cache::flush();
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 557276), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557276'), trans('eloquent_notification::confirm.incorrect_code'));

        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 'foobar'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', ['foo' => 'bar']), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', []), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', null), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', true), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 1), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', 0), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', ''), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '1234'), trans('eloquent_notification::confirm.incorrect_code'));

        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557276'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP7', '557276'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('', '557276'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp(null, '557276'), trans('eloquent_notification::confirm.incorrect_code'));


        \Cache::flush();
        \Date::setTestNow('2025-09-17 08:33:29');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557276'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertTrue($this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557276'), trans('eloquent_notification::confirm.too_many', ['seconds' => 60]));


        \Cache::flush();
        \Date::setTestNow('2025-09-17 08:33:00');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557276'), trans('eloquent_notification::confirm.incorrect_code'));
        $this->assertTrue($this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'));


        \Cache::flush();
        \Date::setTestNow('2025-09-17 08:34:00');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6', '557275'), trans('eloquent_notification::confirm.incorrect_code'));
    }

    /**
     * @return void
     */
    public function test_validateTotpCryptogram()
    {
        \Date::setTestNow('2025-09-17 08:33:00');

        $cryptogram = $this->confirmService->cryptogramTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6');
        $this->assertTrue($this->confirmService->validateTotpCryptogram($cryptogram, 557275));

        $cryptogram = $this->confirmService->cryptogramTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557276'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = encrypt('');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = null;
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = encrypt(['type' => 'foo']);
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = 'foobarbaz';
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = ['type' => 'confirm.totp', 'expired_at' => now()->addMinute()->timestamp];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));

        $cryptogram = $this->confirmService->cryptogramTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6');
        \Date::setTestNow('2025-09-17 10:33:01');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateTotpCryptogram($cryptogram, '557275'), trans('eloquent_notification::confirm.incorrect_code'));
    }

    /**
     * @return void
     */
    public function test_urlTotp()
    {
        config(['app.name' => 'hello']);

        $this->assertSame('otpauth://totp/baz:foo?secret=bar&algorithm=SHA1&digits=6&period=30', $this->confirmService->urlTotp('foo', 'bar', 'baz'));
        $this->assertSame('otpauth://totp/hello:foo?secret=bar&algorithm=SHA1&digits=6&period=30', $this->confirmService->urlTotp('foo', 'bar'));
    }

    /**
     * @return void
     */
    public function test_cryptogramTotp()
    {
        $this->assertSame(
            [
                'type' => 'confirm.totp',
                'secret' => '73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6',
            ],
            json_decode(decrypt($this->confirmService->cryptogramTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'), false), true)
        );
    }

    /**
     * @return void
     */
    public function test_codeTotp()
    {
        \Date::setTestNow('2025-09-17 08:32:59');
        $this->assertNotSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));

        \Date::setTestNow('2025-09-17 08:33:00');
        $this->assertSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));

        \Date::setTestNow('2025-09-17 08:33:10');
        $this->assertSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));

        \Date::setTestNow('2025-09-17 08:33:20');
        $this->assertSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));

        \Date::setTestNow('2025-09-17 08:33:29');
        $this->assertSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));

        \Date::setTestNow('2025-09-17 08:33:30');
        $this->assertNotSame('557275', $this->confirmService->codeTotp('73QZHOHKOYKHMOL7R5BXCW5IT76WIUP6'));
    }

    /**
     * @param int $cost
     * @param array $pow
     * @return array
     */
    private function resolvePuzzle(int $cost, array $pow): array
    {
        $this->assertSame([], array_diff_key($pow, ['salt' => true, 'puzzle_pow' => true, 'cryptogram_pow' => true]));
        $result = [];

        $step = 0;
        for ($i = 0; $i <= $cost; $i++) {
            if (hash('sha256', $pow['salt'] . $i) == $pow['puzzle_pow'][$step]) {
                $result[] = $i;
                $i--;
                $step++;

                if (! isset($pow['puzzle_pow'][$step])) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @return void
     */
    public function test_validateFa_1fa()
    {
        \Date::setTestNow('2025-10-03 10:00:00');

        // Case 1
        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];
        $this->assertSame(['phone' => '79000000000'], $this->confirmService->validateFa($cryptograms, 1));

        // Case 2
        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];
        $this->assertSame(['phone' => '79000000000'], $this->confirmService->validateFa($cryptograms, fn ($contacts) => 1));

        // Case 3
        $cryptograms = [
            (new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')))->encrypt(),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, fn ($contacts) => 0), trans('eloquent_notification::confirm.miscount', ['qty' => 0]));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, fn ($contacts) => 2), trans('eloquent_notification::confirm.miscount', ['qty' => 2]));

        // Case 4
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['phone' => '79000000000', 'email' => 'foo@example.org'], $this->confirmService->validateFa($cryptograms, 1));
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1, ['foo']), trans('eloquent_notification::confirm.too_many', ['seconds' => 1801]));

        // Case 5
        $cryptograms = [
            //
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.miscount', ['qty' => '1-5']));

        // Case 6
        $cryptograms = [
            encrypt(''),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.incorrect'));

        // Case 7
        $cryptograms = [
            encrypt(['foo' => 'bar']),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.incorrect'));

        // Case 8
        $cryptograms = [
            null,
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.incorrect'));

        // Case 9
        $cryptograms = [
            ['foo' => 'bar'],
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.incorrect'));

        // Case 10
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
            encrypt(new FaMapper('bar', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.miscount', ['qty' => 1]));

        // Case 11
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 09:59:59'))),
        ];
        \Date::setTestNow('2025-10-03 10:30:01');
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1), trans('eloquent_notification::confirm.expired'));
        \Date::setTestNow('2025-10-03 10:00:00');

        // Case 12
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:00:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1, ['bar']), trans('eloquent_notification::confirm.incorrect'));

        // Case 13
        $cryptograms = [
            new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00')),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 1, ['bar']), trans('eloquent_notification::confirm.incorrect'));

        // Case 14
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa(null, 1, ['bar']), trans('eloquent_notification::confirm.miscount', ['qty' => '1-5']));

        // Case 15
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa('foo', 1, ['bar']), trans('eloquent_notification::confirm.miscount', ['qty' => '1-5']));
    }

    /**
     * @return void
     */
    public function test_validateFa_2fa()
    {
        \Date::setTestNow('2025-10-03 10:00:00');

        // Case 1
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'email' => 'FOO@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['id' => 123, 'phone' => '79000000000', 'email' => 'foo@example.org'], $this->confirmService->validateFa($cryptograms, 2));

        // Case 2
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['phone' => '79000000000', 'email' => 'foo@example.org'], $this->confirmService->validateFa($cryptograms, 2, ['foo', 'bar']));

        // Case 3
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['id' => 123], $this->confirmService->validateFa($cryptograms, 2));

        // Case 4
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 124, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 2), trans('eloquent_notification::confirm.incorrect'));

        // Case 5
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 2, ['foo', 'baz']), trans('eloquent_notification::confirm.incorrect'));

        // Case 6
        $cryptograms = [
            encrypt(new FaMapper('foo', ['phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 2), trans('eloquent_notification::confirm.incorrect'));

        // Case 7
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('foo', ['id' => 123, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 2), trans('eloquent_notification::confirm.incorrect'));
    }

    /**
     * @return void
     */
    public function test_validateFa_3fa()
    {
        \Date::setTestNow('2025-10-03 10:00:00');

        // Case 1
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:15:00'))),
            encrypt(new FaMapper('baz', ['id' => 123, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['id' => 123, 'phone' => '79000000000', 'email' => 'foo@example.org'], $this->confirmService->validateFa($cryptograms, 3));

        // Case 2
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:15:00'))),
            encrypt(new FaMapper('baz', ['phone' => '79000000000', 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertSame(['id' => 123, 'phone' => '79000000000', 'email' => 'foo@example.org'], $this->confirmService->validateFa($cryptograms, 3));

        // Case 3
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'phone' => '79000000001'], strtotime('2025-10-03 10:15:00'))),
            encrypt(new FaMapper('baz', ['id' => 123, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 3), trans('eloquent_notification::confirm.incorrect'));

        // Case 4
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:15:00'))),
            encrypt(new FaMapper('baz', ['id' => 123, 'email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 3), trans('eloquent_notification::confirm.incorrect'));

        // Case 5
        $cryptograms = [
            encrypt(new FaMapper('foo', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:00:00'))),
            encrypt(new FaMapper('bar', ['id' => 123, 'phone' => '79000000000'], strtotime('2025-10-03 10:15:00'))),
            encrypt(new FaMapper('baz', ['email' => 'foo@example.org'], strtotime('2025-10-03 10:30:00'))),
        ];
        $this->assertCustomValidationFailed(fn () => $this->confirmService->validateFa($cryptograms, 3), trans('eloquent_notification::confirm.incorrect'));
    }


    /**
     * @return void
     */
    public function test_fa()
    {
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';

            protected $casts = [
                'email' => 'string',
                'phone' => 'string',
                'password' => 'string',
                'totp_secret' => 'string',
            ];
        };

        $this->assertSame(
            ['email' => null, 'phone' => null, 'password' => null, 'totp' => null],
            $this->confirmService->fa(null)
        );

        $user->forceFill(['email' => null, 'phone' => null, 'password' => null, 'totp_secret' => null]);
        $this->assertSame(
            ['email' => null, 'phone' => null, 'password' => null, 'totp' => null],
            $this->confirmService->fa($user)
        );

        $user->forceFill(['email' => 'foo@example.org']);
        $this->assertEquals(
            ['email' => ['mask' => 'f*o@example.org', 'value' => true], 'phone' => null, 'password' => null, 'totp' => null],
            $this->confirmService->fa($user)
        );
        $this->assertSame('foo@example.org', decrypt($this->confirmService->fa($user)['email']['value']));

        $user->forceFill(['email' => 'foobar@example.org.net', 'phone' => '79000000002']);
        $this->assertEquals(
            ['email' => ['mask' => 'fo**ar@example.org.net', 'value' => true], 'phone' => ['mask' => '79*******02', 'value' => true], 'password' => null, 'totp' => null],
            $this->confirmService->fa($user)
        );
        $this->assertSame('foobar@example.org.net', decrypt($this->confirmService->fa($user)['email']['value']));
        $this->assertSame('79000000002', decrypt($this->confirmService->fa($user)['phone']['value']));

        $user->forceFill(['email' => 'h-e-l-l-o@example.net', 'phone' => '7912345678', 'password' => 'foo']);
        $this->assertEquals(
            ['email' => ['mask' => 'h-*****-o@example.net', 'value' => true], 'phone' => ['mask' => '79******78', 'value' => true], 'password' => true, 'totp' => null],
            $this->confirmService->fa($user)
        );
        $this->assertSame('h-e-l-l-o@example.net', decrypt($this->confirmService->fa($user)['email']['value']));
        $this->assertSame('7912345678', decrypt($this->confirmService->fa($user)['phone']['value']));

        $user->forceFill(['totp_secret' => 'bar']);
        $this->assertEquals(
            ['email' => ['mask' => 'h-*****-o@example.net', 'value' => true], 'phone' => ['mask' => '79******78', 'value' => true], 'password' => true, 'totp' => true],
            $this->confirmService->fa($user)
        );
    }

    /**
     * @return void
     */
    public function test_faAtLeast()
    {
        $user = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';

            protected $casts = [
                'email' => 'string',
                'phone' => 'string',
                'password' => 'string',
                'totp_secret' => 'string',
            ];
        };

        $this->assertFalse($this->confirmService->faAtLeast(1, null));
        $this->assertFalse($this->confirmService->faAtLeast(2, null));

        $user->forceFill(['email' => null, 'phone' => null, 'password' => null, 'totp_secret' => null]);
        $this->assertFalse($this->confirmService->faAtLeast(1, $user));
        $this->assertFalse($this->confirmService->faAtLeast(2, $user));

        $user->forceFill(['email' => 'foo@example.org']);
        $this->assertTrue($this->confirmService->faAtLeast(1, $user));
        $this->assertFalse($this->confirmService->faAtLeast(2, $user));

        $user->forceFill(['phone' => '79000000002']);
        $this->assertTrue($this->confirmService->faAtLeast(1, $user));
        $this->assertTrue($this->confirmService->faAtLeast(2, $user));

        $user->forceFill(['password' => 'foo']);
        $this->assertTrue($this->confirmService->faAtLeast(1, $user));
        $this->assertTrue($this->confirmService->faAtLeast(2, $user));
    }
}
