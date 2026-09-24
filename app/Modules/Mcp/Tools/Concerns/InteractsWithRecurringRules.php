<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns\ValidatesRecurringRules;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;

/**
 * Shared ground of the recurring rule tools: the arguments a rule
 * is written with, their translation into the attributes the rule
 * Actions expect, validated by the same rules the web uses, and
 * how a rule is presented back. Tools using it also use
 * \App\Modules\Mcp\Tools\Concerns\NormalizesInputs and
 * \App\Modules\Mcp\Tools\Concerns\PresentsRecords.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait InteractsWithRecurringRules
{
    use ValidatesRecurringRules;

    /**
     * The template and schedule arguments a rule takes a value
     * for as is.
     *
     * @var array<int, string>
     */
    protected array $recurringRuleFields = [
        'amount',
        'category',
        'description',
        'ends_on',
        'frequency',
        'interval',
        'starts_on',
        'time_of_day',
        'timezone',
        'type',
    ];

    /**
     * Get the schema of a rule's template and schedule arguments,
     * all required to create one and all optional to update one.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function recurringRuleSchema(JsonSchema $schema, bool $creating): array
    {
        $fields = [
            'account' => $schema->string()
                ->description('The ULID of the account every generated transaction is recorded on.'),
            'currency' => $schema->string()
                ->description("The ISO code of one of the account's currencies. Inferred when the account holds only one."),
            'amount' => $schema->string()
                ->description('The amount of each occurrence, a positive decimal string in major units, such as "25.50".'),
            'type' => $schema->string()
                ->enum(array_column(TransactionType::recordableCases(), 'value'))
                ->description('Whether each occurrence is an expense or an income.'),
            'category' => $schema->string()
                ->enum(array_column(Category::cases(), 'value'))
                ->description('The category stamped on each occurrence; it must be one the type offers.'),
            'frequency' => $schema->string()
                ->enum(array_column(Frequency::cases(), 'value'))
                ->description('How often the rule repeats, counted in `interval` units.'),
            'interval' => $schema->integer()
                ->min(1)
                ->max(99)
                ->description('Repeat every this many units of `frequency` (1 to 99).'.($creating ? ' Defaults to 1.' : '')),
            'starts_on' => $schema->string()
                ->description('The date of the first occurrence ("YYYY-MM-DD"), today or later. Monthly and yearly rules repeat on this day.'),
            'ends_on' => $schema->string()
                ->nullable()
                ->description('The last date an occurrence may fall on ("YYYY-MM-DD"), or null to repeat forever.'),
            'time_of_day' => $schema->string()
                ->description('The local time each occurrence is recorded at ("HH:MM").'),
            'timezone' => $schema->string()
                ->description('The IANA timezone `time_of_day` and the due dates are read in, such as "America/Montevideo".'),
            'description' => $schema->string()
                ->nullable()
                ->description('The description copied onto each occurrence.'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description("Tag ids or exact names (case-insensitive) of the team's active tags. ".($creating ? 'Leave out for none.' : "Replaces the rule's tags; leave out to keep them.")),
        ];

        // Creating a rule needs the whole template and schedule.
        if ($creating) {
            foreach (['account', 'amount', 'type', 'frequency', 'starts_on', 'time_of_day', 'timezone'] as $required) {
                $fields[$required] = $fields[$required]->required();
            }
        }

        return $fields;
    }

    /**
     * Get the schema of the `rule` argument naming an existing
     * rule of the team.
     *
     * @return array{rule: \Illuminate\JsonSchema\Types\Type}
     */
    protected function ruleSchema(JsonSchema $schema): array
    {
        return [
            'rule' => $schema->string()
                ->description('The ULID of the recurring rule, as listed by `list_recurring_rules`.')
                ->required(),
        ];
    }

    /**
     * Find the team's rule the call names.
     */
    protected function findRule(Team $team, Request $request): RecurringRule
    {
        /** @var \App\Modules\Core\Finance\Models\Team\RecurringRule $rule */
        $rule = $this->findOrFail($team->recurringRules(), $request->get('rule'), 'recurring rule');

        return $rule;
    }

    /**
     * Translate the call's arguments into the rule attributes and
     * tag ids the Actions take, over the rule's current values
     * when one is being updated, and validate them with the web's
     * own rules.
     *
     * @return array{attributes: array<string, mixed>, tag_ids: array<int, string>}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function recurringRuleInput(Team $team, Request $request, ?RecurringRule $rule = null): array
    {
        $arguments = $request->all();

        $accountKey = new RecurringRule()->account()->getForeignKeyName();
        $currencyKey = new RecurringRule()->currency()->getForeignKeyName();

        // Start from what the rule holds, so an update only
        // changes what the call names.
        $input = $rule instanceof RecurringRule ? $this->currentRuleInput($rule) : ['interval' => 1];

        // Copy the arguments taken as they are.
        foreach ($this->recurringRuleFields as $field) {
            if (array_key_exists($field, $arguments)) {
                $input[$field] = $field === 'amount' && (is_int($arguments[$field]) || is_float($arguments[$field]))
                    ? (string) $arguments[$field]
                    : $arguments[$field];
            }
        }

        // A new rule must name its account.
        if (! $rule instanceof RecurringRule) {
            $request->validate(['account' => ['required', 'string']]);
        }

        // Resolve the account and its currency, inferring the
        // currency when the account holds only one.
        if (array_key_exists('account', $arguments) || array_key_exists('currency', $arguments)) {
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = array_key_exists('account', $arguments) || ! $rule instanceof RecurringRule
                ? $this->findOrFail($team->accounts(), $arguments['account'] ?? null, 'account')
                : $rule->account;

            $input[$accountKey] = $account->getKey();
            $input[$currencyKey] = $this->accountCurrency($account, $arguments['currency'] ?? null)->getKey();
        }

        // Resolve tags by id or name; an update keeps the rule's
        // tags unless the call replaces them.
        /** @var array<int, string> $attachedTagIds */
        $attachedTagIds = $rule?->tags->modelKeys() ?? [];

        $input['tag_ids'] = array_key_exists('tags', $arguments)
            ? $this->tagIds($team, $arguments['tags'], $attachedTagIds)
            : $attachedTagIds;

        // Validate with the rules the web forms apply.
        $input = $this->normalizeRecurringRuleInput($input);

        $validated = Validator::validate($input, $this->recurringRuleRules($team, $input, $rule), [], [
            $accountKey => 'account',
            $currencyKey => 'currency',
            'tag_ids' => 'tags',
        ]);

        /** @var array<int, string> $tagIds */
        $tagIds = array_values(array_filter(Arr::wrap($validated['tag_ids'] ?? []), is_string(...)));

        return [
            'attributes' => $this->validatedOnly($validated, $this->recurringRuleAttributeKeys()),
            'tag_ids' => $tagIds,
        ];
    }

    /**
     * Get the rule's current template and schedule in the shape
     * the validation rules read.
     *
     * @return array<string, mixed>
     */
    protected function currentRuleInput(RecurringRule $rule): array
    {
        return [
            $rule->account()->getForeignKeyName() => $rule->account_id,
            'amount' => $this->amount($rule->amount, $rule->decimal_places),
            'category' => $rule->category?->value,
            $rule->currency()->getForeignKeyName() => $rule->currency_id,
            'description' => $rule->description,
            'ends_on' => $rule->ends_on?->toDateString(),
            'frequency' => $rule->frequency->value,
            'interval' => $rule->interval,
            'starts_on' => $rule->starts_on->toDateString(),
            'time_of_day' => $rule->time_of_day,
            'timezone' => $rule->timezone,
            'type' => $rule->type->value,
        ];
    }

    /**
     * Present a rule with its template, its schedule, when it
     * fires next and whether it runs.
     *
     * @return array<string, mixed>
     */
    protected function presentRecurringRule(RecurringRule $rule): array
    {
        $rule->loadMissing('account', 'currency', 'tags');

        return [
            'id' => $rule->getKey(),
            'account' => [
                'id' => $rule->account->getKey(),
                'name' => $rule->account->name,
            ],
            'currency' => $rule->currency->code,
            'amount' => $this->amount($rule->amount, $rule->decimal_places),
            'type' => $rule->type->value,
            'category' => $rule->category?->value,
            'description' => $rule->description,
            'frequency' => $rule->frequency->value,
            'interval' => $rule->interval,
            'starts_on' => $rule->starts_on->toDateString(),
            'ends_on' => $rule->ends_on?->toDateString(),
            'time_of_day' => $rule->time_of_day,
            'timezone' => $rule->timezone,
            'tags' => $rule->tags
                ->map(fn (Tag $tag): array => ['id' => $tag->getKey(), 'name' => $tag->name])
                ->values()
                ->all(),
            'status' => $rule->status->value,
            'next_due_date' => $rule->next_due_date?->toDateString(),
            'last_generated_due_date' => $rule->last_generated_due_date?->toDateString(),
            'failure_reason' => $rule->failure_reason,
        ];
    }
}
