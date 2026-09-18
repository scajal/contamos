<?php

declare(strict_types=1);

namespace App\Modules\Support\Concerns;

use App\Modules\Core\Finance\Models\Team\Tag;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTags
{
    /**
     * Attach the given tags to the model.
     *
     * @param  array<int, string>  $tagIds
     */
    public function attachTags(array $tagIds): void
    {
        // Validate the tag IDs are not empty.
        if ($tagIds === []) {
            return;
        }

        $this->assertTagsBelongToTeam($tagIds);

        // Attach the tags to the model.
        $this->tags()->attach($tagIds);
    }

    /**
     * Replace the model's tags with the given tags.
     *
     * @param  array<int, string>  $tagIds
     */
    public function syncTags(array $tagIds): void
    {
        $this->assertTagsBelongToTeam($tagIds);

        // Sync the tags to the model.
        $this->tags()->sync($tagIds);
    }

    /**
     * Validate that the given tags belong to the same team as the tagged model.
     *
     * @param  array<int, string>  $tagIds
     */
    protected function assertTagsBelongToTeam(array $tagIds): void
    {
        $tags = Tag::query()
            ->whereKey($tagIds)
            ->get();

        foreach ($tags as $tag) {
            if ($tag->getAttribute($tag->team()->getForeignKeyName()) !== $this->getAttribute($this->team()->getForeignKeyName())) {
                throw new InvalidArgumentException('A tag must belong to the same team as the tagged model.');
            }
        }
    }

    /**
     * Get the tags for the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\App\Modules\Core\Finance\Models\Team\Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * The team that owns the tagged model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Core\Identity\Models\Team, $this>
     */
    abstract public function team(): BelongsTo;

    /**
     * Scope the query to models that have any of the given tags.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  string|array<int, string>  $ids
     */
    #[Scope]
    protected function withAnyTags(Builder $query, string|array $ids): void
    {
        // Validate that the IDs are an array.
        $tagIds = is_array($ids) ? $ids : explode(',', $ids);

        // Add the where clause to the query.
        $query->whereHas('tags', fn (Builder $tags): Builder => $tags->whereKey($tagIds));
    }
}
