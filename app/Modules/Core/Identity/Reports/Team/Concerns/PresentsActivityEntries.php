<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Reports\Team\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads a team's activity entries for display: who caused
 * each one, what it is about, which fields it changed and the
 * names the keys it recorded point at. The activity pages and
 * the MCP activity tools present entries the same way.
 */
trait PresentsActivityEntries
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

        // MySQL stores JSON objects with their keys reordered, so
        // the fields are sorted here to read the same everywhere.
        $fields = array_keys($old + $new);
        sort($fields);

        return array_map(fn (string $field): array => [
            'field' => $field,
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
            'old_label' => $this->label($field, $old[$field] ?? null, $names),
            'new_label' => $this->label($field, $new[$field] ?? null, $names),
        ], $fields);
    }

    /**
     * Get every row the action that wrote the entry produced, in
     * the order it wrote them. An entry written outside a batch
     * stands alone.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity>
     */
    protected function batch(Team $team, Activity $activity): Collection
    {
        if ($activity->batch_uuid === null) {
            return $activity->newCollection([$activity->load('causer', 'subject')]);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity> $rows */
        $rows = $team->activities()
            ->where('batch_uuid', $activity->batch_uuid)
            ->with('causer', 'subject')
            ->orderBy(new Activity()->getKeyName())
            ->get();

        return $rows;
    }

    /**
     * Get the team's members, as the member filter lists them and
     * the causer of an entry is matched against them.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User>
     */
    protected function members(Team $team): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\User> $members */
        $members = $team->users()
            ->orderBy('name')
            ->get([
                new User()->qualifyColumn(new User()->getKeyName()),
                new User()->qualifyColumn('name'),
            ]);

        return $members;
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

        if (in_array($subject, [ActivitySubject::Account, ActivitySubject::Tag, ActivitySubject::Team], true)) {
            return $named('name');
        }

        // A budget period has no name of its own, so it goes
        // by the tag it belongs to, and by what it was set
        // at once the period is gone.
        if ($subject === ActivitySubject::Budget) {
            return $this->budgetLabel($activity, $stored, $names);
        }

        if ($subject === ActivitySubject::Invitation) {
            return $named('email');
        }

        if ($subject === ActivitySubject::Member) {
            return is_string($name = $activity->subject?->getAttribute('name')) ? $name : null;
        }

        if ($subject === ActivitySubject::Subscription) {
            return ActivitySubject::Subscription->getLabel();
        }

        if ($subject === ActivitySubject::Transaction || $subject === ActivitySubject::Transfer) {
            return $named('description') ?? $this->amountLabel($stored, $names);
        }

        return null;
    }

    /**
     * Name a budget period by the category or tag it follows,
     * falling back to the amount it carried once the period
     * is gone.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, array<string, string>>  $names
     */
    protected function budgetLabel(Activity $activity, array $stored, array $names): ?string
    {
        $subject = $activity->subject;

        if ($subject instanceof BudgetPeriod) {
            $target = $subject->target();

            return $target instanceof Category ? $target->getLabel() : $target?->name;
        }

        return $this->amountLabel($stored, $names);
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
