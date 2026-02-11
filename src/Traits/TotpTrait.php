<?php

namespace AnourValar\EloquentNotification\Traits;

use AnourValar\EloquentValidation\Exceptions\ValidationException;
use AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface;

trait TotpTrait
{
    /**
     * Generate secret for a new TOTP session
     *
     * @param int $bytes
     * @return string
     */
    public function generateTotp(int $bytes = 20): string
    {
        $secret = $this->base32EncodeNopad(random_bytes($bytes));

        if (! \App::isProduction()) {
            \App::make(ExchangerInterface::class)->sendMessage(
                'Session',
                $this->urlTotp('Debug', $secret),
                'TOTP'
            );
        }

        return $secret;
    }

    /**
     * Verify code for a TOTP session
     *
     * @param string|null $secretBase32
     * @param mixed $code
     * @param string $validateKey
     * @param int $window
     * @param int $step
     * @param int $digits
     * @param string $algo
     * @return true
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function validateTotp(
        ?string $secretBase32,
        $code,
        string $validateKey = 'code_totp',
        int $window = 1,
        int $step = 30,
        int $digits = 6,
        string $algo = 'sha1'
    ): true {
        if (! $secretBase32) {
            throw new ValidationException([$validateKey => trans('notification::confirm.incorrect_code')]);
        }

        if (! is_numeric($code) || strlen($code) != $digits) {
            throw new ValidationException([$validateKey => trans('notification::confirm.incorrect_code')]);
        }
        $code = (string) $code;

        // Throttle
        $this->throttle('totp_validate', $secretBase32);

        $now = now()->timestamp;
        $secretBin = $this->base32DecodeNopad($secretBase32);
        $currentCounter = (int) floor($now / $step);

        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $this->hotpBin($secretBin, $currentCounter + $i, $digits, $algo);
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        throw new ValidationException([$validateKey => trans('notification::confirm.incorrect_code')]);
    }

    /**
     * Verify code for a TOTP session (encrypted)
     *
     * @param mixed $cryptogram
     * @param mixed $code
     * @param string $validateKey
     * @param int $window
     * @param int $step
     * @param int $digits
     * @param string $algo
     * @return true
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function validateTotpCryptogram(
        $cryptogram,
        $code,
        string $validateKey = 'code_totp',
        int $window = 1,
        int $step = 30,
        int $digits = 6,
        string $algo = 'sha1'
    ): true {
        $cryptogram = $this->safeDecrypt($cryptogram, false);
        if (is_string($cryptogram)) {
            $cryptogram = json_decode($cryptogram, true);
        }

        if (($cryptogram['type'] ?? null) !== 'confirm.totp') {
            throw new ValidationException([$validateKey => trans('notification::confirm.incorrect_code')]);
        }

        return $this->validateTotp($cryptogram['secret'], $code, $validateKey, $window, $step, $digits, $algo);
    }

    /**
     * Get an url (QR code) to register a TOTP session
     *
     * @param string $account
     * @param string $secretBase32
     * @param string $issuer
     * @param int $digits
     * @param string $algo
     * @return string
     */
    public function urlTotp(string $account, string $secretBase32, ?string $issuer = null, int $digits = 6, string $algo = 'SHA1'): string
    {
        if (! $issuer) {
            $issuer = config('app.name');
        }

        return sprintf('otpauth://totp/%s:%s?secret=%s&algorithm=%s&digits=%d&period=30', $issuer, $account, $secretBase32, $algo, $digits);
    }

    /**
     * Ger a cryptogram to store the TOTP sesssion
     *
     * @param string $secretBase32
     * @return string
     */
    public function cryptogramTotp(string $secretBase32): string
    {
        return encrypt(json_encode(['type' => 'confirm.totp', 'secret' => $secretBase32]), false);
    }

    /**
     * Get a current code fot the TOTP session
     *
     * @param string $secretBase32
     * @param int $time
     * @param int $step
     * @param int $digits
     * @param string $algo
     * @return string
     */
    public function codeTotp(string $secretBase32, ?int $time = null, int $step = 30, int $digits = 6, string $algo = 'sha1'): string
    {
        $time = $time ?? now()->timestamp;
        $counter = (int) floor($time / $step);
        $secretBin = $this->base32DecodeNopad($secretBase32);

        return $this->hotpBin($secretBin, $counter, $digits, $algo);
    }

    /**
     * @param string $secretBin
     * @param int $counter
     * @param int $digits
     * @param string $algo
     * @return string
     */
    private function hotpBin(string $secretBin, int $counter, int $digits, string $algo): string
    {
        if (PHP_INT_SIZE >= 8) {
            $hi = ($counter >> 32) & 0xFFFFFFFF;
            $lo = $counter & 0xFFFFFFFF;
        } else {
            $hi = 0;
            $lo = $counter & 0xFFFFFFFF;
        }

        $binCounter = pack('N2', $hi, $lo);
        $hmac = hash_hmac($algo, $binCounter, $secretBin, true);
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $part = substr($hmac, $offset, 4);
        $val = unpack('N', $part)[1] & 0x7FFFFFFF;
        $mod = (int) (10 ** $digits);

        return str_pad((string)($val % $mod), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $data
     * @return string
     */
    private function base32EncodeNopad(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $out .= $alphabet[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $out .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $out;
    }

    /**
     * @param string $b32
     * @return string
     */
    private function base32DecodeNopad(string $b32): string
    {
        $b32 = strtoupper($b32);
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $buffer = 0;
        $bitsLeft = 0;
        $out = '';

        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $ch = $b32[$i];
            if (!isset($alphabet[$ch])) {
                continue;
            }
            $buffer = ($buffer << 5) | $alphabet[$ch];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $out;
    }
}
