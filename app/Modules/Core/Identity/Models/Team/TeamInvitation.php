<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property string $code
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property string $invited_by
 * @property string $team_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\User $inviter
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 *
 * @method static \Database\Factories\Modules\Core\Identity\Models\Team\TeamInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\TeamInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\TeamInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\TeamInvitation pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\TeamInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Team\TeamInvitation status(string $status)
 *
 * @mixin \Eloquent
 */
#[Fillable(['accepted_at', 'code', 'email', 'expires_at'])]
class TeamInvitation extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Identity\Models\Team\TeamInvitationFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Mark the invitation as accepted.
     */
    public function accept(): static
    {
        return tap($this)->update(['accepted_at' => CarbonImmutable::now()]);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Callback executed when an invitation is
        // being created.
        static::creating(function (TeamInvitation $invitation): void {
            // Normalize the email so lookups and uniqueness
            // checks ignore case and surrounding spaces.
            $invitation->email = Str::lower(trim($invitation->email));

            // Generate a new code if one is not provided.
            if (blank($invitation->code)) {
                $invitation->code = Str::random(64);
            }

            // Set the expiration date to 7 days from now if not provided.
            if (blank($invitation->expires_at)) {
                $invitation->expires_at = CarbonImmutable::now()->addDays(7);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Get the user who created the invitation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Indicate if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Indicate if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Indicate if the invitation was sent to the
     * given email address, ignoring case.
     */
    public function isFor(string $email): bool
    {
        return Str::lower($this->email) === Str::lower($email);
    }

    /**
     * Indicate if the invitation can still be accepted.
     */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Scope the query to invitations that can still be accepted.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\TeamInvitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query
            ->whereNull('accepted_at')
            ->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * Regenerate the invitation code and push
     * the expiration date forward.
     */
    public function renew(): static
    {
        return tap($this)->update([
            'code' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->addDays(7),
        ]);
    }

    /**
     * Scope the query to invitations in the given status, derived
     * from the accepted and expiration dates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team\TeamInvitation>  $query
     * @param  'accepted' | 'expired' | 'pending'  $status
     */
    #[Scope]
    protected function status(Builder $query, string $status): void
    {
        match ($status) {
            'accepted' => $query->whereNotNull('accepted_at'),
            'expired' => $query->whereNull('accepted_at')->where('expires_at', '<=', CarbonImmutable::now()),
            'pending' => $query->whereNull('accepted_at')->where('expires_at', '>', CarbonImmutable::now()),
            default => $query,
        };
    }

    /**
     * Get the team that the invitation belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
