<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Default tenant select handler.
 *
 * Auto-picks the user's single tenant in dev so a fresh host boots the
 * login → tenant-select → dashboard flow out of the box. Replace with a real
 * picker (typically a Livewire SFC under resources/views/livewire/pages/auth/)
 * once your membership model is in place.
 *
 * Contract:
 *   show:  render a picker if the user has multiple tenants, otherwise auto-pick.
 *   store: validate, persist session('tenant_id') and redirect home.
 */
final class TenantSelectController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $tenants = $this->resolveTenants($user);

        if (count($tenants) === 1) {
            $request->session()->put('tenant_id', $tenants[0]['id']);

            return redirect()->route('dashboard');
        }

        $csrf = csrf_token();
        $action = route('auth.tenant-store');
        $options = collect($tenants)
            ->map(fn (array $t) => sprintf('<option value="%s">%s</option>', e($t['id']), e($t['name'])))
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

        $request->session()->put('tenant_id', $request->string('tenant_id')->toString());

        return redirect()->route('dashboard');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function resolveTenants(mixed $user): array
    {
        return [
            ['id' => 'default', 'name' => 'Default tenant'],
        ];
    }
}
