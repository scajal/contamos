<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User\Preference;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasActivation;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User\Preference> $preferences
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
class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasTenants
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
     * Determine if the user can access the given team panel tenant.
     *
     * Operators support every team without holding a membership first, so
     * reaching a tenant is the same check as reaching the panel itself.
     *
     * @param  \App\Modules\Core\Identity\Models\Team  $tenant
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->canAccessPanel(Filament::getPanel('team'));
    }

    /**
     * Get the teams the user can administer as Filament tenants.
     *
     * @return \Illuminate\Support\Collection<int, \App\Modules\Core\Identity\Models\Team>
     */
    public function getTenants(Panel $panel): Collection
    {
        return Team::query()->orderBy('name')->get();
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
     * Get the value the user stored for the given preference,
     * or null when they never chose one.
     */
    public function preference(PreferenceKey $key): ?string
    {
        $value = $this->preferences()
            ->where('key', $key)
            ->value('value');

        return is_string($value) ? $value : null;
    }

    /**
     * Get the preferences the user stored.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Identity\Models\User\Preference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(Preference::class);
    }

    /**
     * Get the locale mail and notifications to the user are
     * rendered in: the one they stored, or English.
     */
    public function preferredLocale(): string
    {
        return $this->preference(PreferenceKey::Locale) ?? 'en';
    }

    /**
     * Store the given value for the preference, replacing the
     * one the user chose before.
     */
    public function rememberPreference(PreferenceKey $key, string $value): Preference
    {
        return $this->preferences()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Get the role the user effectively holds on the team, or
     * null when they do not belong to it. The team owns the rule
     * that turns a stored role into the role it acts as.
     */
    public function roleOn(Team $team): ?Role
    {
        return $team->effectiveRole($this->membershipOn($team)?->role);
    }

    /**
     * Get the membership that ties the user to the team, or
     * null when they do not belong to it.
     */
    public function membershipOn(Team $team): ?Membership
    {
        return $this->teams()
            ->whereKey($team->getKey())
            ->first()?->membership;
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
            ->withPivot('role', 'notification_preferences')
            ->withTimestamps()
            ->as('membership');
    }
}
