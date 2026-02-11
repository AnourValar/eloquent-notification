<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmTotpMy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $secretKey
     * @param  string $suffix
     */
    public function handle(Request $request, Closure $next, string $secretKey = 'totp_secret', string $suffix = 'totp'): Response
    {
        \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validateTotpCryptogram(
            $request->user()->$secretKey,
            $request->input('code_' . $suffix),
            'code_' . $suffix
        );

        return $next($request);
    }
}
