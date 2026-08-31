---
name: anourvalar-eloquent-notification
description: Load when working in a Laravel app that uses the anourvalar/eloquent-notification package - per-user notification channel settings (UserNotification model + AbstractNotification), the ConfirmService (email/phone/PoW/TOTP/nFA confirmation flows), confirm.* HTTP middleware, and the SMS/Telegram/Push/Exchanger driver adapters.
---

# AnourValar Eloquent Notification

Laravel package that adds a "model layer" on top of `Illuminate\Notifications`. It stores per-user channel preferences in a `user_notifications` table, adds custom `telegram`/`sms`/`push` notification channels, provides delayed/grouped notification collection, and exposes a `ConfirmService` for email/phone code confirmation, Proof-of-Work, TOTP and n-Factor authentication flows together with ready-to-use route middleware.

## When to use

- Building per-user notification preference UIs (toggle channels per trigger) in a Laravel app.
- Sending notifications via custom `telegram`, `sms` or `push` channels registered by this package.
- Extending `AnourValar\EloquentNotification\AbstractNotification` instead of `Illuminate\Notifications\Notification`.
- Implementing email/phone code verification, PoW captcha, TOTP, or n-factor confirmation endpoints.
- Wiring confirm middleware (`confirm.pow`, `confirm.email.input`, `confirm.email.my`, `confirm.phone.input`, `confirm.phone.my`, `confirm.totp.input`, `confirm.totp.my`, `confirm.fa.my`).
- Implementing or swapping SMS/Telegram/Push/Exchanger adapters (`SmsInterface`, `TelegramInterface`, `PushInterface`, `ExchangerInterface`).
- Grouping/batching delayed notifications via `Service::collectNotify()` and `CollectNotificationJob`.

## Facades

This package does NOT register any Laravel facades. Resolve its services through the container (`App::make(...)`, dependency injection, or `app()->make(...)`).

## Services

### `AnourValar\EloquentNotification\Service`

Manages per-user notification preferences and delayed group notify.

- `sync(\Illuminate\Contracts\Auth\Authenticatable $user, array $data, mixed $validatePrefix = null, ?string $group = null): void` - upsert/delete `UserNotification` rows so `$data` (a `['trigger' => ['mail','sms',...]]` map) becomes the user's full preferences. When `$group` is set, only triggers whose `eloquent_notification.trigger.{trigger}.{group}` config flag is truthy are mutable; throws `AnourValar\EloquentValidation\Exceptions\ValidationException` on violations.
- `channels(): array` - returns `[channel => translated label]` for every channel referenced by any configured trigger.
- `collectNotify(\Illuminate\Foundation\Auth\User $user, string $notificationClass, array $notificationArguments, string $groupBy = '', ?int $delaySeconds = null): void` - queues a deduplicated, delayed `CollectNotificationJob` that aggregates pushed arguments via `Atom::exchangerPush`, then dispatches one `$notificationClass` per `$groupBy` bucket. Default delay comes from `eloquent_notification.collect_delay_seconds`.

```php
use AnourValar\EloquentNotification\Service;

$service = app(Service::class);

// Replace the user's notification preferences in one call.
$service->sync($user, [
    'logged_in'      => ['mail', 'telegram'],
    'password_reset' => ['sms'],
]);

// Batch a delayed notification (deduped per user by unique-until-processing job).
$service->collectNotify($user, \App\Notifications\NewCommentNotification::class, [
    'comments' => [$comment->id],
]);
```

### `AnourValar\EloquentNotification\ConfirmService`

Aggregates the `TotpTrait`, `PowTrait` and `FaTrait`. All `validate*` methods throw `AnourValar\EloquentValidation\Exceptions\ValidationException` on failure and apply throttling per `eloquent_notification.confirm.throttle.*` config.

Email / phone confirmation:

- `requestEmail($email, $emailShouldExists = null, ?string $code = null, string $emailAttribute = 'email', array $notificationParams = []): array` - validates the email, optionally checks existence (`true`/`false`) on the auth user model, throttles, sends `eloquent_notification.confirm.notification` to a `PersonMapper`, and returns `['cryptogram_email' => '...']`.
- `validateEmail($cryptogramEmail, $inputCodeEmail, $inputEmail, string $validateKey = 'code_email'): string` - decrypts the cryptogram, checks expiry/throttle and that input email + code match. Returns the normalized email.
- `requestPhone($phone, string $validationRule, $phoneShouldExists = null, ?string $code = null, string $phoneAttribute = 'phone', array $notificationParams = []): array` - same flow for phone; `$validationRule` is your phone format validator (e.g. `'regex:/^\+?[0-9]+$/'`). Returns `['cryptogram_phone' => '...']`.
- `validatePhone($cryptogramPhone, $inputCodePhone, $inputPhone, string $validateKey = 'code_phone'): string` - mirror of `validateEmail` for phone.
- `cryptoRandom(int $length = 15, string $alphabet = '0-9a-zA-Z'): string` - helper to make a random code/cryptogram secret.

TOTP (via `TotpTrait`):

- `generateTotp(int $bytes = 20): string` - new base32 secret; in non-production echoes the otpauth URL via `ExchangerInterface`.
- `validateTotp(?string $secretBase32, $code, string $validateKey = 'code_totp', int $window = 1, int $step = 30, int $digits = 6, string $algo = 'sha1'): true`.
- `validateTotpCryptogram($cryptogram, $code, string $validateKey = 'code_totp', int $window = 1, int $step = 30, int $digits = 6, string $algo = 'sha1'): true` - same but accepts the encrypted secret produced by `cryptogramTotp()`.
- `urlTotp(string $account, string $secretBase32, ?string $issuer = null, int $digits = 6, string $algo = 'SHA1'): string` - returns an `otpauth://totp/...` URL (QR payload).
- `cryptogramTotp(string $secretBase32): string` - encrypts a TOTP secret for safe storage / transport.
- `codeTotp(string $secretBase32, ?int $time = null, int $step = 30, int $digits = 6, string $algo = 'sha1'): string` - current OTP code.

Proof-of-Work (via `PowTrait`):

- `requestPow(?int $cost = null): array` - returns `['salt' => ..., 'cryptogram_pow' => ..., 'puzzle_pow' => [...]]`. Client must brute-force `sha256(salt + i)` matches; see comment block in `src/Traits/PowTrait.php` for the JS solver. Cost defaults to `eloquent_notification.confirm.pow_cost`.
- `validatePow($puzzlePow, $cryptogramPow): true` - one-shot validation (uses `Cache::add` to block reuse).

n-Factor authentication (via `FaTrait`):

- `validateFa($cryptograms, int|callable $qty, array $faWhite = [], array $faBlack = []): array` - verifies an array of `FaMapper` cryptograms; enforces count, uniqueness, expiry, optional white/black lists, and that all factors share at least one identifying contact. Returns the merged contacts array.
- `fa(?\Illuminate\Foundation\Auth\User $user): array` - returns available factors (`email`, `phone`, `password`, `totp`) for the user, with masked values + encrypted handle (auto-detected from the user model's casts).
- `faAtLeast(int $qty, ?\Illuminate\Foundation\Auth\User $user): bool`.

```php
use AnourValar\EloquentNotification\ConfirmService;

$confirm = app(ConfirmService::class);

// Request: send a code, return cryptogram to the client.
$payload = $confirm->requestEmail($request->input('email'), emailShouldExists: false);

// Validate later: throws ValidationException with HTTP 4xx on failure.
$normalizedEmail = $confirm->validateEmail(
    $request->input('cryptogram_email'),
    $request->input('code_email'),
    $request->input('email'),
);
```

### `AnourValar\EloquentNotification\AbstractNotification`

Abstract base class for app-level notifications. Extend it instead of `Illuminate\Notifications\Notification`.

- Implements `ShouldQueue` + `Queueable`, calls `afterCommit()` in constructor.
- `via($notifiable)` automatically returns the channels the user has enabled for this trigger (from `user_notifications`), respects soft-deleted notifiables, applies optional duplicate-suppression and cache (`cacheChannels`, default 120s).
- `databaseType($notifiable)` returns the configured trigger name (so `database` channel rows get a stable type).
- Hooks to override in subclasses: `preventDuplicates(): int` (lock seconds, default `0`), `cacheChannels(): int` (default `120`), `notifiableIdForSettings($notifiable): int` (default `$notifiable->id`).
- Helper `markdown(string ...$markdown): \Illuminate\Support\HtmlString` for inline markdown rendering.
- Every subclass MUST be registered as a trigger in `config('eloquent_notification.trigger')` with key `bind`, otherwise `via()` throws `\RuntimeException`. The `channels` config key whitelists which channels the user may enable.

### `AnourValar\EloquentNotification\UserNotification` (Eloquent model)

Stores `user_id`, `trigger`, `channels` (JSON array). Should be replaced by an app-local `App\UserNotification` (the stub is published to `app/`) and pointed at via `eloquent_notification.model`. The `AnourValarEloquentNotificationServiceProvider` registers `UserNotificationObserver` on whichever class is configured; the observer auto-deletes rows when `channels` becomes empty.

### `AnourValar\EloquentNotification\PersonMapper`

Lightweight notifiable used by `ConfirmService` to send to a raw email/phone:

```php
use AnourValar\EloquentNotification\PersonMapper;

(new PersonMapper(email: 'foo@example.org', locale: 'en'))
    ->notify(new \App\Notifications\Welcome());
```

Constructor throws `\RuntimeException` if both `email` and `phone` are null.

### `AnourValar\EloquentNotification\FaMapper`

Immutable value object that represents one verified factor for the n-FA flow.

- `__construct(string $name, array $contacts, ?int $expiredTimestamp = null)` - `contacts` must be non-empty (strings are lower-cased). Default expiry comes from `eloquent_notification.confirm.fa_expire`.
- `encrypt(): string` / `__toString()` - returns an encrypted token to ship to the client.
- Decrypt with Laravel's `decrypt()`; values are gzcompressed JSON internally.

### Notification channels (drivers)

Registered by the service provider via `Notification::extend(...)`. Implement the matching `to*` method on your notification and route via `routeNotificationFor*` on the notifiable:

- `\AnourValar\EloquentNotification\Drivers\TelegramChannel` ('telegram') - calls `Notification->toTelegram($notifiable)`, sends via `TelegramInterface`. On `Error::USER_BLOCK` fires the `TelegramUsernameBlocked` event before re-throwing.
- `\AnourValar\EloquentNotification\Drivers\SmsChannel` ('sms') - calls `Notification->toSms($notifiable)`, sends via `SmsInterface`.
- `\AnourValar\EloquentNotification\Drivers\PushChannel` ('push') - calls `Notification->toPush($notifiable): array{title:string, body:string, data?:array}`, fans out via `PushInterface` to up to 10 tokens.

### Adapter interfaces (bind these to swap providers)

- `AnourValar\EloquentNotification\Adapters\Sms\SmsInterface` - `sendMessage(string $phone, string $message): void`. Implementations: `MtsAdapter`, `SmscAdapter`, `ExchangerAdapter`, `MockAdapter`. Default: `MtsAdapter`.
- `AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface` - `fromConfig(array $config): self`, `sendMessage(string $chatId, string $message): void`, `fetchChatId(string $username): ?string`. Default: `TelegramAdapter`.
- `AnourValar\EloquentNotification\Adapters\Push\PushInterface` - `fromConfig(array $config): self`, `sendMessage(string $receiver, string $title, string $body, array $data = []): void`. Default: `FCMAdapter`.
- `AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface` - `sendMessage(string $title, string $body, string $tag = 'default', bool $html = false): void`. Default: `NullAdapter` (use `MailAdapter` for dev mail dump).

All four are bound as singletons in `register()` of the service provider using `config('eloquent_notification.bindings')`; constructor receives the corresponding sub-array (token, sender, etc.) when resolved through the container.

### Confirm middleware

All resolve `ConfirmService` from the container. Body of every middleware uses the same suffix convention: cryptograms come in as `cryptogram_<suffix>`, codes as `code_<suffix>`.

| Alias suggestion | Class | Behavior |
| --- | --- | --- |
| `confirm.pow` | `Http\Middleware\ConfirmPow` | `validatePow($request->input('puzzle_pow'), $request->input('cryptogram_pow'))`. |
| `confirm.email.input` | `Http\Middleware\ConfirmEmailInput` | Validates input email against cryptogram + code; rewrites the request's email key with the normalized value. Args: `emailKey='email'`, `suffix='email'`. |
| `confirm.email.my` | `Http\Middleware\ConfirmEmailMy` | Validates code against `$request->user()->{$emailKey}`. |
| `confirm.phone.input` | `Http\Middleware\ConfirmPhoneInput` | Phone twin of `ConfirmEmailInput`. |
| `confirm.phone.my` | `Http\Middleware\ConfirmPhoneMy` | Phone twin of `ConfirmEmailMy`. |
| `confirm.totp.input` | `Http\Middleware\ConfirmTotpInput` | Validates TOTP via `validateTotpCryptogram(cryptogram_<suffix>, code_<suffix>)`. |
| `confirm.totp.my` | `Http\Middleware\ConfirmTotpMy` | Validates TOTP using `$request->user()->{$secretKey}` (default `totp_secret`). |
| `confirm.fa.my` | `Http\Middleware\ConfirmFaMy` | Args: `cryptogramsKey`, `qty`, `...faBlack`. Validates n-FA cryptograms and asserts every returned contact matches `$request->user()->{$key}`; otherwise throws `AuthorizationException`. Skipped when the request is precognitive. |

### Jobs / events / exceptions

- `Jobs\CollectNotificationJob` - `ShouldBeUniqueUntilProcessing` job dispatched by `Service::collectNotify()`; unique per `$user->id`. Pulls aggregated arguments via `Atom::exchangerPull` and sends one notification per `(class, group_by)` bucket.
- `Events\TelegramUsernameBlocked($user)` - fired when `TelegramChannel` catches `ExternalException` with `Error::USER_BLOCK`.
- `Exceptions\Error` - enum (`USER_BLOCK`, `ETC`) used by adapters.
- `Exceptions\ExternalException` - all adapter failures throw this; logs via `report()`, surfaces dump via `context()` (Horizon-friendly).
- `Notifications\ConfirmNotification(string $code, array $params = [])` - default notification sent by `ConfirmService::requestEmail/requestPhone`. Override via `eloquent_notification.confirm.notification`. `$params` keys: `subject`, `markdown`, `message`.

## Usage examples

Setting up a custom notification with per-user channels:

```php
// config/eloquent_notification.php
'trigger' => [
    'new_post' => [
        'bind'     => \App\Notifications\Triggers\NewPostNotification::class,
        'title'    => 'eloquent_notification::user_notification.trigger.new_post',
        'channels' => ['mail', 'sms', 'telegram', 'push', 'database'],
        'is_public' => true,
    ],
],
```

```php
// app/Notifications/Triggers/NewPostNotification.php
namespace App\Notifications\Triggers;

use Illuminate\Notifications\Messages\MailMessage;
use AnourValar\EloquentNotification\AbstractNotification;

class NewPostNotification extends AbstractNotification
{
    public function __construct(public \App\Models\Post $post)
    {
        parent::__construct(); // required - sets afterCommit()
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->post->title)
            ->line($this->markdown($this->post->summary));
    }

    public function toSms(object $notifiable): string
    {
        return "New post: {$this->post->title}";
    }

    public function toPush(object $notifiable): array
    {
        return ['title' => 'New post', 'body' => $this->post->title, 'data' => ['post_id' => $this->post->id]];
    }
}

// Dispatch it - the user only receives via channels they have enabled.
$user->notify(new NewPostNotification($post));
```

Email confirmation endpoints:

```php
use AnourValar\EloquentNotification\ConfirmService;

Route::post('/email/request', function (Request $request, ConfirmService $confirm) {
    return $confirm->requestEmail($request->string('email')->toString(), emailShouldExists: false);
});

Route::post('/email/verify', function (Request $request, ConfirmService $confirm) {
    $email = $confirm->validateEmail(
        $request->input('cryptogram_email'),
        $request->input('code_email'),
        $request->input('email'),
    );
    // ...persist $email...
});

// Or guard a route with the published middleware aliases:
Route::post('/profile/email', SetEmailController::class)
    ->middleware(['auth', 'confirm.email.input']);
```

TOTP setup and validation:

```php
$confirm = app(\AnourValar\EloquentNotification\ConfirmService::class);

$secret = $confirm->generateTotp();
$qrUrl  = $confirm->urlTotp($user->email, $secret);
$stored = $confirm->cryptogramTotp($secret); // -> save to users.totp_secret

// On login second step:
$confirm->validateTotpCryptogram($user->totp_secret, $request->input('code_totp'));
```

n-Factor confirmation:

```php
use AnourValar\EloquentNotification\FaMapper;

// After verifying one factor, ship the user a FaMapper cryptogram:
$factor = new FaMapper(name: 'email', contacts: ['id' => $user->id, 'email' => $user->email]);
return ['cryptogram_fa' => $factor->encrypt()];

// Then later require 2 factors to match the current user:
Route::post('/dangerous', DangerousController::class)
    ->middleware(['auth', 'confirm.fa.my:cryptograms_fa,2']);
```

Swapping an SMS adapter:

```php
// config/eloquent_notification.php
AnourValar\EloquentNotification\Adapters\Sms\SmsInterface::class => [
    'bind'        => \App\Sms\TwilioAdapter::class, // implements SmsInterface
    'account_sid' => env('TWILIO_SID'),
    'auth_token'  => env('TWILIO_TOKEN'),
    'from'        => env('TWILIO_FROM'),
],
```

```php
namespace App\Sms;

use AnourValar\EloquentNotification\Adapters\Sms\SmsInterface;
use AnourValar\EloquentNotification\Exceptions\{ExternalException, Error};

class TwilioAdapter implements SmsInterface
{
    public function __construct(string $account_sid, string $auth_token, string $from) { /* ... */ }

    public function sendMessage(string $phone, string $message): void
    {
        // ...call API, on failure:
        throw new ExternalException('twilio.send', ['phone' => $phone], Error::ETC);
    }
}
```

## Conventions / gotchas

- The service provider's `boot()` method calls `$class::observe(...)` on `config('eloquent_notification.model')`. That config key MUST point at an Eloquent model class - the stub at `src/resources/stubs/UserNotification.php` is published into `app/` by `php artisan vendor:publish`; update `eloquent_notification.model` to point at your published `App\UserNotification` after publishing.
- Publish resources with `php artisan vendor:publish --provider="AnourValar\\EloquentNotification\\Providers\\AnourValarEloquentNotificationServiceProvider"` - this drops the config, migrations, model stub, langs and views.
- Every subclass of `AbstractNotification` must be registered in `eloquent_notification.trigger[<key>][bind] = NotificationClass::class`; otherwise `via()` throws.
- `AbstractNotification::__construct()` calls `parent::__construct()` which calls `afterCommit()` and is required - child constructors must invoke `parent::__construct()`.
- `Service::collectNotify` relies on `\Atom::exchangerPush` / `exchangerPull` from `anourvalar/laravel-atom` and a queue worker - it queues `CollectNotificationJob` with a delay, so the queue MUST be running for the aggregated notification to be sent.
- `routeNotificationForTelegram` should return the chat ID (not username); `routeNotificationForSms` returns the phone string; `routeNotificationForPush` may return a single token, an array of tokens, or null. The push channel truncates to 10 tokens.
- `ConfirmService` throttling is enforced via `RateLimiter` keyed off the cryptogram's sha1 / the email / phone - `429` is set on the `ValidationException` (`->status(429)`). `FaTrait` failures throw with `->status(403)`.
- `ConfirmService::validateEmail` / `validatePhone` lower-case all values and additionally treat strings longer than 100 chars as Laravel `decrypt()` payloads (used to ship the value through the client without exposing it).
- The TOTP secret used with `validateTotpCryptogram` must be wrapped via `cryptogramTotp()`; raw secrets only work with `validateTotp()`.
- `FaMapper::contacts` is lower-cased on construction; `ConfirmFaMy` middleware enforces strict equality against `$request->user()->{$contactKey}`, so do not store mixed-case emails on the user model.
- Adapter `bind` config arrays are spread into the implementation constructor in declaration order (`new $impl(...$arguments)`), so positional argument order matters when writing custom adapters.
- The default `eloquent_notification.confirm.notification` is `Notifications\ConfirmNotification` which sends `mail` + `sms`; override `via()` or replace the class to add other channels.
- This package requires PHP 8.4+ and Laravel 10/11/12/13 (per `composer.json`).
