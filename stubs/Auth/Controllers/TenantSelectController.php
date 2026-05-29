<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Stub controller published by mortelos/starter.
 *
 * Replace with a real tenant-select tied to your membership model.
 * The minimal stub here picks the user's first available tenant (or
 * a single global tenant in dev) and writes it to session('tenant_id'),
 * which is what the starter route bridge looks for to redirect to the
 * dashboard.
 *
 * Contract:
 *   show:  render a picker if the user has multiple tenants, otherwise auto-pick.
 *   store: persist session('tenant_id') and redirect home.
 */
final class TenantSelectController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // Replace with: $user->memberships()->orderBy(...)->get()
        $tenants = $this->resolveTenants($user);

        if (count($tenants) === 1) {
            $request->session()->put('tenant_id', $tenants[0]['id']);

            return redirect()->route('dashboard');
        }

        // Minimal stub: render a basic picker so the host boots before a
        // proper Livewire tenant picker is built. Replace with a real view
        // (e.g. a Livewire SFC under resources/views/livewire/pages/auth/)
        // once your tenant model is in place.
        $csrf = csrf_token();
        $action = route('auth.tenant-store');
        $options = collect($tenants)
            ->map(fn (array $t) => sprintf(
                '<option value="%s">%s</option>',
                e($t['id']),
                e($t['name']),
            ))
            ->implode("\n");

        return response(<<<HTML
            <form method="POST" action="{$action}">
                <input type="hidden" name="_token" value="{$csrf}">
                <label for="tenant_id">Select tenant</label>
                <select name="tenant_id" id="tenant_id">{$options}</select>
                <button type="submit">Continue</button>
            </form>
        HTML);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'string'],
        ]);

        // Replace with a membership lookup that confirms the user actually
        // belongs to this tenant before persisting it on the session.
        $request->session()->put('tenant_id', $request->string('tenant_id')->toString());

        return redirect()->route('dashboard');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function resolveTenants(mixed $user): array
    {
        // Replace with your real membership query.
        return [
            ['id' => 'default', 'name' => 'Default tenant'],
        ];
    }
}
