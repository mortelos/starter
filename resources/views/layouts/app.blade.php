<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' rx='4' fill='%2309090b'/%3E%3Cpath d='M4 8h8' stroke='%23ffffff' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E">
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

    <flux:sidebar sticky collapsible class="border-r border-zinc-200 bg-surface-alt">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="{{ route('dashboard') }}"
                wire:navigate
                name="{{ config('app.name') }}"
            />
            <flux:sidebar.toggle class="lg:hidden min-h-[44px] -me-2" icon="x-mark" />
            <flux:sidebar.collapse class="max-lg:hidden min-h-[44px] in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        @if (is_string($sidebarNavComponent))
            <livewire:dynamic-component
                :is="$sidebarNavComponent"
                wire:key="starter-sidebar-nav" />
        @endif

        <flux:sidebar.spacer />

        @if (is_string($topbarComponent))
            <flux:sidebar.nav class="max-lg:hidden">
                <livewire:dynamic-component
                    :is="$topbarComponent"
                    :sidebar="true"
                    position="top"
                    align="start"
                    wire:key="starter-sidebar-topbar" />
            </flux:sidebar.nav>
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

        @if (is_string($topbarComponent))
            <livewire:dynamic-component
                :is="$topbarComponent"
                wire:key="starter-mobile-topbar" />
        @endif
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
