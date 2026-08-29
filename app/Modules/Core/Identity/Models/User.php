<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property string|null $avatar
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $external_id
 * @property string $name
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \App\Modules\Core\Identity\Models\Membership|null $membership
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team> $teams
 *
 * @method static \Database\Factories\Modules\Core\Identity\Models\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\User query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['email', 'external_id', 'name'])]
#[Hidden(['external_id', 'remember_token'])]
class User extends Authenticatable
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Identity\Models\UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;

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
     * Get the teams to which the user belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Modules\Core\Identity\Models\Team, $this, \App\Modules\Core\Identity\Models\Membership, 'membership'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, new Membership()->getTable(), $this->getForeignKey(), new Team()->getForeignKey())
            ->using(Membership::class)
            ->withTimestamps()
            ->as('membership');
    }
}
