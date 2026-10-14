<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mortel\AI\Agents\QuestionAgent;
use Mortel\Enums\SourceType;
use Mortel\Models\InboxItem;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function inboxUser(): User
{
    $user = User::factory()->create();
    actingAs($user);

    return $user;
}

/**
 * Inbox items come from framework producers (agents, workflows, skills); a test sets one up directly.
 *
 * @param  array<model-property<InboxItem>, mixed>  $attributes
 */
function inboxItem(User $user, array $attributes = []): InboxItem
{
    // @spine-ignore: test fixture, the starter itself never creates inbox items
    return InboxItem::factory()->forUser($user)->create(array_merge(['type' => 'voorstel', 'risk_level' => 'low'], $attributes));
}

it('resolves each inbox component to the starter components folder', function (string $name, string $file): void {
    expect(app('livewire.finder')->resolveSingleFileComponentPath($name))
        ->toBe(resource_path('views/components/inbox/'.$file));
})->with([
    ['inbox.inbox-filter', '⚡inbox-filter.blade.php'],
    ['inbox.inbox-list', '⚡inbox-list.blade.php'],
    ['inbox.inbox-detail', '⚡inbox-detail.blade.php'],
    ['inbox.intake-detail', '⚡intake-detail.blade.php'],
]);

it('shows an empty inbox in a fresh starter', function (): void {
    inboxUser();

    get(route('inbox'))
        ->assertOk()
        ->assertSee('Je inbox is leeg')
        ->assertSee('Kies een item');
});

it('lists only the pending items of the signed-in user', function (): void {
    $user = inboxUser();
    inboxItem($user, ['title' => 'Mijn voorstel']);
    inboxItem($user, ['title' => 'Al goedgekeurd', 'status' => 'approved', 'approved_at' => now()]);
    inboxItem(User::factory()->create(), ['title' => 'Van een ander']);

    get(route('inbox'))
        ->assertOk()
        ->assertSee('Mijn voorstel')
        ->assertDontSee('Al goedgekeurd')
        ->assertDontSee('Van een ander');
});

it('filters the list on each of the five url parameters', function (array $query, string $shown, string $hidden): void {
    $user = inboxUser();
    inboxItem($user, ['title' => 'Voorstel laag agent', 'source_type' => SourceType::AgentRun->value]);
    inboxItem($user, ['title' => 'Notificatie hoog workflow', 'type' => 'notificatie', 'risk_level' => 'high', 'source_type' => SourceType::WorkflowRun->value]);
    inboxItem($user, ['title' => 'Afgewezen voorstel', 'status' => 'rejected', 'rejected_at' => now()]);
    inboxItem($user, ['title' => 'Goedgekeurd voorstel', 'status' => 'approved', 'approved_at' => now()]);

    get(route('inbox', $query))
        ->assertOk()
        ->assertSee($shown)
        ->assertDontSee($hidden);
})->with([
    'type' => [['filterType' => 'notificatie'], 'Notificatie hoog workflow', 'Voorstel laag agent'],
    'risk level' => [['filterRiskLevel' => 'low'], 'Voorstel laag agent', 'Notificatie hoog workflow'],
    'source' => [['filterSource' => SourceType::WorkflowRun->value], 'Notificatie hoog workflow', 'Voorstel laag agent'],
    'archive' => [['archive' => 1], 'Afgewezen voorstel', 'Voorstel laag agent'],
    'status' => [['archive' => 1, 'status' => 'approved'], 'Goedgekeurd voorstel', 'Afgewezen voorstel'],
]);

it('sends set-filter from the filter and the page keeps each choice in its url property', function (): void {
    inboxUser();

    $filter = Livewire::test('inbox.inbox-filter', ['filterRiskLevel' => 'high']);
    $filter->call('toggle', 'filterType', 'voorstel');
    $filter->assertDispatched('set-filter', filterType: 'voorstel', filterRiskLevel: 'high', filterSource: '', filterStatus: '');
    $filter->call('toggle', 'filterRiskLevel', 'high');
    $filter->assertDispatched('set-filter', filterType: '', filterRiskLevel: '', filterSource: '', filterStatus: '');
    $filter->call('setArchive', true);
    $filter->assertDispatched('set-filter', filterArchive: true);

    $page = Livewire::test('pages::inbox.index');
    $page->dispatch('set-filter', filterType: 'voorstel', filterRiskLevel: 'high', filterSource: 'agent_run', filterArchive: true, filterStatus: 'approved');
    $page->assertSet('filterType', 'voorstel');
    $page->assertSet('filterRiskLevel', 'high');
    $page->assertSet('filterSource', 'agent_run');
    $page->assertSet('filterArchive', true);
    $page->assertSet('filterStatus', 'approved');
});

it('opens the detail of a selected item', function (): void {
    $item = inboxItem(inboxUser(), ['title' => 'Factuur controleren', 'summary' => 'Het bedrag wijkt af.']);

    Livewire::test('inbox.inbox-list')->assertSee("itemId: '{$item->id}'", false);

    $page = Livewire::test('pages::inbox.index');
    $page->dispatch('item-selected', itemId: $item->id);
    $page->assertSet('selectedItemId', $item->id);
    $page->assertSee('Het bedrag wijkt af.');
    $page->assertSee('Goedkeuren');
});

it('approves through the framework action, closes the detail and drops the item from the list', function (): void {
    $item = inboxItem(inboxUser(), ['title' => 'Goed te keuren']);

    $detail = Livewire::test('inbox.inbox-detail', ['itemId' => $item->id]);
    $detail->call('approve');
    $detail->assertDispatched('item-approved', id: $item->id);

    expect($item->refresh()->status)->toBe('approved');

    $page = Livewire::test('pages::inbox.index');
    $page->dispatch('item-selected', itemId: $item->id);
    $page->dispatch('item-approved');
    $page->assertSet('selectedItemId', '');

    get(route('inbox'))->assertOk()->assertDontSee('Goed te keuren');
});

it('rejects with a reason through the framework action and drops the item from the list', function (): void {
    $item = inboxItem(inboxUser(), ['title' => 'Af te wijzen']);

    $detail = Livewire::test('inbox.inbox-detail', ['itemId' => $item->id]);
    $detail->set('showRejectForm', true);
    $detail->call('reject');
    $detail->assertHasErrors('rejectReason');
    $detail->set('rejectReason', 'Het bedrag klopt niet');
    $detail->call('reject');
    $detail->assertDispatched('item-rejected', id: $item->id);

    $item->refresh();
    expect($item->status)->toBe('rejected')
        ->and($item->metadata)->toHaveKey('rejection_reason', 'Het bedrag klopt niet');

    $page = Livewire::test('pages::inbox.index');
    $page->dispatch('item-selected', itemId: $item->id);
    $page->dispatch('item-rejected');
    $page->assertSet('selectedItemId', '');

    // The framework answers a rejection with a revised item; the rejected one leaves the list.
    get(route('inbox'))
        ->assertOk()
        ->assertDontSee("itemId: '{$item->id}'", false)
        ->assertSee('Af te wijzen (herzien)');
});

it('does not open or approve an item of another user', function (): void {
    inboxUser();
    $item = inboxItem(User::factory()->create(), ['title' => 'Niet van jou']);

    $detail = Livewire::test('inbox.inbox-detail', ['itemId' => $item->id]);
    $detail->assertDontSee('Niet van jou');
    $detail->call('approve');
    $detail->assertNotDispatched('item-approved');

    expect($item->refresh()->status)->toBe('pending');
});

it('opens the intake detail for an intake type and the inbox detail for any other type', function (string $type, string $opens, string $skips): void {
    app()->instance('inbox.test-type-resolver', new class
    {
        public function resolve(string $itemId): string
        {
            return InboxItem::query()->find($itemId)->type ?? '';
        }
    });
    config(['starter.inbox.item_type_resolver' => 'inbox.test-type-resolver']);
    $item = inboxItem(inboxUser(), ['type' => $type]);

    get(route('inbox', ['item' => $item->id]))
        ->assertOk()
        ->assertSee('wire:name="'.$opens.'"', false)
        ->assertDontSee('wire:name="'.$skips.'"', false);
})->with([
    'intake type' => ['compliance_intake', 'inbox.intake-detail', 'inbox.inbox-detail'],
    'other type' => ['voorstel', 'inbox.inbox-detail', 'inbox.intake-detail'],
]);

it('answers a question in the intake conversation through the framework action', function (): void {
    $item = inboxItem(inboxUser(), ['type' => 'audit', 'title' => 'Audit voorbereiden']);
    QuestionAgent::fake([['answer' => 'Begin met de risicoanalyse.', 'entity_ids_used' => [], 'confidence' => 90]]);

    $intake = Livewire::test('inbox.intake-detail', ['itemId' => $item->id]);
    $intake->assertSee('Audit voorbereiden');
    $intake->set('newMessage', 'Waar begin ik?');
    $intake->call('sendMessage');
    $intake->assertSet('newMessage', '');
    $intake->assertSee('Waar begin ik?');
    $intake->assertSee('Begin met de risicoanalyse.');
});
