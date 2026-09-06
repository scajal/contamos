<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Paddle\Billable;
use Laravel\Pennant\Concerns\HasFeatures;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Account> $accounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\TeamInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction> $transactions
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
    use Billable {
        transactions as billingTransactions;
    }

    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Identity\Models\TeamFactory> */
    use HasFactory;

    use HasFeatures;
    use HasUlids;

    /**
     * Get the accounts of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

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

            // Ensure the slug is lowercase.
            $team->slug = Str::lower($team->slug);
        });

        // Callback executed when a team is
        // being updated.
        static::updating(function (self $team): void {
            if ($team->isDirty('name')) {
                $team->slug = Str::lower(self::generateSlug($team->name, $team->getKey())); // @phpstan-ignore-line argument.type Key is always string.
            }
        });
    }

    /**
     * Generate a unique slug for the team
     * off the name.
     */
    protected static function generateSlug(string $name, ?string $exclude = null): string
    {
        // Generate a default slug based on the name.
        $default = Str::slug($name);

        // Build the query to find a unique slug for
        // the team.
        $isTaken = fn (string $slug): bool => static::query()
            ->where('slug', $slug)
            ->when(filled($exclude), fn (Builder $query): Builder => $query->whereKeyNot($exclude))
            ->exists();

        // If the default slug is not taken, return it.
        if (! $isTaken($default)) {
            return $default;
        }

        // If the default slug is taken, generate a new one.
        do {
            $candidate = "{$default}-".Str::lower(Str::random(6));
        } while ($isTaken($candidate));

        return $candidate;
    }

    /**
     * Get the route key name for the
     * model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the invitations of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Identity\Models\Team\TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Team\TeamInvitation::class);
    }

    /**
     * Get the team's current plan.
     */
    public function plan(): Plan
    {
        return match (true) {
            $this->subscribed() => Plan::Pro,
            default => Plan::Free,
        };
    }

    /**
     * Get the tags of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Get the transactions of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
