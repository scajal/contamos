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
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared template and schedule validation for the recurring rule Store
 * and Update requests. The template half mirrors the transaction store
 * request, since a rule is the transaction it will generate.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesScheduleInputs
{
    /**
     * Normalise the money and the time of day, which the form submits
     * as HH:mm, before validation reads them.
     */
    protected function prepareScheduleForValidation(): void
    {
        $this->merge([
            'amount' => new Amount()->normalize($this->input('amount')),
        ]);

        if (is_string($timeOfDay = $this->input('time_of_day')) && preg_match('/^\d{2}:\d{2}$/', $timeOfDay) === 1) {
            $this->merge(['time_of_day' => "{$timeOfDay}:00"]);
        }
    }

    /**
     * Get the validation rules the rule's template and schedule share.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function scheduleRules(Team $team): array
    {
        return [
            new RecurringRule()->account()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'amount' => [
                'required',
                ($currency = $this->currency()) instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gt:0',
            ],
            'category' => $this->categoryRules(),
            new RecurringRule()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in($this->account($team)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
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
                $this->startsOnRule(),
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
                $this->tagIsSelectable($team),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(array_column(TransactionType::recordableCases(), 'value')),
            ],
        ];
    }

    /**
     * Get the rules for the category the rule stamps on every
     * transaction it generates, restricted to the list
     * its type offers.
     *
     * @return array<int, \Illuminate\Validation\Rules\Enum|string>
     */
    protected function categoryRules(): array
    {
        $type = $this->submittedType();

        $cases = $type instanceof TransactionType ? Category::casesFor($type) : [];

        // A type that is never recorded by hand, such as an opening
        // balance, carries no category at all.
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
     * Get the type the request is recording the rule with.
     */
    protected function submittedType(): ?TransactionType
    {
        $type = $this->input('type');

        return is_string($type) ? TransactionType::tryFrom($type) : null;
    }

    /**
     * Only a tag the rule already carries may be inactive: a member
     * keeps categorising the rows a deactivated tag still describes,
     * but cannot newly pick it.
     */
    protected function tagIsSelectable(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! is_string($value)) {
                return;
            }

            $tag = $team->tags()->whereKey($value)->first();

            if ($tag === null || $tag->is_active || in_array($value, $this->attachedTagIds(), true)) {
                return;
            }

            $fail(trans('The selected tag is inactive.'));
        };
    }

    /**
     * The tag keys the rule being validated already carries. A rule
     * that does not exist yet carries none.
     *
     * @return array<int, string>
     */
    protected function attachedTagIds(): array
    {
        return [];
    }

    /**
     * The start date may not be backdated, so turning a rule on never
     * floods the ledger with occurrences it never generated.
     */
    protected function startsOnRule(): ValidationRule|Closure|string
    {
        return 'after_or_equal:today';
    }

    /**
     * Get the account for the rule.
     */
    protected function account(Team $team): ?Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = Account::query()
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->find($this->input(new RecurringRule()->account()->getForeignKeyName()));

        return $account;
    }

    /**
     * Get the selected currency, if any.
     */
    protected function currency(): ?Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()
            ->find($this->input(new RecurringRule()->currency()->getForeignKeyName()));

        return $currency;
    }

    /**
     * Get the validated tag ids for the rule.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        /** @var array<int, mixed> $tagIds */
        $tagIds = $this->validated('tag_ids', []);

        return array_values(array_filter($tagIds, is_string(...)));
    }

    /**
     * Get the rule attributes the action writes.
     *
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        return $this->safe([
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
        ]);
    }
}
