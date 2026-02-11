<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmFaMy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $cryptogramsKey
     * @param  int $qty
     * @param  array $faBlack
     */
    public function handle(Request $request, Closure $next, string $cryptogramsKey, int $qty, ...$faBlack): Response
    {
        if (! $request->isPrecognitive()) { // it is safe -> precognitive middleware must be added to the specific routes
            $contacts = \App::make(\AnourValar\EloquentNotification\ConfirmService::class)->validateFa(
                $request->input($cryptogramsKey), // cryptograms_fa
                $qty,
                [],
                $faBlack
            );

            foreach ($contacts as $key => $value) {
                if ($request->user()->$key !== $value) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('User mismatch.');
                }
            }
        }

        return $next($request);
    }
}
