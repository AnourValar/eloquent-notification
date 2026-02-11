<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmTotpInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $suffix
     */
    public function handle(Request $request, Closure $next, string $suffix = 'totp'): Response
    {
        \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validateTotpCryptogram(
            $request->input('cryptogram_' . $suffix),
            $request->input('code_' . $suffix),
            'code_' . $suffix
        );

        return $next($request);
    }
}
