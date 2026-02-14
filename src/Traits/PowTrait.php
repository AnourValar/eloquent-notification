<?php

namespace AnourValar\EloquentNotification\Traits;

use AnourValar\EloquentValidation\Exceptions\ValidationException;
use AnourValar\EloquentNotification\Adapters\Exchanger\ExchangerInterface;

/**
 * PoW resolve on JS (example):
 *
 * let responseFromPowApi = {
 *   "salt": "foo",
 *   "puzzle": ["f03722d6f4e2c3b038dcbfe8b1bf9aad9789e8d0b98be56bb9f550bfb19f397b", "03ca8b933c074cd1c9bcf02ba5f0ca4fb2da9a6126ae97627d57e96016e5c920"]
 * };
 *
 * function sha256(message) {
 *  const shaObj = new jsSHA('SHA-256', 'TEXT')
 *  shaObj.update(message)
 *  return shaObj.getHash('HEX')
 * }
 *
 * let puzzle = [];
 * let step = 0;
 * for (let i = 0; i < 10000000; i++) {
 *   if (sha256(responseFromPowApi.salt + i) == responseFromPowApi.puzzle[step]) { // sha256 - from npm package
 *     puzzle.push(i);
 *     step++;
 *     i--;
 *     if (responseFromPowApi.puzzle.length == step) break;
 *   }
 *
 *   if (i % 100000 == 0) await new Promise((resolve) => setTimeout(resolve, 20));
 * }
 *
 * console.log(puzzle);
 */

trait PowTrait
{
    /**
     * Request a PoW
     *
     * @param int|null $cost
     * @return array
     */
    public function requestPow(?int $cost = null): array // @TODO: confirm.dynamic_request_pow - increase on many limits?
    {
        if (! isset($cost)) {
            $cost = config('eloquent_notification.confirm.pow_cost');
        }

        $salt = $this->getSalt();
        $puzzle = $this->getPuzzle($cost);
        $cryptogram = encrypt([
            'type' => 'confirm.pow',
            'puzzle' => $puzzle, // challenge
            'expired_at' => now()->addSeconds(config('eloquent_notification.confirm.pow_expire'))->timestamp,
        ]);

        if (! \App::isProduction()) {
            \App::make(ExchangerInterface::class)->sendMessage(
                'Cryptogram',
                json_encode(decrypt($cryptogram), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'POW'
            );
        }

        return ['salt' => $salt, 'cryptogram_pow' => $cryptogram, 'puzzle_pow' => $this->encodePuzzle($puzzle, $salt)];
    }

    /**
     * Verify the PoW
     *
     * @param mixed $puzzlePow
     * @param mixed $cryptogramPow
     * @return true
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function validatePow($puzzlePow, $cryptogramPow): true
    {
        try {
            if (is_string($cryptogramPow)) {
                $sha1 = sha1($cryptogramPow);
                $cryptogramPow = decrypt($cryptogramPow);
            } else {
                $cryptogramPow = null;
                $sha1 = '';
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if (($cryptogramPow['type'] ?? null) !== 'confirm.pow') {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect'));
        }

        if ($cryptogramPow['expired_at'] < now()->timestamp) {
            throw new ValidationException(['code' => trans('eloquent_notification::confirm.expired')]);
        }

        if ($cryptogramPow['puzzle'] !== $puzzlePow) {
            throw new ValidationException(trans('eloquent_notification::confirm.incorrect_code'));
        }

        if (! \Cache::add(implode(' / ', [__METHOD__, $sha1]), '1', (config('eloquent_notification.confirm.pow_expire') + 1))) {
            throw new ValidationException(['code' => trans('eloquent_notification::confirm.expired')]);
        }

        return true;
    }

    /**
     * @return string
     */
    private function getSalt(): string
    {
        return hash('sha256', random_bytes(100));
    }

    /**
     * @param int $cost
     * @return array
     */
    private function getPuzzle(int $cost): array
    {
        $puzzle = [
            random_int(0, (int) ($cost * 0.1)),
            random_int(0, (int) ($cost * 0.2)),
            random_int(0, $cost),
            random_int(0, $cost),
            random_int(0, $cost),
            random_int(0, $cost),
            random_int(0, $cost),
            random_int(0, $cost),
            random_int((int) ($cost * 0.8), $cost),
            random_int((int) ($cost * 0.9), $cost),
        ];
        sort($puzzle); // make it easy for the client

        return $puzzle;
    }

    /**
     * @param array $puzzle
     * @param string $salt
     * @return array
     */
    private function encodePuzzle(array $puzzle, string $salt): array
    {
        foreach ($puzzle as &$item) {
            $item = hash('sha256', $salt . $item);
        }
        unset($item);

        return $puzzle;
    }
}
