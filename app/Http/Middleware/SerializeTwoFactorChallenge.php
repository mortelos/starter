<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * One code check at a time per waiting user. Fortify reads the recovery codes, accepts a code and only then saves the
 * replacement, so two requests at the same moment can both read the old list and both accept the same code. With a
 * lock per waiting user (`login.id`) around the whole check, the second request starts after the first one replaced
 * the code and is refused. The lock needs a cache store that locks (database, redis, array: not `file` on several
 * servers), the starter's own `database` store does.
 *
 * A request that cannot get the lock within the wait gets a 429 and is not checked at all.
 */
final class SerializeTwoFactorChallenge
{
    /** Seconds the lock may be held (a crashed request frees it after this). */
    public const int HOLD = 15;

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $waiting = $request->session()->get('login.id');

        if (! is_scalar($waiting) || (string) $waiting === '') {
            return $next($request);
        }

        $lock = Cache::lock(self::key((string) $waiting), self::HOLD);

        try {
            $lock->block(config()->integer('starter.auth.two_factor_lock_wait', 5));
        } catch (LockTimeoutException) {
            abort(429);
        }

        try {
            return $next($request);
        } finally {
            $lock->release();
        }
    }

    public static function key(string $userKey): string
    {
        return 'two-factor-challenge:'.$userKey;
    }
}
