<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Identity\Models;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Identity\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Identity\Models\Team>
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = fake()->unique()->company(),
            'slug' => Str::slug($name),
        ];
    }

    /**
     * Indicate that the team was granted the given plan by
     * hand, optionally until the given moment.
     */
    public function grantedPlan(Plan $plan, ?CarbonImmutable $until = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan_override' => $plan,
            'plan_override_expires_at' => $until,
        ]);
    }

    /**
     * Indicate that the given user owns the team.
     */
    public function ownedBy(User $user): static
    {
        return $this->afterCreating(function (Team $team) use ($user): void {
            $team->users()->attach($user, ['role' => Role::Owner]);
        });
    }
}
