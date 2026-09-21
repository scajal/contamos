<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Http\Requests\Team\Activity\IndexRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityController
{
    /**
     * The changed fields whose values are keys of another
     * record, mapped to the model that names them.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected const array REFERENCES = [
        'account_id' => Account::class,
        'currency_id' => Currency::class,
        'tags' => Tag::class,
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        $members = $team->users()
            ->orderBy('name')
            ->get([
                new User()->qualifyColumn(new User()->getKeyName()),
                new User()->qualifyColumn('name'),
            ]);

        return Inertia::render('identity/teams/activity/index', [
            'activities' => Inertia::scroll(
                fn (): LengthAwarePaginator => $this->entries($team, $members, QueryBuilder::for($team->activities(), $request)
                    ->allowedFilters(...$request->filters())
                    ->with('causer', 'subject')
                    ->orderBy(Activity::CREATED_AT, 'desc')
                    ->orderBy(new Activity()->getKeyName(), 'desc')
                    ->paginate(10)
                    ->withQueryString()),
            )->defer(),
            'filters' => $request->validated('filter', []),
            'members' => $members,
            'subjects' => ActivitySubject::options(),
        ]);
    }

    /**
     * Shape who an entry came from: somebody in the team, the
     * platform's support staff, an account that no longer
     * exists, or no one at all.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @return array{kind: string, name: string|null}
     */
    protected function causer(Collection $members, Activity $activity): array
    {
        // Nothing performed the change, so it came from the
        // platform itself.
        if ($activity->causer_id === null) {
            return ['kind' => 'system', 'name' => null];
        }

        // The user is gone, and their identity went with them.
        if (! ($causer = $activity->causer) instanceof User) {
            return ['kind' => 'former', 'name' => null];
        }

        // Somebody outside the team who may reach the
        // administration panel acted as support.
        if (! $members->contains(fn (User $member): bool => $member->is($causer)) && $causer->canAccessPanel(Filament::getPanel('administration'))) {
            return ['kind' => 'support', 'name' => null];
        }

        return ['kind' => 'member', 'name' => $causer->name];
    }

    /**
     * Shape the fields an entry changed, each with the value
     * before and after, plus the display name of whatever
     * record a reference points at.
     *
     * @param  array<string, array<string, string>>  $names
     * @return array<int, array<string, mixed>>
     */
    protected function changes(Activity $activity, array $names): array
    {
        $old = $this->side($activity, 'old');
        $new = $this->side($activity, 'attributes');

        return array_map(fn (string $field): array => [
            'field' => $field,
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
            'old_label' => $this->label($field, $old[$field] ?? null, $names),
            'new_label' => $this->label($field, $new[$field] ?? null, $names),
        ], array_keys($old + $new));
    }

    /**
     * Shape the page of entries the timeline renders.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>  $members
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, \App\Modules\Core\Identity\Models\Team\Activity>  $activities
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function entries(Team $team, Collection $members, LengthAwarePaginator $activities): LengthAwarePaginator
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity> $rows */
        $rows = $activities->getCollection();

        // The names every reference on the page points at, read
        // once per referenced model rather than once per row.
        $names = $this->references($rows);

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>> $entries */
        $entries = $activities->through(fn (Activity $activity): array => [
            'id' => $activity->getKey(),
            'batch_uuid' => $activity->batch_uuid,
            'causer' => $this->causer($members, $activity),
            'changes' => $this->changes($activity, $names),
            'created_at' => $activity->created_at?->toIso8601String(),
            'event' => $activity->event,
            'subject_id' => $activity->subject_id,
            'subject_label' => $this->subjectLabel($activity, $names),
            'subject_type' => ActivitySubject::forType($activity->subject_type)?->value,
        ]);

        return $entries;
    }

    /**
     * Get the display name a reference points at, falling back
     * to the raw key when the record it named is gone. A value
     * that names no record has no label.
     *
     * @param  array<string, array<string, string>>  $names
     * @return string|array<int, string>|null
     */
    protected function label(string $field, mixed $value, array $names): string|array|null
    {
        // A role names itself through the enum, with no record
        // to look up.
        if ($field === 'role') {
            return is_string($value) ? Role::tryFrom($value)?->getLabel() : null;
        }

        if ($value === null || ! isset($names[$field])) {
            return null;
        }

        // A list of keys resolves to a list of names.
        if (is_array($value)) {
            return array_values(array_map(fn (mixed $key): string => $this->named($names[$field], $key), $value));
        }

        return $this->named($names[$field], $value);
    }

    /**
     * Get the name stored for a key, or the key itself when
     * the record it points at no longer exists.
     *
     * @param  array<string, string>  $names
     */
    protected function named(array $names, mixed $key): string
    {
        $key = is_scalar($key) ? (string) $key : '';

        return $names[$key] ?? $key;
    }

    /**
     * Read the display names of every record the page's entries
     * refer to, one query per referenced model.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity>  $activities
     * @return array<string, array<string, string>>
     */
    protected function references(Collection $activities): array
    {
        $names = [];

        foreach (self::REFERENCES as $field => $model) {
            // Gather every key the page mentions for the field,
            // on either side of a change.
            $keys = [];

            foreach ($activities as $activity) {
                foreach (['old', 'attributes'] as $side) {
                    $value = $this->side($activity, $side)[$field] ?? null;

                    foreach (is_array($value) ? $value : [$value] as $key) {
                        if (is_string($key)) {
                            $keys[] = $key;
                        }
                    }
                }
            }

            if ($keys === []) {
                $names[$field] = [];

                continue;
            }

            /** @var array<string, string> $resolved */
            $resolved = $model::query()
                ->whereKey(array_unique($keys))
                ->pluck($model === Currency::class ? 'code' : 'name', new $model()->getKeyName())
                ->all();

            $names[$field] = $resolved;
        }

        return $names;
    }

    /**
     * Read one side of an entry's recorded change.
     *
     * @return array<string, mixed>
     */
    protected function side(Activity $activity, string $side): array
    {
        if (! is_array($values = $activity->attribute_changes?->get($side))) {
            return [];
        }

        $fields = [];

        foreach ($values as $field => $value) {
            $fields[(string) $field] = $value;
        }

        return $fields;
    }

    /**
     * Name the record an entry is about, from the record itself
     * while it exists and from the values the entry stored once
     * it is gone.
     *
     * @param  array<string, array<string, string>>  $names
     */
    protected function subjectLabel(Activity $activity, array $names): ?string
    {
        $subject = ActivitySubject::forType($activity->subject_type);

        // The values the entry recorded, whichever side holds
        // them, so a deleted record still names itself.
        $stored = $this->side($activity, 'attributes') + $this->side($activity, 'old');

        $named = function (string $attribute) use ($activity, $stored): ?string {
            // The record still exists, so it names itself.
            if (is_string($value = $activity->subject?->getAttribute($attribute))) {
                return $value;
            }

            return is_string($value = $stored[$attribute] ?? null) ? $value : null;
        };

        return match ($subject) {
            ActivitySubject::Account, ActivitySubject::Tag, ActivitySubject::Team => $named('name'),
            ActivitySubject::Invitation => $named('email'),
            ActivitySubject::Member => is_string($name = $activity->subject?->getAttribute('name')) ? $name : null,
            ActivitySubject::Subscription => ActivitySubject::Subscription->getLabel(),
            ActivitySubject::Transaction, ActivitySubject::Transfer => $named('description') ?? $this->amountLabel($stored, $names),
            null => null,
        };
    }

    /**
     * Name a movement that carries no description by what it
     * moved, so the entry still says which one it was.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, array<string, string>>  $names
     */
    protected function amountLabel(array $stored, array $names): ?string
    {
        if (! is_string($amount = $stored['amount'] ?? null)) {
            return null;
        }

        // Trim the padding the decimal cast adds, keeping every
        // digit that carries value.
        $amount = str_contains($amount, '.') ? rtrim(rtrim($amount, '0'), '.') : $amount;

        $code = $this->label('currency_id', $stored['currency_id'] ?? null, $names);

        return is_string($code) ? "{$amount} {$code}" : $amount;
    }
}
