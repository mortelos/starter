import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';

// Livewire bundles Alpine; expose it for debugging and ad-hoc directives.
window.Alpine = Alpine;

Livewire.start();
