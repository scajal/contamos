<?php

declare(strict_types=1);

namespace App\Modules\Support\Concerns;

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Support\Helpers\TeamActivity;
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

        // Remember the tag set the model carried.
        $before = $this->tagKeys();

        // Attach the tags to the model.
        $this->tags()->attach($tagIds);

        // Record the change in the team's activity log.
        $this->recordTagActivity($before, $this->tagKeys());
    }

    /**
     * Replace the model's tags with the given tags.
     *
     * @param  array<int, string>  $tagIds
     */
    public function syncTags(array $tagIds): void
    {
        $this->assertTagsBelongToTeam($tagIds);

        // Remember the tag set the model carried.
        $before = $this->tagKeys();

        // Sync the tags to the model.
        $this->tags()->sync($tagIds);

        // Record the change in the team's activity log.
        $this->recordTagActivity($before, $this->tagKeys());
    }

    /**
     * Get the keys of the tags the model currently carries,
     * in a stable order.
     *
     * @return array<int, string>
     */
    protected function tagKeys(): array
    {
        /** @var array<int, string> $keys */
        $keys = $this->tags()->pluck(new Tag()->getQualifiedKeyName())->sort()->values()->all();

        return $keys;
    }

    /**
     * Record a change of the model's tag set in the team's activity
     * log, so a tag filter that starts matching a record has a
     * matching entry. An unchanged set is not a change.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    protected function recordTagActivity(array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        TeamActivity::record($this, 'tags_synced', [
            'old' => ['tags' => $before],
            'attributes' => ['tags' => $after],
        ]);
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
     * The query builder filter hands each tag over as its own
     * argument, and other callers pass a list or a
     * comma-separated string, so every form is gathered.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  string|array<int, string>  ...$ids
     */
    #[Scope]
    protected function withAnyTags(Builder $query, string|array ...$ids): void
    {
        // Gather every tag, however it was passed.
        $tagIds = collect($ids)
            ->flatMap(fn (string|array $id): array => is_array($id) ? $id : explode(',', $id))
            ->all();

        // Add the where clause to the query.
        $query->whereHas('tags', fn (Builder $tags): Builder => $tags->whereKey($tagIds));
    }
}
