<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The single source of a recurring rule's template and schedule
 * validation, shared by the web Form Requests and the MCP tools.
 * Every method takes the submitted input as a plain array, so it
 * never depends on where the input came from.
 */
trait ValidatesRecurringRules
{
    /**
     * Normalise the money and the time of day, which the form
     * submits as HH:mm, before validation reads them.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    protected function normalizeRecurringRuleInput(array $input): array
    {
        // The amount may use a comma as its decimal point.
        $input['amount'] = new Amount()->normalize($input['amount'] ?? null);

        // A time of day without seconds gains them.
        if (is_string($timeOfDay = $input['time_of_day'] ?? null) && preg_match('/^\d{2}:\d{2}$/', $timeOfDay) === 1) {
            $input['time_of_day'] = "{$timeOfDay}:00";
        }

        return $input;
    }

    /**
     * Get the validation rules of a rule's template and schedule,
     * for a new rule or for the given one being updated.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function recurringRuleRules(Team $team, array $input, ?RecurringRule $rule = null): array
    {
        $accountKey = new RecurringRule()->account()->getForeignKeyName();
        $currencyKey = new RecurringRule()->currency()->getForeignKeyName();

        // The account and currency submitted, when they exist.
        $account = $this->recurringRuleAccount($team, $input[$accountKey] ?? null);
        $currency = $this->recurringRuleCurrency($input[$currencyKey] ?? null);

        /** @var array<int, string> $attachedTagIds */
        $attachedTagIds = $rule?->tags->modelKeys() ?? [];

        return [
            $accountKey => [
                'required',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'amount' => [
                'required',
                $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gt:0',
            ],
            'category' => $this->recurringRuleCategoryRules($input['type'] ?? null),
            $currencyKey => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in($account?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'ends_on' => [
                'nullable',
                'date',
                'after_or_equal:starts_on',
            ],
            'frequency' => [
                'required',
                Rule::in(array_column(Frequency::cases(), 'value')),
            ],
            'interval' => [
                'required',
                'integer',
                'between:1,99',
            ],
            'starts_on' => [
                'required',
                'date',
                $this->recurringRuleStartsOnRule($rule),
            ],
            'time_of_day' => [
                'required',
                'date_format:H:i:s',
            ],
            'timezone' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Instant::isZone($value)) {
                        $fail(trans('The timezone is invalid.'));
                    }
                },
            ],
            'tag_ids' => [
                'sometimes',
                'array',
            ],
            'tag_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(Tag::class, new Tag()->getKeyName())
                    ->where(new Tag()->team()->getForeignKeyName(), $team->getKey()),
                $this->recurringRuleTagIsSelectable($team, $attachedTagIds),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(array_column(TransactionType::recordableCases(), 'value')),
            ],
        ];
    }

    /**
     * Get the keys of the validated input the rule Actions write.
     *
     * @return array<int, string>
     */
    protected function recurringRuleAttributeKeys(): array
    {
        return [
            new RecurringRule()->account()->getForeignKeyName(),
            'amount',
            'category',
            new RecurringRule()->currency()->getForeignKeyName(),
            'description',
            'ends_on',
            'frequency',
            'interval',
            'starts_on',
            'time_of_day',
            'timezone',
            'type',
        ];
    }

    /**
     * Get the rules for the category the rule stamps on every
     * transaction it generates, restricted to the list its
     * type offers.
     *
     * @return array<int, \Illuminate\Validation\Rules\Enum|string>
     */
    protected function recurringRuleCategoryRules(mixed $type): array
    {
        $type = is_string($type) ? TransactionType::tryFrom($type) : null;

        $cases = $type instanceof TransactionType ? Category::casesFor($type) : [];

        // A type that is never recorded by hand, such as an
        // opening balance, carries no category at all.
        if ($cases === []) {
            return ['prohibited'];
        }

        return [
            'required',
            'string',
            Rule::enum(Category::class)->only($cases),
        ];
    }

    /**
     * A new rule may not be backdated, so turning it on never
     * floods the ledger. A rule that already started keeps the
     * start date it was created with, the anchor its past
     * occurrences were generated from; only a start date being
     * moved must not be backdated.
     */
    protected function recurringRuleStartsOnRule(?RecurringRule $rule): Closure|string
    {
        // A new rule starts today at the earliest.
        if (! $rule instanceof RecurringRule) {
            return 'after_or_equal:today';
        }

        $current = $rule->starts_on;

        return function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if (is_string($value) && $current->isSameDay(CarbonImmutable::parse($value))) {
                return;
            }

            if (is_string($value) && CarbonImmutable::parse($value)->startOfDay()->greaterThanOrEqualTo(CarbonImmutable::now()->startOfDay())) {
                return;
            }

            $fail(trans('validation.after_or_equal', ['attribute' => $attribute, 'date' => trans('today')]));
        };
    }

    /**
     * Only a tag the rule already carries may be inactive: a
     * member keeps categorising the rows a deactivated tag still
     * describes, but cannot newly pick it.
     *
     * @param  array<int, string>  $attachedTagIds
     */
    protected function recurringRuleTagIsSelectable(Team $team, array $attachedTagIds): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team, $attachedTagIds): void {
            if (! is_string($value)) {
                return;
            }

            $tag = $team->tags()->whereKey($value)->first();

            if ($tag === null || $tag->is_active || in_array($value, $attachedTagIds, true)) {
                return;
            }

            $fail(trans('The selected tag is inactive.'));
        };
    }

    /**
     * Get the team's account the input names, if any.
     */
    protected function recurringRuleAccount(Team $team, mixed $accountId): ?Account
    {
        if (! is_string($accountId)) {
            return null;
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = Account::query()
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->find($accountId);

        return $account;
    }

    /**
     * Get the currency the input names, if any.
     */
    protected function recurringRuleCurrency(mixed $currencyId): ?Currency
    {
        if (! is_string($currencyId)) {
            return null;
        }

        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()->find($currencyId);

        return $currency;
    }
}
