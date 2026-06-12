<?php

namespace AnourValar\EloquentNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmFaMyDynamic extends ConfirmFaMy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string $cryptogramsKey
     * @param int $qty
     * @param  array $faBlack
     */
    public function handle(Request $request, Closure $next, string $cryptogramsKey, int $qty, ...$faBlack): Response
    {
        $this->calculateQty($request, $qty);

        if (! $qty) {
            return $next($request);
        }

        return parent::handle($request, $next, $cryptogramsKey, $qty, ...$faBlack);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $qty
     * @return void
     */
    protected function calculateQty(Request $request, int &$qty): void
    {
        if ($request->user()->totp_secret) {
            $qty++;
        }
    }
}
