<?php

namespace AnourValar\EloquentNotification;

use AnourValar\EloquentValidation\Exceptions\ValidationException;

class ConfirmService
{
    use \AnourValar\EloquentNotification\Traits\TotpTrait;
    use \AnourValar\EloquentNotification\Traits\PowTrait;
    use \AnourValar\EloquentNotification\Traits\FaTrait;

    /**
     * Request a verification for an email
     *
     * @param mixed $email
     * @param mixed $emailShouldExists
     * @param string|null $code
     * @param string $emailAttribute
     * @param array $notificationParams
     * @return array
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function requestEmail(
        $email,
        $emailShouldExists = null,
        ?string $code = null,
        string $emailAttribute = 'email',
        array $notificationParams = []
    ): array {
        if (! isset($code)) {
            $code = $this->cryptoRandom(15);
        }

        // Validation
        $email = $this->normalizeValue($email);
        \Validator::validate(
            [$emailAttribute => $email, 'email_should_exists' => $emailShouldExists],
            [
                $emailAttribute => ['required', 'string', 'min:2', 'max:100', 'email:filter'],
                'email_should_exists' => ['nullable', 'not_empty', 'boolean'],
            ]
        );

        // Check if exists
        if (isset($emailShouldExists)) {
            $exists = $this->getUserModel()->where($emailAttribute, '=', $email)->first();

            if ($emailShouldExists && ! $exists) {
                throw new ValidationException(trans('eloquent_notification::confirm.email_not_exists'));
            }

            if (! $emailShouldExists && $exists) {
                throw new ValidationException(trans('eloquent_notification::confirm.email_already_exists'));
            }
        }

        // Throttle
        $this->throttle('request_email', $email);

        // Generate a request and send a notification
        $cryptogram = encrypt([
            'type' => 'confirm.email',
            'code' => $code,
            'email' => $email,
            'expired_at' => now()->addSeconds(config('eloquent_notification.confirm.email_expire'))->timestamp,
        ]);

        $class = config('eloquent_notification.confirm.notification');
        (new PersonMapper(email: $email, locale: \App::getLocale()))->notify(new $class($code, $notificationParams));
        return ['cryptogram_email' => $cryptogram];
    }

    /**
     * Check a verification for an email
     *
     * @param mixed $cryptogramEmail
     * @param mixed $inputCodeEmail
     * @param mixed $inputEmail
     * @param string $validateKey
     * @return string
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function validateEmail($cryptogramEmail, $inputCodeEmail, $inputEmail, string $validateKey = 'code_email'): string
    {
        if (is_numeric($inputCodeEmail)) {
            $inputCodeEmail = (string) $inputCodeEmail;
        }
        $inputEmail = $this->normalizeValue($inputEmail);

        try {
            if (is_string($cryptogramEmail)) {
                $sha1 = sha1($cryptogramEmail);
                $cryptogramEmail = decrypt($cryptogramEmail);
            } else {
                $sha1 = '';
                $cryptogramEmail = null;
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if (($cryptogramEmail['type'] ?? null) !== 'confirm.email') {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if ($cryptogramEmail['expired_at'] < now()->timestamp) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.expired')]);
        }

        $this->throttle('validate_email', $sha1, 'eloquent_notification::confirm.expired');

        if (! isset($inputEmail)) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.email_is_empty')]);
        }

        if ($cryptogramEmail['email'] !== $inputEmail) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.incorrect_code')]);
        }

        if ($cryptogramEmail['code'] !== $inputCodeEmail) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.incorrect_code')]);
        }

        $this->throttle('validate_email', $sha1, 'eloquent_notification::confirm.expired', true);
        return $inputEmail;
    }

    /**
     * Request a verification for a phone
     *
     * @param mixed $phone
     * @param string $validationRule
     * @param mixed $phoneShouldExists
     * @param string|null $code
     * @param string $phoneAttribute
     * @param array $notificationParams
     * @return array
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function requestPhone(
        $phone,
        string $validationRule,
        $phoneShouldExists = null,
        ?string $code = null,
        string $phoneAttribute = 'phone',
        array $notificationParams = []
    ): array {
        if (! isset($code)) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        // Validation
        $phone = $this->normalizeValue($phone);
        \Validator::validate(
            [$phoneAttribute => $phone, 'phone_should_exists' => $phoneShouldExists],
            [
                $phoneAttribute => ['required', 'string', 'min:2', 'max:20', 'bail', $validationRule],
                'phone_should_exists' => ['nullable', 'not_empty', 'boolean'],
            ]
        );

        // Check if exists
        if (isset($phoneShouldExists)) {
            $exists = $this->getUserModel()->where($phoneAttribute, '=', $phone)->first();

            if ($phoneShouldExists && ! $exists) {
                throw new ValidationException(trans('eloquent_notification::confirm.phone_not_exists'));
            }

            if (! $phoneShouldExists && $exists) {
                throw new ValidationException(trans('eloquent_notification::confirm.phone_already_exists'));
            }
        }

        // Throttle
        $this->throttle('request_phone', $phone);

        // Generate a request and send a notification
        $cryptogram = encrypt([
            'type' => 'confirm.phone',
            'code' => $code,
            'phone' => $phone,
            'expired_at' => now()->addSeconds(config('eloquent_notification.confirm.phone_expire'))->timestamp,
        ]);

        $class = config('eloquent_notification.confirm.notification');
        (new PersonMapper(phone: $phone, locale: \App::getLocale()))->notify(new $class($code, $notificationParams));
        return ['cryptogram_phone' => $cryptogram];
    }

    /**
     * Check a verification for a phone
     *
     * @param mixed $cryptogramPhone
     * @param mixed $inputCodePhone
     * @param mixed $inputPhone
     * @param string $validateKey
     * @return string
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function validatePhone($cryptogramPhone, $inputCodePhone, $inputPhone, string $validateKey = 'code_phone'): string
    {
        if (is_numeric($inputCodePhone)) {
            $inputCodePhone = (string) $inputCodePhone;
        }
        $inputPhone = $this->normalizeValue($inputPhone);

        try {
            if (is_string($cryptogramPhone)) {
                $sha1 = sha1($cryptogramPhone);
                $cryptogramPhone = decrypt($cryptogramPhone);
            } else {
                $sha1 = '';
                $cryptogramPhone = null;
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if (($cryptogramPhone['type'] ?? null) !== 'confirm.phone') {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if ($cryptogramPhone['expired_at'] < now()->timestamp) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.expired')]);
        }

        $this->throttle('validate_phone', $sha1, 'eloquent_notification::confirm.expired');

        if (! isset($inputPhone)) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.phone_is_empty')]);
        }

        if ($cryptogramPhone['phone'] !== $inputPhone) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.incorrect_code')]);
        }

        if ($cryptogramPhone['code'] !== $inputCodePhone) {
            throw new ValidationException([$validateKey => trans('eloquent_notification::confirm.incorrect_code')]);
        }

        $this->throttle('validate_phone', $sha1, 'eloquent_notification::confirm.expired', true);
        return $inputPhone;
    }

    /**
     * Generate a cryptogram random string
     *
     * @param int $length
     * @param string $alphabet
     * @return string
     */
    public function cryptoRandom(int $length = 15, string $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $result = '';

        $maxIndex = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $maxIndex)];
        }

        return $result;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getUserModel(): \Illuminate\Database\Eloquent\Builder
    {
        $class = config('auth.providers.users.model');

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($class))) {
            return $class::withTrashed();
        }

        return $class::query();
    }

    /**
     * @param string $name
     * @param string $key
     * @param string $error
     * @param bool $payOff
     * @return void
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    protected function throttle(string $name, string $key, string $error = 'eloquent_notification::confirm.too_many', bool $payOff = false): void
    {
        foreach (config("eloquent_notification.confirm.throttle.{$name}") as $index => $policy) {
            $cacheKey = implode(' / ', [__METHOD__, $name, $index, $key]);

            if (\RateLimiter::tooManyAttempts($cacheKey, $policy['limit'])) {
                throw (new ValidationException(trans($error, ['seconds' => \RateLimiter::availableIn($cacheKey)])))->status(429);
            }

            \RateLimiter::increment($cacheKey, $policy['seconds'], $payOff ? $policy['limit'] : 1);
        }
    }

    /**
     * @param mixed $cryptogram
     * @param bool $serialize
     * @return mixed
     */
    private function safeDecrypt($cryptogram, bool $serialize = true)
    {
        try {
            return is_string($cryptogram) ? decrypt($cryptogram, $serialize) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue($value)
    {
        try {
            if (is_string($value) && mb_strlen($value) > 100 && $decrypt = decrypt($value)) {
                $value = $decrypt;
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {

        }

        if (is_string($value)) {
            $value = mb_strtolower($value);
        }

        return $value;
    }
}
