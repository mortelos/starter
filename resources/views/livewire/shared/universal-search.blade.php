{{-- SPIKE 23.1: entity results below are auto-filtered by the global PolicyScope when enabled. Revert after Story 23.5. --}}
<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    /** @var list<array{id: string, name: string, type: string, url: string, icon: string}> */
    public array $entities = [];

    /** @var list<array{id: string, title: string, summary: string, type: string, status: string, url: string, icon: string}> */
    public array $inboxItems = [];

    /** @var list<array{id: string, title: string, summary: string, url: string, icon: string}> */
    public array $chatItems = [];

    /** @var list<array{label: string, icon: string, route: string}> */
    public array $navItems = [];

    /** @var list<string> */
    public array $iconNames = [];

    public string $query = '';

    public function mount(): void
    {
        $this->loadResults('');
        $this->loadNavigation();
        $this->refreshIconNames();
    }

    public function search(string $query): void
    {
        $this->query = trim(Str::limit($query, 120, ''));

        $this->loadResults($this->query);
        $this->refreshIconNames();
    }

    public function saveOverview(string $query, string $name): void
    {
        if (! auth()->check() || trim($name) === '' || trim($query) === '') {
            return;
        }

        $this->universalSearchResolver()?->saveOverview(trim($query), trim($name), auth()->id());

        $this->dispatch('overzicht-saved');
    }

    private function loadResults(string $query): void
    {
        $resolver = $this->universalSearchResolver();

        if ($resolver === null) {
            $this->entities = [];
            $this->inboxItems = [];
            $this->chatItems = [];
            return;
        }

        $results = $resolver->results($query, auth()->user());
        $this->entities = $results['entities'] ?? [];
        $this->inboxItems = $results['inboxItems'] ?? [];
        $this->chatItems = $results['chatItems'] ?? [];
    }

    private function loadNavigation(): void
    {
        $resolver = $this->universalSearchResolver();

        if ($resolver === null) {
            $this->navItems = [];
            return;
        }

        $this->navItems = $resolver->navigation(auth()->user());
    }

    private function refreshIconNames(): void
    {
        $this->iconNames = array_values(array_unique([
            ...array_column($this->entities, 'icon'),
            ...array_column($this->inboxItems, 'icon'),
            ...array_column($this->chatItems, 'icon'),
            ...array_column($this->navItems, 'icon'),
        ]));
    }

    private function universalSearchResolver(): ?object
    {
        $resolver = config('starter.navigation.universal_search_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return null;
        }

        return app($resolver);
    }
}; ?>

<div
    x-data="{
        open: false,
        query: '',
        activeIndex: -1,
        showSaveForm: false,
        saveName: '',
        saveConfirmed: false,
        entities: $wire.entangle('entities'),
        inboxItems: $wire.entangle('inboxItems'),
        chatItems: $wire.entangle('chatItems'),
        navItems: $wire.entangle('navItems'),
        get results() {
            const q = this.query.toLowerCase();
            return {
                entities: this.query.length < 1 ? this.entities.slice(0, 5) : this.entities,
                inboxItems: this.inboxItems,
                chatItems: this.chatItems,
                navItems: this.query.length < 1 ? this.navItems : this.navItems.filter(n => n.label.toLowerCase().includes(q)),
            };
        },
        get flatResults() {
            return [
                ...this.results.entities.map(e => ({ ...e, _type: 'entity' })),
                ...this.results.inboxItems.map(i => ({ ...i, _type: 'inbox' })),
                ...this.results.chatItems.map(c => ({ ...c, _type: 'chat' })),
                ...this.results.navItems.map(n => ({ ...n, _type: 'nav' })),
            ];
        },
        get hasResults() {
            return this.flatResults.length > 0;
        },
        doOpen() {
            this.open = true;
            this.activeIndex = -1;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        doClose() {
            this.open = false;
            this.query = '';
            this.activeIndex = -1;
            this.showSaveForm = false;
            this.saveName = '';
            this.saveConfirmed = false;
            this.$wire.search('');
        },
        moveDown() {
            if (this.activeIndex < this.flatResults.length - 1) {
                this.activeIndex++;
            }
        },
        moveUp() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            }
        },
        select() {
            const item = this.flatResults[this.activeIndex];
            if (item) {
                this.navigate(item);
                return;
            }

            if (!this.hasResults && this.query.trim().length > 0) {
                this.askFreeSearch();
            }
        },
        askFreeSearch() {
            this.$dispatch('open-conversation-panel', { question: this.query, hint: 'auto' });
            this.doClose();
        },
        navigate(item) {
            const url = item._type === 'nav' ? item.route : item.url;
            this.doClose();
            Livewire.navigate(url);
        },
        iconHtml(name) {
            return this.$refs.icons?.querySelector(`[data-icon='${name}']`)?.innerHTML || '';
        },
    }"
    @keydown.escape.window="doClose()"
    @keydown.meta.k.window.prevent="doOpen()"
    @open-universal-search.window="doOpen()"
    @keydown.ctrl.k.window.prevent="doOpen()"
    @click.outside="doClose()"
    class="relative w-full max-w-sm"
>
    {{-- Hidden icon bank rendered by Blade --}}
    <div class="hidden" x-ref="icons">
        @foreach ($iconNames as $icon)
            <div data-icon="{{ $icon }}">
                <x-dynamic-component :component="'flux::icon.' . $icon" variant="mini" class="size-4 text-zinc-400" />
            </div>
        @endforeach
    </div>

    {{-- Search trigger button --}}
    <button
        type="button"
        @click="doOpen()"
        class="flex w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-400 transition hover:border-zinc-300 hover:text-zinc-500"
    >
        <flux:icon.magnifying-glass variant="mini" class="size-4" />
        <span class="flex-1 text-left">Stel een vraag of zoek...</span>
        <kbd class="hidden rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400 sm:inline-block">⌘K</kbd>
    </button>

    {{-- Modal overlay --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/25 pt-[15vh]"
            @click.self="doClose()"
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-zinc-200"
                role="combobox"
                aria-expanded="true"
                aria-haspopup="listbox"
                aria-owns="universal-search-results"
            >
                {{-- Input --}}
                <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3">
                    <flux:icon.magnifying-glass variant="mini" class="size-5 text-zinc-400" />
                    <input
                        x-ref="searchInput"
                        x-model="query"
                        @input.debounce.200ms="$wire.search(query)"
                        @keydown.arrow-down.prevent="moveDown()"
                        @keydown.arrow-up.prevent="moveUp()"
                        @keydown.enter.prevent="select()"
                        @keydown.escape="doClose()"
                        type="text"
                        placeholder="Stel een vraag of zoek..."
                        class="focus-ring flex-1 rounded border-none bg-transparent text-sm text-zinc-900 outline-none placeholder:text-zinc-400"
                        aria-label="Zoek of stel een vraag"
                        aria-autocomplete="list"
                        aria-controls="universal-search-results"
                        :aria-activedescendant="activeIndex >= 0 ? 'search-result-' + activeIndex : null"
                    />
                    <kbd class="rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400">ESC</kbd>
                </div>

                {{-- Results --}}
                <div id="universal-search-results" role="listbox" class="max-h-80 overflow-y-auto p-2">
                    {{-- Entities group --}}
                    <template x-if="results.entities.length > 0">
                        <div>
                            <div class="px-2 py-1.5 text-xs font-medium text-zinc-400">Entiteiten</div>
                            <template x-for="(entity, i) in results.entities" :key="'e-' + entity.id">
                                <button
                                    type="button"
                                    @click="navigate({ ...entity, _type: 'entity' })"
                                    @mouseenter="activeIndex = i"
                                    :id="'search-result-' + i"
                                    :class="activeIndex === i ? 'bg-zinc-100' : ''"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-zinc-100"
                                    role="option"
                                    :aria-selected="activeIndex === i"
                                >
                                    <span x-html="iconHtml(entity.icon)"></span>
                                    <span class="flex-1 truncate text-zinc-900" x-text="entity.name"></span>
                                    <span class="text-xs text-zinc-400" x-text="entity.type"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Inbox group --}}
                    <template x-if="results.inboxItems.length > 0">
                        <div>
                            <div
                                class="px-2 py-1.5 text-xs font-medium text-zinc-400"
                                :class="results.entities.length > 0 ? 'mt-2 border-t border-zinc-100 pt-2' : ''"
                            >Inbox</div>
                            <template x-for="(item, j) in results.inboxItems" :key="'i-' + item.id">
                                <button
                                    type="button"
                                    @click="navigate({ ...item, _type: 'inbox' })"
                                    @mouseenter="activeIndex = results.entities.length + j"
                                    :id="'search-result-' + (results.entities.length + j)"
                                    :class="activeIndex === (results.entities.length + j) ? 'bg-zinc-100' : ''"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-zinc-100"
                                    role="option"
                                    :aria-selected="activeIndex === (results.entities.length + j)"
                                >
                                    <span x-html="iconHtml(item.icon)"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-zinc-900" x-text="item.title"></span>
                                        <span class="block truncate text-xs text-zinc-400" x-text="item.summary"></span>
                                    </span>
                                    <span class="text-xs text-zinc-400" x-text="item.status"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Chat group --}}
                    <template x-if="results.chatItems.length > 0">
                        <div>
                            <div
                                class="px-2 py-1.5 text-xs font-medium text-zinc-400"
                                :class="(results.entities.length + results.inboxItems.length) > 0 ? 'mt-2 border-t border-zinc-100 pt-2' : ''"
                            >Chat</div>
                            <template x-for="(chat, k) in results.chatItems" :key="'c-' + chat.id">
                                <button
                                    type="button"
                                    @click="navigate({ ...chat, _type: 'chat' })"
                                    @mouseenter="activeIndex = results.entities.length + results.inboxItems.length + k"
                                    :id="'search-result-' + (results.entities.length + results.inboxItems.length + k)"
                                    :class="activeIndex === (results.entities.length + results.inboxItems.length + k) ? 'bg-zinc-100' : ''"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-zinc-100"
                                    role="option"
                                    :aria-selected="activeIndex === (results.entities.length + results.inboxItems.length + k)"
                                >
                                    <span x-html="iconHtml(chat.icon)"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-zinc-900" x-text="chat.title"></span>
                                        <span class="block truncate text-xs text-zinc-400" x-text="chat.summary"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Navigation group --}}
                    <template x-if="results.navItems.length > 0">
                        <div>
                            <div
                                class="px-2 py-1.5 text-xs font-medium text-zinc-400"
                                :class="(results.entities.length + results.inboxItems.length + results.chatItems.length) > 0 ? 'mt-2 border-t border-zinc-100 pt-2' : ''"
                            >Navigatie</div>
                            <template x-for="(nav, j) in results.navItems" :key="'n-' + nav.label">
                                <button
                                    type="button"
                                    @click="navigate({ ...nav, _type: 'nav' })"
                                    @mouseenter="activeIndex = results.entities.length + results.inboxItems.length + results.chatItems.length + j"
                                    :id="'search-result-' + (results.entities.length + results.inboxItems.length + results.chatItems.length + j)"
                                    :class="activeIndex === (results.entities.length + results.inboxItems.length + results.chatItems.length + j) ? 'bg-zinc-100' : ''"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-zinc-100"
                                    role="option"
                                    :aria-selected="activeIndex === (results.entities.length + results.inboxItems.length + results.chatItems.length + j)"
                                >
                                    <span x-html="iconHtml(nav.icon)"></span>
                                    <span class="flex-1 truncate text-zinc-900" x-text="nav.label"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- No results: ask AI --}}
                    <template x-if="!hasResults && query.length > 0">
                        <div class="px-2 py-2">
                            <button
                                type="button"
                                @click="askFreeSearch()"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-teal-600 hover:bg-teal-50"
                            >
                                <flux:icon.chat-bubble-left-right class="size-5 shrink-0" />
                                <span>Stel een vraag over '<span x-text="query" class="font-medium"></span>'</span>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Save as overview --}}
                <template x-if="results.entities.length > 0 && query.length > 0">
                    <div class="border-t border-zinc-100 px-3 py-2">
                        <template x-if="!showSaveForm && !saveConfirmed">
                            <button
                                type="button"
                                @click="showSaveForm = true; $nextTick(() => $refs.saveNameInput?.focus())"
                                class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-zinc-400 transition hover:bg-zinc-50 hover:text-zinc-600"
                            >
                                <flux:icon.bookmark-square variant="mini" class="size-3.5" />
                                Bewaar als overzicht
                            </button>
                        </template>
                        <template x-if="showSaveForm">
                            <div class="flex items-center gap-2">
                                <input
                                    x-ref="saveNameInput"
                                    x-model="saveName"
                                    @keydown.enter.prevent="if (saveName.trim()) { $wire.saveOverview(query, saveName); showSaveForm = false; saveConfirmed = true; saveName = ''; }"
                                    @keydown.escape="showSaveForm = false; saveName = ''"
                                    type="text"
                                    placeholder="Naam voor dit overzicht..."
                                    class="flex-1 rounded border border-zinc-200 px-2 py-1 text-xs text-zinc-900 outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400"
                                />
                                <button
                                    type="button"
                                    @click="if (saveName.trim()) { $wire.saveOverview(query, saveName); showSaveForm = false; saveConfirmed = true; saveName = ''; }"
                                    class="rounded bg-teal-600 px-2 py-1 text-xs font-medium text-white hover:bg-teal-700"
                                >
                                    Opslaan
                                </button>
                            </div>
                        </template>
                        <template x-if="saveConfirmed">
                            <p class="px-2 py-1.5 text-xs text-teal-600">Opgeslagen in Mijn overzichten</p>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
