<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Models;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Support\Concerns\DefinesKeyType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $team_id
 * @property string $user_id
 * @property \App\Modules\Core\Identity\Enums\Team\Role $role
 * @property array<string, bool>|null $notification_preferences
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 * @property-read \App\Modules\Core\Identity\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Membership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Membership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Identity\Models\Membership query()
 *
 * @mixin \Eloquent
 */
#[Table('team_members')]
class Membership extends Pivot
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
            'role' => Role::class,
        ];
    }

    /**
     * Get the notification switches of the membership with
     * every type filled in, a type the member never touched
     * reading as on.
     *
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        $preferences = [];

        foreach (NotificationType::cases() as $type) {
            $preferences[$type->value] = $this->wantsNotification($type);
        }

        return $preferences;
    }

    /**
     * Indicate if the member keeps the given notification
     * type switched on. A type they never touched is on.
     */
    public function wantsNotification(NotificationType $type): bool
    {
        return ($this->notification_preferences[$type->value] ?? true) !== false;
    }

    /**
     * Get the team that the membership belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that the membership belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
