<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\Team\Activity;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of record a team's activity entries are about. The
 * value is the alias the timeline filters by, so the page never
 * sees a class name.
 */
enum ActivitySubject: string implements HasLabel
{
    /**
     * Represents an account of the team.
     */
    case Account = 'account';

    /**
     * Represents one dated stretch of a tag's budget.
     */
    case Budget = 'budget';

    /**
     * Represents an invitation to join the team.
     */
    case Invitation = 'invitation';

    /**
     * Represents somebody's membership of the team.
     */
    case Member = 'member';

    /**
     * Represents the team's subscription.
     */
    case Subscription = 'subscription';

    /**
     * Represents a tag of the team.
     */
    case Tag = 'tag';

    /**
     * Represents the team itself.
     */
    case Team = 'team';

    /**
     * Represents a transaction of the team.
     */
    case Transaction = 'transaction';

    /**
     * Represents a transfer between the team's accounts.
     */
    case Transfer = 'transfer';

    /**
     * Get the subject matching the given morph type, if
     * the type is one the timeline knows.
     */
    public static function forType(?string $type): ?self
    {
        foreach (self::cases() as $subject) {
            if ($subject->modelClass() === $type) {
                return $subject;
            }
        }

        return null;
    }

    /**
     * Get the label for the subject.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Account => trans('Account'),
            self::Budget => trans('Budget'),
            self::Invitation => trans('Invitation'),
            self::Member => trans('Member'),
            self::Subscription => trans('Subscription'),
            self::Tag => trans('Tag'),
            self::Team => trans('Team'),
            self::Transaction => trans('Transaction'),
            self::Transfer => trans('Transfer'),
        };
    }

    /**
     * Get the model the subject is stored as.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Account => Account::class,
            self::Budget => BudgetPeriod::class,
            self::Invitation => TeamInvitation::class,
            self::Member => User::class,
            self::Subscription => Subscription::class,
            self::Tag => Tag::class,
            self::Team => Team::class,
            self::Transaction => Transaction::class,
            self::Transfer => Transfer::class,
        };
    }

    /**
     * Shape every subject as the options the record type
     * filter renders, in case order.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $subject): array => [
            'label' => $subject->getLabel(),
            'value' => $subject->value,
        ], self::cases());
    }
}
