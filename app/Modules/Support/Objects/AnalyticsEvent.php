<?php

declare(strict_types=1);

namespace App\Modules\Support\Objects;

use Illuminate\Contracts\Support\Arrayable;
use Inertia\Inertia;
use Inertia\ResponseFactory;

/**
 * A Google Analytics event the browser reports once the next
 * page loads, for a step that happened on the server, such as
 * signing up or creating a team.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class AnalyticsEvent implements Arrayable
{
    /**
     * Create a new instance of the class.
     *
     * @param  array<string, scalar>  $params
     */
    public function __construct(
        public string $name,
        public array $params = [],
    ) {}

    /**
     * Flash the event to Inertia's session, after any event the
     * same request flashed before it.
     */
    public function flash(): ResponseFactory
    {
        /** @var array<int, array{name: string, params: array<string, scalar>}> $flashed */
        $flashed = Inertia::getFlashed()['analytics'] ?? [];

        return Inertia::flash('analytics', [...$flashed, $this->toArray()]);
    }

    /**
     * Create a new instance of the class with the given name
     * and parameters.
     *
     * @param  array<string, scalar>  $params
     */
    public static function make(string $name, array $params = []): self
    {
        return new self($name, $params);
    }

    /**
     * Get the instance as an array.
     *
     * @return array{name: string, params: array<string, scalar>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'params' => $this->params,
        ];
    }
}
