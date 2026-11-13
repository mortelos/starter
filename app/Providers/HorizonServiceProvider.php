<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\HorizonAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // A queue that waits longer than `waits` in config/horizon.php is always logged; with HORIZON_NOTIFICATION_MAIL
        // set, Horizon mails it as well.
        Event::listen(LongWaitDetected::class, static function (LongWaitDetected $event): void {
            Log::warning('Horizon: long wait on the queue', [
                'connection' => $event->connection,
                'queue' => $event->queue,
                'seconds' => $event->seconds,
            ]);
        });

        $notificationMail = config('horizon.notification_mail');

        if (is_string($notificationMail) && $notificationMail !== '') {
            Horizon::routeMailNotificationsTo($notificationMail);
        }

        // HorizonApplicationServiceProvider lets everyone in on the local environment. Every worktree on a dev
        // server runs with APP_ENV=local, so check the gate there too.
        Horizon::auth(static fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn ($user = null): bool => HorizonAccess::allows($user));
    }
}
