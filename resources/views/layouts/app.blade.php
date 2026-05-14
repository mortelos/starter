<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white font-sans antialiased">
    @php
        $sidebarNavComponent = config('starter.layout.sidebar_nav_component');
        $topbarComponent = config('starter.layout.topbar_component');
        $universalSearchComponent = config('starter.layout.universal_search_component');
    @endphp

    <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 bg-surface-alt">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="{{ route('dashboard') }}"
                wire:navigate
                name="{{ config('app.name') }}"
            />
            <flux:sidebar.collapse class="lg:hidden min-h-[44px]" />
        </flux:sidebar.header>

        @if (is_string($sidebarNavComponent))
            <livewire:dynamic-component
                :is="$sidebarNavComponent"
                wire:key="starter-sidebar-nav" />
        @endif

        <flux:sidebar.spacer />

        @if (is_string($topbarComponent))
            <livewire:dynamic-component
                :is="$topbarComponent"
                wire:key="starter-topbar" />
        @endif
    </flux:sidebar>

    {{-- Connection loss banner --}}
    <div
        x-data="{ offline: false }"
        @offline.window="offline = true"
        @online.window="offline = false"
        x-show="offline"
        x-cloak
        class="fixed inset-x-0 top-0 z-50 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white"
        role="status"
        aria-live="polite"
    >
        Verbinding verbroken. Herverbinden...
    </div>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden min-h-[44px]" icon="bars-2" inset="left" />
        <flux:spacer />
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @if (is_string($universalSearchComponent))
        <div class="hidden">
            <livewire:dynamic-component
                :is="$universalSearchComponent"
                wire:key="starter-universal-search" />
        </div>
    @endif

    @php
        $chatSettingsService = config('starter.chat.settings_service');
        $chatConversationPanelComponent = config('starter.chat.conversation_panel_component');
        $showChatConversationPanel = is_string($chatSettingsService)
            && is_string($chatConversationPanelComponent)
            && auth()->check()
            && app($chatSettingsService)->enabled();
    @endphp

    @if ($showChatConversationPanel)
        <livewire:dynamic-component
            :is="$chatConversationPanelComponent"
            wire:key="starter-chat-conversation-panel" />
    @endif

    @livewireScriptConfig
    @fluxScripts
</body>
</html>
