<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\TagController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Tag.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tagPayload(array $overrides = []): array
{
    return [
        'name' => 'Comida',
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred list of tags', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->active()->for($team)->create(['name' => 'Comida']);

        $this->get(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/index')
                ->has('user')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('tags.data', 1)
                    ->where('tags.data.0.name', 'Comida')));
    });

    it('filters tags by activity and search', function (): void {
        [$user, $team] = actingMember();
        $matching = Tag::factory()->active()->for($team)->create(['name' => 'Comida']);
        Tag::factory()->inactive()->for($team)->create(['name' => 'Salidas']);

        $this->get(route('finance.teams.tags.index', [
            'team' => $team->getKey(),
            'filter' => [
                'is_active' => 'true',
                'search' => 'Comi',
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.is_active', 'true')
                ->where('filters.search', 'Comi')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('tags.data', 1)
                    ->where('tags.data.0.id', $matching->getKey())));
    });

    it('rejects an invalid activity filter', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.tags.index', [
            'team' => $team->getKey(),
            'filter' => ['is_active' => 'not-a-boolean'],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.is_active']);
    });
});

describe('create', function (): void {
    it('renders the create tag modal', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.create', ['team' => $team->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/create'));
    });
});

describe('store', function (): void {
    it('creates a tag for the team', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getKey()]), tagPayload())
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $tag = Tag::query()->sole();
        expect($tag->name)->toBe('Comida')
            ->and($tag->is_active)->toBeTrue()
            ->and($tag->team->is($team))->toBeTrue();
    });

    it('rejects a duplicate name ignoring case', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getKey()]), tagPayload([
                'name' => 'comida',
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('allows the same name on another team', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for(Team::factory())->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getKey()]), tagPayload())
            ->assertRedirect();

        expect($team->tags()->where('name', 'Comida')->count())->toBe(1);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('rejects a name that is not a string', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.tags.store', ['team' => $team->getKey()]), tagPayload([
                'name' => ['Comida'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);

        expect(Tag::query()->count())->toBe(0);
    });
});

describe('edit', function (): void {
    it('renders the edit tag modal', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.tags.edit', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/tags/edit')
                ->where('tag.id', $tag->getKey()));
    });

    it('returns 404 when the tag belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.tags.edit', [
            'team' => $team->getKey(),
            'tag' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Old']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => 'New',
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->name)->toBe('New');
    });

    it('allows keeping the current name', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload())
            ->assertRedirect();

        expect($tag->fresh()?->name)->toBe('Comida');
    });

    it('rejects a duplicate name ignoring case', function (): void {
        [$user, $team] = actingMember();
        Tag::factory()->for($team)->create(['name' => 'Comida']);
        $tag = Tag::factory()->for($team)->create(['name' => 'Salidas']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => 'comida',
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('returns 404 when updating a tag from another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Tag::factory()->for(Team::factory())->create();

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getKey(),
            'tag' => $foreign->getKey(),
        ]), tagPayload())->assertNotFound();
    });

    it('rejects a name that is not a string', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.update', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]), tagPayload([
                'name' => ['Salidas'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['name']);

        expect($tag->fresh()?->name)->toBe('Comida');
    });
});

describe('activate', function (): void {
    it('activates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.activate', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->is_active)->toBeTrue();
    });
});

describe('deactivate', function (): void {
    it('deactivates the tag', function (): void {
        [$user, $team] = actingMember();
        $tag = Tag::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.tags.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.tags.deactivate', [
                'team' => $team->getKey(),
                'tag' => $tag->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($tag->fresh()?->is_active)->toBeFalse();
    });
});
