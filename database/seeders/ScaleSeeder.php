<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;

/**
 * Grows the default workspace to a chosen size, so a page that costs more per list item shows up as a bigger
 * page. The page-weight tests call `scale()` with two sizes and compare the pages; `db:seed --class=ScaleSeeder`
 * fills a local database with 15 of each to look at a longer list.
 *
 * It adds `$size` extra members and `$size` open invitations on top of what DatabaseSeeder made, with fictional
 * names and the reserved `scale.example.test` domain. Run it after DatabaseSeeder. `scale(0)` takes every row
 * this seeder made away again, so a test can start from the small size at any point.
 *
 * Add the lists of your own screens here (one `$size` per list), so the sweep in `PageWeightTest` grows them too.
 */
final class ScaleSeeder extends Seeder
{
    private const DOMAIN = 'scale.example.test';

    /** Used by `db:seed --class=ScaleSeeder`; a test calls `scale()` with its own sizes. */
    public function run(int $size = 15): void
    {
        $this->scale($size);
    }

    /** Grows to `$size` extra members and `$size` open invitations (0 removes both). */
    public function scale(int $size): void
    {
        $this->members($size);
        $this->invitations($size);
    }

    /**
     * Grows to exactly `$size` extra members, or removes them for 0.
     *
     * @return list<string> the user ids of those members, which the users page puts on their rows
     */
    public function members(int $size): array
    {
        $tenantId = $this->tenantId();
        $existing = DB::table('users')->where('email', 'like', 'member-%@'.self::DOMAIN)->count();

        if ($size < $existing) {
            $ids = DB::table('users')->where('email', 'like', 'member-%@'.self::DOMAIN)->orderByDesc('id')->limit($existing - $size)->pluck('id');
            DB::table('tenant_user')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
        }

        for ($number = $existing + 1; $number <= $size; $number++) {
            // forceCreate: a test calls this seeder directly, outside the unguarded `db:seed`.
            $user = User::query()->forceCreate([
                'name' => 'Scale Member '.$number,
                'email' => 'member-'.$number.'@'.self::DOMAIN,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            DB::table('tenant_user')->insert([
                'tenant_id' => $tenantId,
                'user_id' => Scalar::string($user->getKey()),
                'role' => 'member',
                'role_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return self::ids(DB::table('users')->where('email', 'like', 'member-%@'.self::DOMAIN)->pluck('id'));
    }

    /**
     * Grows to exactly `$size` open invitations, or revokes them for 0.
     *
     * @return list<string> the invitation ids, which the users page puts on their rows
     */
    public function invitations(int $size): array
    {
        $open = fn () => Invitation::query()->open()->where('email', 'like', 'invitee-%@'.self::DOMAIN);
        $existing = $open()->count();

        if ($size < $existing) {
            // An invitation is a projection of its events: revoke it through the Action that owns that.
            foreach ($open()->orderByDesc('id')->limit($existing - $size)->pluck('id') as $id) {
                app(RevokeInvitation::class)->handle(Scalar::string($id), $this->actor());
            }
        }

        $role = Role::query()->where('name', 'owner')->firstOrFail();

        for ($number = $existing + 1; $number <= $size; $number++) {
            app(InviteUser::class)->handle('invitee-'.$number.'@'.self::DOMAIN, Scalar::string($role->getKey()), $this->actor());
        }

        return self::ids($open()->pluck('id'));
    }

    /**
     * @param  Collection<array-key, mixed>  $ids
     * @return list<string>
     */
    private static function ids(Collection $ids): array
    {
        return array_values($ids->map(fn (mixed $id): string => Scalar::string($id))->all());
    }

    /** The seeded admin: it holds every right of the owner role, which InviteUser requires of the inviter. */
    private function actor(): Actor
    {
        return Actor::user(User::query()->where('email', 'admin@example.test')->firstOrFail());
    }

    private function tenantId(): string
    {
        $id = config('starter.tenancy.default_tenant_id', 'default');

        return is_string($id) ? $id : 'default';
    }
}
