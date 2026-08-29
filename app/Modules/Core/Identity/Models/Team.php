<?php

namespace App\Modules\Core\Identity\Models;

use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\Membership|null $membership
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User> $users
 *
 * @method static \Database\Factories\Modules\Core\Identity\Models\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team query()
 *
 * @mixin \Eloquent
 */
class Team extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Identity\Models\TeamFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Callback executed when a team is
        // being created.
        static::creating(function (self $team): void {
            if (blank($team->slug)) {
                $team->slug = self::generateSlug($team->name);
            }
        });

        // Callback executed when a team is
        // being updated.
        static::updating(function (self $team): void {
            if ($team->isDirty('name')) {
                $team->slug = self::generateSlug($team->name, $team->getKey()); // @phpstan-ignore-line argument.type Key is always string.
            }
        });
    }

    /**
     * Generate a unique slug for the team
     * off the name.
     */
    protected static function generateSlug(string $name, ?string $exclude = null): string
    {
        $default = Str::slug($name);

        // Build the query to find a unique slug for
        // the team.
        $query = static::query()
            ->where(fn (Builder $query): Builder => $query->where('slug', $default)->orWhere('slug', 'like', "{$default}%"))
            ->when(filled($exclude), fn (Builder $query): Builder => $query->whereKeyNot($exclude));

        // Get the latest suffx created in
        // the database.
        $suffix = ($existing = $query->pluck('slug'))
            ->map(fn (string $slug): ?int => match (true) { // @phpstan-ignore-line argument.type Anonymous collection.
                $slug === $default => 0,
                preg_match('/^'.preg_quote($default, '/').'-(\d+)$/', $slug, $matches) => (int) $matches[1],
                default => null,
            })
            ->filter(fn (?int $suffix): bool => filled($suffix))
            ->max();

        return $existing->isEmpty() ? $default : "{$default}-".($suffix + 1);
    }

    /**
     * Get the users that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Modules\Core\Identity\Models\User, $this, \App\Modules\Core\Identity\Models\Membership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, new Membership()->getTable(), $this->getForeignKey(), new User()->getForeignKey())
            ->using(Membership::class)
            ->withTimestamps()
            ->as('membership');
    }
}
