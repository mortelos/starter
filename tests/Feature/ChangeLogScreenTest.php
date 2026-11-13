<?php

declare(strict_types=1);

use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Contracts\ChangeLog\ChangeLogAgents;
use App\Contracts\ChangeLog\ChangeLogArchive;
use App\Enums\ChangeLogActorKind;
use App\Models\User;
use App\Support\ChangeLog\ChangeLogArchiveEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mortel\Enums\TrustLevel;
use Tests\Support\ChangeLog\LinkAgents;
use Tests\Support\ChangeLog\MapAccess;
use Tests\Support\ChangeLog\MemoryArchive;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $descriptions = changeLogDescriptions();
    $descriptions->part('planning', 'Planning');
    $descriptions->part('stock', 'Voorraad');
    $descriptions->register('planning.phase.shifted', 'planning', fn ($entry): string => 'Fase '.$entry->text('name').' verschoven');
    $descriptions->register('stock.item.counted', 'stock', fn ($entry): string => 'Voorraad '.$entry->text('name').' geteld');
});

/**
 * @param  array<string, string>  $query
 * @return list<string> the lines of the CSV, the header first
 */
function changeLogCsv(User $user, array $query = []): array
{
    $response = actingAs($user)->get(route('changelog.export', $query));
    $response->assertOk();
    $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));

    return $lines;
}

it('refuses the page and the export without changelog.view', function (): void {
    $user = User::factory()->create();
    attachGovernanceMembership($user, createRole('member'));

    actingAs($user)->get(route('changelog'))->assertForbidden();
    actingAs($user)->get(route('changelog.export'))->assertForbidden();
});

it('refuses a guest', function (): void {
    get(route('changelog'))->assertRedirect(route('login'));
});

it('links the change log from the profile menu only for those who may open it', function (): void {
    $member = User::factory()->create();
    attachGovernanceMembership($member, createRole('member'));

    actingAs(changeLogViewer())->get(route('settings'))->assertSee('Wijzigingslog');
    actingAs($member)->get(route('settings'))->assertDontSee('Wijzigingslog');
});

it('shows who did what in which part and when, newest first under day headings', function (): void {
    $viewer = changeLogViewer();
    $anna = User::factory()->create(['name' => 'Anna Jansen']);
    storeChangeEvent('planning.phase.shifted', ['name' => 'Oud'], (string) $anna->id, CarbonImmutable::now()->subDay()->setTime(9, 15));
    storeChangeEvent('stock.item.counted', ['name' => 'Buizen'], 'system:scheduler', CarbonImmutable::now()->setTime(8, 5));
    storeChangeEvent('planning.phase.shifted', ['name' => 'Nieuw'], 'agent:run-1', CarbonImmutable::now()->setTime(10, 30));

    actingAs($viewer)->get(route('changelog'))
        ->assertOk()
        ->assertSeeInOrder(['Vandaag', 'Fase Nieuw verschoven', 'Agent', 'Planning', '10:30', 'Voorraad Buizen geteld', 'Systeem', '08:05', 'Gisteren', 'Fase Oud verschoven', 'Anna Jansen', '09:15']);
});

it('names an agent and the user it acted for through the agents outlet', function (): void {
    app()->bind(ChangeLogAgents::class, LinkAgents::class);
    $viewer = changeLogViewer();
    $bram = User::factory()->create(['name' => 'Bram Vos']);
    storeChangeEvent('planning.phase.shifted', ['name' => 'A'], 'agent:link-'.$bram->id);

    actingAs($viewer)->get(route('changelog'))->assertSee('de koppeling namens Bram Vos');
});

it('filters on a person, all people, agents, one agent and the system', function (): void {
    app()->bind(ChangeLogAgents::class, LinkAgents::class);
    $viewer = changeLogViewer();
    $anna = User::factory()->create(['name' => 'Anna Jansen']);
    attachGovernanceMembership($anna, null);
    storeChangeEvent('planning.phase.shifted', ['name' => 'Mens'], (string) $anna->id);
    storeChangeEvent('planning.phase.shifted', ['name' => 'Koppeling'], 'agent:link-'.$anna->id);
    storeChangeEvent('planning.phase.shifted', ['name' => 'Andere agent'], 'agent:run-9');
    storeChangeEvent('planning.phase.shifted', ['name' => 'Systeem'], 'system:scheduler');

    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index')
        ->assertSee(['Fase Mens', 'Fase Koppeling', 'Fase Andere agent', 'Fase Systeem'])
        ->assertSee('de koppeling'); // offered in the Wie filter

    $page->set('who', 'user:'.$anna->id)->assertSee('Fase Mens')->assertDontSee(['Fase Koppeling', 'Fase Systeem']);
    $page->set('who', 'people')->assertSee('Fase Mens')->assertDontSee(['Fase Koppeling', 'Fase Andere agent', 'Fase Systeem']);
    $page->set('who', 'agents')->assertSee(['Fase Koppeling', 'Fase Andere agent'])->assertDontSee(['Fase Mens', 'Fase Systeem']);
    $page->set('who', 'agent:link')->assertSee('Fase Koppeling')->assertDontSee(['Fase Andere agent', 'Fase Mens']);
    $page->set('who', 'system')->assertSee('Fase Systeem')->assertDontSee(['Fase Mens', 'Fase Koppeling']);
    $page->call('resetFilters')->assertSee(['Fase Mens', 'Fase Systeem']);
});

it('filters on a part and on a period', function (): void {
    $viewer = changeLogViewer();
    storeChangeEvent('planning.phase.shifted', ['name' => 'Maart'], 'system:x', CarbonImmutable::parse('2026-03-10 12:00'));
    storeChangeEvent('stock.item.counted', ['name' => 'April'], 'system:x', CarbonImmutable::parse('2026-04-10 12:00'));
    storeChangeEvent('unknown.thing.happened', [], 'system:x', CarbonImmutable::parse('2026-04-11 12:00'), 'Losse wijziging');

    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index');

    $page->set('part', 'stock')->assertSee('Voorraad April geteld')->assertDontSee(['Fase Maart', 'Losse wijziging']);
    $page->set('part', 'other')->assertSee('Losse wijziging')->assertDontSee(['Voorraad April', 'Fase Maart']);
    $page->set('part', '')->set('from', '2026-04-01')->assertSee(['Voorraad April geteld', 'Losse wijziging'])->assertDontSee('Fase Maart');
    $page->set('from', '')->set('to', '2026-03-31')->assertSee('Fase Maart verschoven')->assertDontSee('Voorraad April');
    $page->set('from', '2026-04-10')->set('to', '2026-04-10')->assertSee('Voorraad April geteld')->assertDontSee(['Fase Maart', 'Losse wijziging']);
});

it('pages with a cursor and keeps the list still while new changes come in', function (): void {
    $viewer = changeLogViewer();
    $start = CarbonImmutable::parse('2026-05-01 08:00');

    foreach (range(1, 55) as $i) {
        storeChangeEvent('planning.phase.shifted', ['name' => sprintf('nr%02d', $i)], 'system:x', $start->addMinutes($i));
    }

    // The part filter leaves out the role and policy events of changeLogViewer(), which are newer.
    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index', ['part' => 'planning'])
        ->assertSee(['Fase nr55 verschoven', 'Fase nr06 verschoven'])->assertDontSee('Fase nr05 verschoven');

    storeChangeEvent('planning.phase.shifted', ['name' => 'Nieuw'], 'system:x', $start->addHours(5));
    $page->call('$refresh')->assertDontSee('Fase Nieuw');

    preg_match("/wire:click=\"show\\('([^']+)'\\)\"[^>]*>\\s*(?:<svg.*?<\\/svg>\\s*)?Oudere/s", $page->html(), $older);
    $page->call('show', $older[1] ?? '')->assertSee(['Fase nr05 verschoven', 'Fase nr01 verschoven'])->assertDontSee('Fase nr06 verschoven');
    $page->call('newest')->assertSee('Fase Nieuw');
});

it('exports exactly what the filter shows as CSV, guarding cells against formulas', function (): void {
    $viewer = changeLogViewer();
    storeChangeEvent('planning.phase.shifted', ['name' => '=SOM(A1)'], 'system:x', CarbonImmutable::parse('2026-04-02 12:00'));
    storeChangeEvent('stock.item.counted', ['name' => 'Buizen'], 'system:x', CarbonImmutable::parse('2026-04-03 12:00'));

    // Up to 30 April: leaves out the role and policy events of changeLogViewer().
    $all = changeLogCsv($viewer, ['to' => '2026-04-30']);
    expect($all[0])->toBe('Datum;Tijd;Wie;Soort;Wat;Toelichting;Onderdeel;"Ongedaan gemaakt";"Vóór de overstap"')
        ->and($all)->toHaveCount(3)
        ->and($all[1])->toContain('2026-04-03;12:00;Systeem;Systeem;"Voorraad Buizen geteld";;Voorraad;nee;nee');

    $planning = changeLogCsv($viewer, ['part' => 'planning']);
    expect($planning)->toHaveCount(2)->and($planning[1])->toContain('Fase =SOM(A1) verschoven');

    $formula = changeLogCsv($viewer, ['part' => 'planning', 'from' => '2026-04-02', 'to' => '2026-04-02']);
    expect($formula)->toHaveCount(2);

    expect(changeLogCsv($viewer, ['from' => '2026-04-04', 'to' => '2026-04-30']))->toHaveCount(1);
});

it('guards a cell that starts with a formula character', function (): void {
    changeLogDescriptions()->register('evil.thing.done', 'planning', fn (): string => '=HYPERLINK("x")');
    storeChangeEvent('evil.thing.done', [], 'system:x');

    expect(changeLogCsv(changeLogViewer(), ['part' => 'planning'])[1])->toContain("\"'=HYPERLINK(\"\"x\"\")\"");
});

it('hides the rows, filter option and export lines of a part the access outlet hides', function (): void {
    app()->instance(ChangeLogAccess::class, new MapAccess(['planning' => TrustLevel::Observe]));
    $viewer = changeLogViewer();
    storeChangeEvent('planning.phase.shifted', ['name' => 'Zichtbaar'], 'system:x');
    storeChangeEvent('stock.item.counted', ['name' => 'Geheim'], 'system:x');
    storeChangeEvent('unknown.thing.happened', [], 'system:x', reason: 'Overige wijziging');

    actingAs($viewer);
    Livewire::test('pages::changelog.index')
        ->assertSee('Fase Zichtbaar verschoven')
        ->assertDontSee(['Voorraad Geheim', 'Overige wijziging', 'Voorraad'])
        ->set('part', 'stock')
        ->assertDontSee('Voorraad Geheim');

    $csv = implode("\n", changeLogCsv($viewer));
    expect($csv)->toContain('Fase Zichtbaar');
    expect(str_contains($csv, 'Geheim') || str_contains($csv, 'Overige wijziging'))->toBeFalse();
});

it('shows the archive once the events run out, read only and filtered', function (): void {
    app()->instance(ChangeLogArchive::class, new MemoryArchive([
        new ChangeLogArchiveEntry('7', CarbonImmutable::parse('2025-12-01 10:00'), 'Oude Gebruiker', ChangeLogActorKind::Person, 'Werkpakket aangemaakt', 'planning'),
        new ChangeLogArchiveEntry('8', CarbonImmutable::parse('2025-11-01 10:00'), 'Oude Gebruiker', ChangeLogActorKind::Person, 'Voorraad aangepast', 'stock'),
    ]));
    $viewer = changeLogViewer('things.manage');
    storeChangeEvent('planning.phase.shifted', ['name' => 'Nu'], 'system:x');

    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index');
    $page->assertSeeInOrder(['Fase Nu verschoven', 'Vóór de overstap', 'Werkpakket aangemaakt', 'Oude Gebruiker', 'Voorraad aangepast']);
    $page->assertDontSee('Ongedaan maken')
        ->set('part', 'stock')
        ->assertSee('Voorraad aangepast')
        ->assertDontSee('Werkpakket aangemaakt');

    $csv = changeLogCsv($viewer);
    expect(end($csv))->toContain('"Oude Gebruiker";Persoon;"Voorraad aangepast";;Voorraad;nee;ja');

    app()->instance(ChangeLogAccess::class, new MapAccess(['planning' => TrustLevel::Observe]));
    app()->forgetScopedInstances(); // a new request: the reader is bound per request
    expect(str_contains(implode("\n", changeLogCsv($viewer)), 'Voorraad aangepast'))->toBeFalse();
});

it('starts at the newest when the cursor in the address is broken', function (): void {
    $viewer = changeLogViewer();
    storeChangeEvent('planning.phase.shifted', ['name' => 'Heel'], 'system:x');

    actingAs($viewer);
    Livewire::test('pages::changelog.index', ['cursor' => base64_encode('{"other":1,"_pointsToNextItems":true}')])
        ->assertSee('Fase Heel verschoven');
});

it('closes an open page on the next request once the right is gone', function (): void {
    $viewer = changeLogViewer();
    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index')->assertOk();

    DB::table('tenant_user')->where('user_id', $viewer->id)->update(['role_id' => null]);
    app()->forgetScopedInstances();

    $page->call('$refresh')->assertForbidden();
});
