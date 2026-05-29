# 03 — TALL stack conventions

Tailwind, Alpine, **Livewire 4 (single-file components)**, Laravel. Plus Flux UI
Pro as the design system and Pest as the test framework.

## Hard rules

1. **Flux UI first.** Before writing any custom Alpine/Tailwind block, check
   Flux. The license is in place; assume Flux Pro components are available.
2. **Livewire 4 SFC**, not class-based v3. Single file = co-located state, view,
   lifecycle.
3. **Pest**, not raw PHPUnit. Architecture and feature suites both Pest.
4. **Action classes** in `app/Actions/<Domain>/` for write paths.
5. **Pint** before commit (`vendor/bin/pint --dirty`).
6. **No em-dashes** in Dutch user-facing copy.

## Flux UI cheat sheet (use these before going custom)

| Need | Flux component |
| --- | --- |
| Slide-over / drawer panel | `<flux:modal flyout position="right">` |
| Centered dialog | `<flux:modal>` |
| Tabs | `<flux:tabs>` |
| Select | `<flux:select>` |
| Badge | `<flux:badge>` |
| Button | `<flux:button>` |
| Input / textarea | `<flux:input>`, `<flux:textarea>` |
| Checkbox / radio | `<flux:checkbox>`, `<flux:radio>` |
| Field / fieldset | `<flux:field>`, `<flux:fieldset>` |
| Card | `<flux:card>` |
| Table | `<flux:table>` |
| Dropdown | `<flux:dropdown>` |
| Kanban | `<flux:kanban>` |

**Violation example**: building a custom slide-in panel with Alpine + fixed
positioning + transitions when `flux:modal flyout position="right"` already
exists. Don't do that.

## Livewire 4 SFC shape

```php
{{-- resources/views/livewire/pages/admin/orders/index.blade.php --}}
@php
use App\Models\Order;
use function Livewire\Volt\{state, computed};

state(['search' => '']);

$orders = computed(fn () => Order::query()
    ->when($this->search, fn ($q) => $q->where('reference', 'like', "%{$this->search}%"))
    ->latest()
    ->paginate(20));
@endphp

<x-mortelos-starter::layouts.app>
    <flux:heading size="xl">Orders</flux:heading>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search reference" />

    <flux:table>
        {{-- ... --}}
    </flux:table>
</x-mortelos-starter::layouts.app>
```

Class-based components are still allowed when state genuinely doesn't fit (a
big component with many computed properties), but the default is SFC.

## Action classes

Domain rules live in actions, not components.

```php
namespace App\Actions\Documents;

final readonly class ApproveDocument
{
    public function __construct(private DocumentRepository $documents) {}

    public function execute(string $documentId, User $actor): void
    {
        Gate::authorize('approve', $this->documents->find($documentId));
        // 1. emit DocumentApproved event
        // 2. let projections catch up
    }
}
```

Components call actions; actions emit events; projections catch up; surfaces
read from projections. That's the standard flow.

## Pest patterns

```php
// tests/Feature/Documents/ApproveDocumentTest.php
use App\Actions\Documents\ApproveDocument;

it('emits DocumentApproved and updates the projection', function () {
    $user = User::factory()->admin()->create();
    $doc  = Document::factory()->pending()->create();

    actingAs($user);

    app(ApproveDocument::class)->execute($doc->id, $user);

    expect(events())->toHaveDispatched(DocumentApproved::class);
    expect(DocumentReviewStatus::find($doc->id)->status)->toBe('approved');
});

it('denies non-admin', function () {
    $user = User::factory()->create();
    $doc  = Document::factory()->pending()->create();

    actingAs($user);

    expect(fn () => app(ApproveDocument::class)->execute($doc->id, $user))
        ->toThrow(AuthorizationException::class);
});
```

Architecture tests run in the same suite (`tests/Feature/Architecture/`,
`tests/Unit/Architecture/`).

## Naming

- Models: `App\Models\<Singular>` — `Order`, `Document`, `Invoice`
- Actions: `App\Actions\<Domain>\<Verb><Noun>` — `Documents\ApproveDocument`
- Events: `App\Events\<NounVerb>` — `DocumentApproved`, `InvoiceSynced`
- Projections: `App\Projections\<ReadModel>` — `DocumentReviewStatus`
- Policies: `App\Policies\<Singular>Policy` — `DocumentPolicy`
- Livewire pages: `resources/views/livewire/pages/<area>/<name>.blade.php`
- Livewire shared components: `resources/views/livewire/shared/<name>.blade.php`

## Translations

User-facing strings go through `__('…')` and translation files. Don't hardcode
Dutch in Blade if the host supports multiple languages.

## What not to do

- Custom Tailwind block when Flux has the component
- Class-based Livewire v3 for new code
- Domain logic inside Blade `@if` or Livewire methods
- Direct DB queries from Blade
- Skipping Pint
- Em-dashes in Dutch prose
