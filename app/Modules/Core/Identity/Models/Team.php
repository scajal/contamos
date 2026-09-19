<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Paddle\Billable;
use Laravel\Paddle\Cashier;
use Laravel\Pennant\Concerns\HasFeatures;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Account> $accounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Billing\Models\Transaction> $billingTransactions
 * @property-read \App\Modules\Billing\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\TeamInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Billing\Models\Subscription> $subscriptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction> $transactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transfer> $transfers
 * @property-read \App\Modules\Core\Identity\Models\Membership|null $membership
 * @property-read \App\Modules\Core\Identity\Models\User|null $owner
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
     * Get the customer's email to associate with Paddle.
     */
    public function paddleEmail(): ?string
    {
        // Billing is the owner's business, so billing correspondence
        // goes to them.
        return $this->owner?->email;
    }

    /**
     * Get the customer's name to associate with Paddle.
     */
    public function paddleName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the user who owns the team. Every team has exactly
     * one owner, the user who created it.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<\App\Modules\Core\Identity\Models\User|null, never>
     */
    protected function owner(): Attribute
    {
        return Attribute::get(fn (): ?User => $this->relationLoaded('users')
            ? $this->users->first(fn (User $user): bool => $user->membership?->role === Role::Owner)
            : $this->users()->wherePivot('role', Role::Owner)->first());
    }

    /**
     * Get the roles the team may hand out. Choosing a role is a pro
     * feature, so a team without it only has admins besides its
     * owner.
     *
     * @return array<int, \App\Modules\Core\Identity\Enums\Team\Role>
     */
    public function assignableRoles(): array
    {
        return $this->assignsRoles() ? Role::assignable() : [Role::Admin];
    }

    /**
     * Indicate if the team may choose the role its members carry.
     */
    public function assignsRoles(): bool
    {
        return Feature::AssignRoles->enabled($this);
    }

    /**
     * Get the role a stored role acts as on the team. This is the
     * single place the plan bends a stored role: while the team
     * cannot choose roles, the owner stays the owner and everybody
     * else acts as an admin. The stored role is left untouched, so
     * returning to pro restores it.
     */
    public function effectiveRole(?Role $role): ?Role
    {
        if (! $role instanceof Role) {
            return null;
        }

        return $role === Role::Owner || $this->assignsRoles() ? $role : Role::Admin;
    }

    /**
     * Get the current plan of the team.
     */
    public function plan(): Plan
    {
        return $this->subscribedToProduct(Plan::Pro->productId()) ? Plan::Pro : Plan::Free;
    }

    /**
     * Generate a redirection to the customer portal.
     */
    public function redirectToCustomerPortal(): RedirectResponse
    {
        // Generate a customer for the team if it doesn't exist.
        if (! ($customer = $this->customer)) {
            $customer = $this->createAsCustomer();
        }

        // Generate the corresponding payload.
        $payload = ($subscription = $this->subscription())
            ? ['subscription_ids' => $subscription->paddle_id] // @phpstan-ignore-line property.type False positive.
            : null;

        // Generate the Cashier session for the
        // customer.
        /** @var \App\Modules\Billing\Models\Customer $customer */
        $session = Cashier::api('POST', "customers/{$customer->paddle_id}/portal-sessions", $payload)->json('data');

        return redirect()->away($session['urls']['general']['overview']); // @phpstan-ignore-line offsetAccess.nonOffsetAccessible False positive.
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
     * Get the transfers of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Core\Finance\Models\Team\Transfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
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
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');
    }
}
