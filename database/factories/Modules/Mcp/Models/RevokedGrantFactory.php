<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Mcp\Models;

use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Models\RevokedGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Mcp\Models\RevokedGrant>
 */
class RevokedGrantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Mcp\Models\RevokedGrant>
     */
    protected $model = RevokedGrant::class;

    /**
     * Define the model's default state.
     *
     * Each revocation belongs to a
     * \App\Modules\Core\Identity\Models\User and names the session
     * the revoked tokens carried.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'session_id' => 'session_'.Str::upper(Str::random(26)),
            (new RevokedGrant)->user()->getForeignKeyName() => User::factory(),
        ];
    }

    /**
     * Indicate that the revocation names a client rather than a
     * session.
     */
    public function forClient(string $clientId): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $clientId,
            'session_id' => null,
        ]);
    }
}
