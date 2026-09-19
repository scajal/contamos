<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasActivation;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string|null $avatar
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string|null $external_id
 * @property bool $is_active
 * @property string $name
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $current_team_id
 * @property-read \App\Modules\Core\Identity\Models\Team|null $currentTeam
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \App\Modules\Core\Identity\Models\Membership|null $membership
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team> $teams
 *
 * @method static \Database\Factories\Modules\Core\Identity\Models\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User isActive(string|bool $value = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['avatar', 'email', 'external_id', 'is_active', 'name'])]
#[Hidden(['external_id', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    use HasActivation;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Identity\Models\UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;

    /**
     * Indicate if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where(
            "{$team->getTable()}.{$team->getKeyName()}",
            $team->getKey()
        )->exists();
    }

    /**
     * Indicate if the user holds the given permission on the
     * team, according to their effective role there.
     */
    public function hasTeamPermission(Team $team, Permission $permission): bool
    {
        return $this->roleOn($team)?->grants($permission) === true;
    }

    /**
     * Determine if the user can access the filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // @todo: Add security validation.
        return in_array($this->email, ['operator@example.com', 'operator@example.com']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the current team of the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the password field for the user.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Get the role the user effectively holds on the team, or
     * null when they do not belong to it.
     *
     * Choosing roles is a pro feature, so on a team without it
     * the owner stays the owner and every other stored role
     * acts as an admin. The stored role is left untouched, so
     * returning to pro restores it.
     */
    public function roleOn(Team $team): ?Role
    {
        $role = $this->teams()
            ->whereKey($team->getKey())
            ->first()?->membership?->role;

        if (! $role instanceof Role) {
            return null;
        }

        if ($role === Role::Owner || Feature::AssignRoles->enabled($team)) {
            return $role;
        }

        return Role::Admin;
    }

    /**
     * Switch the user to a different team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        // Associate the team with the user.
        $this->currentTeam()
            ->associate($team)
            ->save();

        // Set the team as default in the URL.
        URL::defaults(['team' => $team->getRouteKey()]);

        return true;
    }

    /**
     * Get the teams to which the user belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Modules\Core\Identity\Models\Team, $this, \App\Modules\Core\Identity\Models\Membership, 'membership'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, new Membership()->getTable(), $this->getForeignKey(), new Team()->getForeignKey())
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');
    }
}
