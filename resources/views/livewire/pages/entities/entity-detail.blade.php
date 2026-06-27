<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mortel\Access\AccessActor;
use Mortel\Access\ActorContext;
use Mortel\Access\ActorContextResolver;
use Mortel\Access\ContextAccessResolver;
use Mortel\Access\DeniedActor;
use Mortel\Actions\Policies\ClassifyData;
use Mortel\Actions\Policies\EnforceTrustLevel;
use Mortel\Contracts\TenantResolver;
use Mortel\Enums\TrustLevel;
use Mortel\Models\Entity;
use Mortelos\EntityGraph\EntityGraphServiceProvider;

new
#[Layout('layouts::app')]
#[Title('Entity')]
class extends Component
{
    public string $entityId = '';

    public ?Entity $entityModel = null;

    public function mount(string $entity): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (strlen($entity) !== 26 || ! ctype_alnum($entity)) {
            abort(404);
        }

        $this->entityId = $entity;
        $this->entityModel = Entity::find($entity);

        if (! $this->entityModel) {
            abort(404);
        }

        // Access gate: a non-viewable entity is indistinguishable from a missing one.
        if (! app(ContextAccessResolver::class)->canView($this->currentActor(), $this->entityModel)->allowed) {
            abort(404);
        }
    }

    /**
     * Linked entities the current actor may view, for drill-through navigation.
     *
     * @return Collection<int, Entity>
     */
    #[Computed]
    public function linkedEntities(): Collection
    {
        if (! $this->entityModel) {
            return collect();
        }

        // Re-load links + their related entities fresh; relations may not be
        // hydrated on the stored $entityModel after a Livewire roundtrip.
        $entity = Entity::with(['sourceLinks.targetEntity', 'targetLinks.sourceEntity'])->find($this->entityId);

        if (! $entity) {
            return collect();
        }

        $linked = collect();

        foreach ($entity->sourceLinks as $link) {
            if ($link->targetEntity !== null) {
                $linked->push($link->targetEntity);
            }
        }

        foreach ($entity->targetLinks as $link) {
            if ($link->sourceEntity !== null) {
                $linked->push($link->sourceEntity);
            }
        }

        $decide = app(ContextAccessResolver::class)->forCollection($this->currentActor(), $linked);

        return $linked
            ->filter(fn (Entity $candidate): bool => $decide($candidate)->allowed)
            ->unique('id')
            ->values();
    }

    /**
     * Trust-filtered attributes safe to surface to the current actor.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function visibleAttributes(): array
    {
        if (! $this->entityModel) {
            return [];
        }

        return app(ClassifyData::class)->filterEntityFields($this->entityModel, $this->entityReadTrustLevel());
    }

    /**
     * The graph panel is a progressive enhancement: only render it when the
     * mortelos/entity-graph package is installed in the host portal.
     */
    #[Computed]
    public function hasGraphPanel(): bool
    {
        return class_exists(EntityGraphServiceProvider::class);
    }

    private function currentActor(): AccessActor
    {
        $user = auth()->user();

        return $user !== null
            ? (app(ActorContextResolver::class)->resolve($user, app(TenantResolver::class)->id()) ?? DeniedActor::instance())
            : DeniedActor::instance();
    }

    private function entityReadTrustLevel(): TrustLevel
    {
        $actor = $this->currentActor();

        if (! $actor instanceof ActorContext) {
            return TrustLevel::Observe;
        }

        $enforcer = app(EnforceTrustLevel::class);

        return $enforcer->getTrustLevelForDomain($actor->roleId(), 'entity:read')
            ?? $enforcer->getTrustLevelForDomain($actor->roleId(), 'entity')
            ?? TrustLevel::Observe;
    }
}; ?>

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-700">&larr; Terug</a>
        <h1 class="mt-2 text-2xl font-semibold text-zinc-900">{{ $entityModel?->name }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-600">{{ $entityModel?->type }}</span>
            @if ($entityModel?->status)
                <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-600">{{ is_object($entityModel->status) ? $entityModel->status->value : $entityModel->status }}</span>
            @endif
            <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-600">{{ $entityModel?->classification ?? 'public' }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900">Kenmerken</h2>
            @forelse ($this->visibleAttributes as $key => $value)
                <div class="flex justify-between gap-4 border-b border-zinc-100 py-2 text-sm last:border-0">
                    <span class="text-zinc-500">{{ $key }}</span>
                    <span class="max-w-[60%] truncate text-right font-medium text-zinc-900">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                </div>
            @empty
                <p class="text-sm text-zinc-500">Geen zichtbare kenmerken.</p>
            @endforelse
        </section>

        <aside class="rounded-xl border border-zinc-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900">Gekoppelde entities</h2>
            <div class="space-y-2">
                @forelse ($this->linkedEntities as $linked)
                    <a
                        href="{{ route('entities.show', $linked->id) }}"
                        wire:navigate
                        class="block rounded-md border border-zinc-200 px-3 py-2 text-sm text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-50"
                    >
                        <span class="block truncate font-medium">{{ $linked->name }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ $linked->type }}</span>
                    </a>
                @empty
                    <p class="text-sm text-zinc-500">Geen gekoppelde entities.</p>
                @endforelse
            </div>
        </aside>
    </div>

    @if ($this->hasGraphPanel)
        <section class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white">
            @livewire('entity-graph::panel', [
                'entityId' => $entityId,
                'scope' => 'focus',
                'depth' => 2,
                'viewName' => 'Entity graph',
            ], key('entity-graph-'.$entityId))
        </section>
    @endif
</div>
