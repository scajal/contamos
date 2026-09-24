<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Models;

use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Concerns\DefinesKeyType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A grant an AI assistant held on a user's account that the user
 * revoked. The MCP server refuses every token that belongs to it,
 * without waiting for the token to expire.
 *
 * @property string $id
 * @property string|null $client_id
 * @property string|null $session_id
 * @property string $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Modules\Core\Identity\Models\User $user
 *
 * @method static \Database\Factories\Modules\Mcp\Models\RevokedGrantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Mcp\Models\RevokedGrant covering(\App\Modules\Core\Identity\Models\User $user, ?string $sessionId, ?string $clientId, \Carbon\CarbonImmutable $issuedAt)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Mcp\Models\RevokedGrant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Mcp\Models\RevokedGrant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Modules\Mcp\Models\RevokedGrant query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['client_id', 'session_id'])]
#[Table('mcp_revoked_grants')]
class RevokedGrant extends Model
{
    /** @use \App\Modules\Support\Concerns\DefinesKeyType<string> */
    use DefinesKeyType;

    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\Modules\Mcp\Models\RevokedGrantFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Scope the query to the revocations that cover a token: one
     * naming the token's session, or one naming the token's
     * client for the same user, recorded after the token was
     * issued. A client reconnected after revoking gets new
     * tokens, which the second kind no longer covers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Mcp\Models\RevokedGrant>  $query
     */
    #[Scope]
    protected function covering(Builder $query, User $user, ?string $sessionId, ?string $clientId, CarbonImmutable $issuedAt): void
    {
        $query->where(fn (Builder $query): Builder => $query
            ->when($sessionId !== null, fn (Builder $query): Builder => $query->orWhere('session_id', $sessionId))
            ->when($clientId !== null, fn (Builder $query): Builder => $query->orWhere(fn (Builder $query): Builder => $query
                ->where($this->user()->getForeignKeyName(), $user->getKey())
                ->where('client_id', $clientId)
                ->where(self::CREATED_AT, '>=', $issuedAt))),
        );

        // With neither a session nor a client on the token there
        // is nothing a revocation could name.
        $query->when($sessionId === null && $clientId === null, fn (Builder $query): Builder => $query->whereRaw('1 = 0'));
    }

    /**
     * Get the user whose grant was revoked.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
