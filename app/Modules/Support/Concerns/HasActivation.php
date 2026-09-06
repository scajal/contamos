<?php

declare(strict_types=1);

namespace App\Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property bool $is_active
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasActivation
{
    /**
     * Initialize the trait.
     */
    public function initializeHasActivation(): void
    {
        /** @disregard P1013 */
        $this->mergeCasts([
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Mark the model as active.
     */
    public function activate(): bool
    {
        /** @disregard P1013 */
        return $this->update(['is_active' => true]);
    }

    /**
     * Mark the model as inactive.
     */
    public function deactivate(): bool
    {
        /** @disregard P1013 */
        return $this->update(['is_active' => false]);
    }

    /**
     * Scope the query to models with the given active state.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    #[Scope]
    protected function isActive(Builder $query, string|bool $value = true): void
    {
        $query->where($this->qualifyColumn('is_active'), filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
