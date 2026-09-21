<?php

namespace App\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Concerns\DefinesKeyType;
use App\Modules\Support\Concerns\HasActivation;
use App\Modules\Support\Concerns\LogsTeamActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property bool $is_active
 * @property string $name
 * @property string $team_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\Team $team
 *
 * @method static \Database\Factories\Modules\Core\Finance\Models\Team\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Tag isActive(string|bool $value = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Core\Finance\Models\Team\Tag query()
 *
 * @mixin \Eloquent
 */
#[Fillable('is_active', 'name')]
class Tag extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    use HasActivation;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Core\Finance\Models\Team\TagFactory> */
    use HasFactory;

    use HasUlids;
    use LogsTeamActivity;

    /**
     * Get the attributes whose changes are recorded in the
     * team's activity log.
     *
     * @return array<int, string>
     */
    public function activityLogAttributes(): array
    {
        return ['is_active', 'name'];
    }

    /**
     * Get the key of the team the tag belongs to.
     */
    public function activityTeamKey(): ?string
    {
        return is_string($key = $this->getAttribute($this->team()->getForeignKeyName())) ? $key : null;
    }

    /**
     * Get the team that owns the tag.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
