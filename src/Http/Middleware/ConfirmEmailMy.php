<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmEmailMy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $emailKey
     * @param  string $suffix
     */
    public function handle(Request $request, Closure $next, string $emailKey = 'email', string $suffix = 'email'): Response
    {
        \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validateEmail(
            $request->input('cryptogram_' . $suffix),
            $request->input('code_' . $suffix),
            $request->user()->$emailKey,
            'code_' . $suffix
        );

        return $next($request);
    }
}
