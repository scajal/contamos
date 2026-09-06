<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Identity\Models\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Identity\Models\Team\TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Identity\Models\Team\TeamInvitation>
     */
    protected $model = TeamInvitation::class;

    /**
     * Define the model's default state.
     *
     * Each invitation belongs to a \App\Modules\Core\Identity\Models\Team and
     * was sent by a \App\Modules\Core\Identity\Models\User.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'expires_at' => CarbonImmutable::now()->addDays(7),
            (new TeamInvitation)->inviter()->getForeignKeyName() => User::factory(),
            (new TeamInvitation)->team()->getForeignKeyName() => Team::factory(),
        ];
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => CarbonImmutable::now()->subDay(),
        ]);
    }
}
