<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPhoneInput
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
        $value = \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validatePhone(
            $request->input('cryptogram_' . $suffix),
            $request->input('code_' . $suffix),
            $request->input($phoneKey),
            'code_' . $suffix
        );

        $request->merge([$phoneKey => $value]);
        return $next($request);
    }
}
