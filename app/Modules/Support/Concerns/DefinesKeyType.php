<?php

declare(strict_types=1);

namespace App\Modules\Support\Concerns;

/**
 * @template TKey of string|int
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait DefinesKeyType
{
    /**
     * Get the primary key for the model.
     *
     * @return TKey
     */
    #[\Override]
    public function getKey(): string|int
    {
        /** @var TKey $key */
        /** @disregard P1013 */
        $key = $this->getAttribute($this->getKeyName());

        return $key;
    }
}
