<?php

declare(strict_types=1);

use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use App\Models\User;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

/** Invites an address as the seeded admin; returns the invitation id. */
function inviteForChangeLog(string $email): string
{
    $admin = User::query()->where('email', 'admin@example.test')->sole();
    $role = Role::query()->where('name', 'owner')->sole();
    $result = app(InviteUser::class)->handle($email, Scalar::scalarString($role->getKey()), Actor::user($admin));

    return Scalar::string($result->data['invitation_id'] ?? null);
}

it('undoes a change from the log and shows the message', function (): void {
    inviteForChangeLog('terug@example.test');

    $page = verifyAsAdmin('/changelog');
    expectNoPostsSinceLoad($page);
    $page->assertSee('Uitnodiging klaargezet voor terug@example.test');

    $undone = LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->click('[data-changelog-row]:has-text("terug@example.test") >> text=Ongedaan maken'), 1);
    fwrite(STDERR, 'Screenshot na ongedaan maken: '.$undone['screenshot'].PHP_EOL);

    $page->assertSeeIn('[data-changelog-message]', 'Ongedaan gemaakt: Uitnodiging klaargezet voor terug@example.test.')
        ->assertSeeIn('[data-changelog-row]:has-text("terug@example.test")', 'Ongedaan gemaakt');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('[data-changelog-message] >> text=Sluiten'), 0);
    $page->assertScript("getComputedStyle(document.querySelector('[data-changelog-message]')).display === 'none'");
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});

it('says what changed when the invitation was revoked in the meantime, and changes nothing', function (): void {
    $invitation = inviteForChangeLog('al-weg@example.test');
    app(RevokeInvitation::class)->handle($invitation, Actor::user(User::query()->where('email', 'admin@example.test')->sole()));

    $page = verifyAsAdmin('/changelog');
    expectNoPostsSinceLoad($page);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->click('[data-changelog-row]:has-text("al-weg@example.test") >> text=Ongedaan maken'), 1);

    $page->assertSeeIn('[data-changelog-message]', 'Ongedaan maken lukte niet: Deze uitnodiging is ingetrokken. Er is niets teruggedraaid.')
        ->assertSeeIn('[data-changelog-row]:has-text("al-weg@example.test")', 'Ongedaan maken');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});

it('filters on a part with one round trip, also on a phone', function (): void {
    inviteForChangeLog('filter@example.test');

    $page = verifyAsAdmin('/dashboard');
    $page->resize(390, 844)->navigate('/changelog');
    expectNoPostsSinceLoad($page);
    $page->assertSee('Recht ‘Allow changelog.view’ aangemaakt');

    $filtered = LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->select('part', 'users'), 1);
    fwrite(STDERR, 'Screenshot gefilterd op 390: '.$filtered['screenshot'].PHP_EOL);

    $page->assertSee('Uitnodiging klaargezet voor filter@example.test')
        ->assertDontSee('Recht ‘Allow changelog.view’ aangemaakt')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});
