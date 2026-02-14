<?php

namespace AnourValar\EloquentNotification\Traits;

use AnourValar\EloquentValidation\Exceptions\ValidationException;

/**
 * The factor should return the user ID [if identified] + verified contact
 * In addition to the factor's cryptogram, it may return the availability of other factors with value masks // see FaTrait::fa()
 *
 * Factors:
 * - Password
 * - Code sent to phone (sms/whatsapp/telegram)
 * - Code sent to email
 * - Session (including on other devices ["transfer" via QR code (base64_decode from encrypt saves ~25%)])
 * - TOTP
 * - "Whitelist" of IP addresses
 * - Security questions
 * - One-time recovery codes [stored in a file or printed]
 */

trait FaTrait
{
    /**
     * Verification (n)FA
     *
     * @param mixed $cryptograms
     * @param int|callable $qty
     * @param array $faWhite
     * @param array $faBlack
     * @return array
     * @throws \RuntimeException
     */
    public function validateFa($cryptograms, int|callable $qty, array $faWhite = [], array $faBlack = []): array
    {
        // Cryptograms qty (pre)
        if (! is_array($cryptograms) || ! $cryptograms || count($cryptograms) > 5) {
            throw (new ValidationException(trans('eloquent_notification::confirm.miscount', ['qty' => '1-5'])))->status(403);
        }


        // Cryptograms
        $contacts = null;
        foreach ($cryptograms as $cryptogram) {
            // Correct
            $cryptogram = $this->safeDecrypt($cryptogram);
            if (! $cryptogram instanceof \AnourValar\EloquentNotification\FaMapper) {
                throw (new ValidationException(trans('eloquent_notification::confirm.incorrect')))->status(403);
            }

            // Lifecycle
            if ($cryptogram->expiredAt < now()->timestamp) {
                throw new ValidationException(trans('eloquent_notification::confirm.expired'));
            }

            // Available (and unique)
            if (in_array($cryptogram->name, $faBlack) || ($faWhite && ! in_array($cryptogram->name, $faWhite))) {
                throw (new ValidationException(trans('eloquent_notification::confirm.incorrect')))->status(403);
            }
            $faBlack[] = $cryptogram->name;

            // Union contacts
            if (isset($contacts) && ! array_intersect($contacts, $cryptogram->contacts)) {
                throw (new ValidationException(trans('eloquent_notification::confirm.incorrect')))->status(403);
            }

            // Same contacts
            foreach ($cryptogram->contacts as $key => $value) {
                if (isset($contacts[$key]) && $contacts[$key] !== $value) {
                    throw (new ValidationException(trans('eloquent_notification::confirm.incorrect')))->status(403);
                }
            }

            // List of contacts
            $contacts = array_replace((array) $contacts, $cryptogram->contacts);
        }


        // Cryptograms qty (post)
        if (is_callable($qty)) {
            $qty = $qty($contacts);
        }
        if (count($cryptograms) != $qty) {
            throw (new ValidationException(trans('eloquent_notification::confirm.miscount', ['qty' => $qty])))->status(403);
        }


        // Singleton
        foreach ($cryptograms as $cryptogram) {
            $this->throttle('fa_validate', $cryptogram);
        }


        // Result
        if (! $contacts) {
            throw new \RuntimeException('Incorrect usage.');
        }

        return $contacts;
    }

    /**
     * User's factors
     *
     * @param \Illuminate\Foundation\Auth\User|null $user
     * @return array
     */
    public function fa(?\Illuminate\Foundation\Auth\User $user): array
    {
        return [
            'email' => $user?->email ? ['mask' => preg_replace('#(?<=.).(?=[^@]+@)#u', '*', $user->email), 'value' => encrypt($user->email)] : null,
            'phone' => $user?->phone ? ['mask' => preg_replace('#(?<=.{2}).(?=.{2})#u', '*', $user->phone), 'value' => encrypt($user->phone)] : null,
            'password' => $user?->password ? true : null,
            'totp' => $user?->totp_secret ? true : null,
        ];
    }

    /**
     * Has user N factors
     *
     * @param int $qty
     * @param \Illuminate\Foundation\Auth\User|null $user
     * @return bool
     */
    public function faAtLeast(int $qty, ?\Illuminate\Foundation\Auth\User $user): bool
    {
        return count(array_filter($this->fa($user))) >= $qty;
    }
}
