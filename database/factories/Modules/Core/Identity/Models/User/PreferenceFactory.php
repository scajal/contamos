<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Identity\Models\User;

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Models\User\Preference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Identity\Models\User\Preference>
 */
class PreferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Identity\Models\User\Preference>
     */
    protected $model = Preference::class;

    /**
     * Define the model's default state.
     *
     * Each preference belongs to a
     * \App\Modules\Core\Identity\Models\User and defaults to
     * the dark colour scheme.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => PreferenceKey::Appearance,
            'value' => 'dark',
            (new Preference)->user()->getForeignKeyName() => User::factory(),
        ];
    }

    /**
     * Indicate that the preference stores the given locale.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => PreferenceKey::Locale,
            'value' => $locale,
        ]);
    }

    /**
     * Indicate that the preference stores the given colour
     * scheme.
     */
    public function appearance(string $appearance): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => PreferenceKey::Appearance,
            'value' => $appearance,
        ]);
    }
}
