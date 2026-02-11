<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPhoneMy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $phoneKey
     * @param  string $suffix
     */
    public function handle(Request $request, Closure $next, string $phoneKey = 'phone', string $suffix = 'phone'): Response
    {
        \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validatePhone(
            $request->input('cryptogram_' . $suffix),
            $request->input('code_' . $suffix),
            $request->user()->$phoneKey,
            'code_' . $suffix
        );

        return $next($request);
    }
}
