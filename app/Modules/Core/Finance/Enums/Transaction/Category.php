<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Transaction;

use Filament\Support\Contracts\HasLabel;

enum Category: string implements HasLabel
{
    /**
     * Represents schooling, courses and training.
     */
    case Education = 'education';

    /**
     * Represents what a family and the pets in it cost.
     */
    case FamilyAndPets = 'family_and_pets';

    /**
     * Represents insurance, taxes, interest and bank fees.
     */
    case Financial = 'financial';

    /**
     * Represents groceries and eating out.
     */
    case Food = 'food';

    /**
     * Represents money earned working for oneself.
     */
    case Freelance = 'freelance';

    /**
     * Represents money received as a present.
     */
    case Gift = 'gift';

    /**
     * Represents presents given and money donated.
     */
    case GiftsAndDonations = 'gifts_and_donations';

    /**
     * Represents doctors, medicines and health insurance.
     */
    case Health = 'health';

    /**
     * Represents rent, mortgage and home maintenance.
     */
    case Housing = 'housing';

    /**
     * Represents returns on invested money.
     */
    case Investments = 'investments';

    /**
     * Represents going out, hobbies and holidays.
     */
    case LeisureAndTravel = 'leisure_and_travel';

    /**
     * Represents a movement that fits none of the
     * other categories.
     */
    case Other = 'other';

    /**
     * Represents money paid back for something
     * advanced earlier.
     */
    case Reimbursement = 'reimbursement';

    /**
     * Represents money earned as an employee.
     */
    case Salary = 'salary';

    /**
     * Represents clothes, devices and everything else
     * bought for oneself.
     */
    case Shopping = 'shopping';

    /**
     * Represents recurring services billed
     * month after month.
     */
    case SubscriptionsAndServices = 'subscriptions_and_services';

    /**
     * Represents fuel, tickets and keeping a
     * vehicle on the road.
     */
    case Transport = 'transport';

    /**
     * Represents electricity, water, gas, internet
     * and phone bills.
     */
    case Utilities = 'utilities';

    /**
     * Get the label for the category.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Education => trans('Education'),
            self::FamilyAndPets => trans('Family & pets'),
            self::Financial => trans('Financial'),
            self::Food => trans('Food'),
            self::Freelance => trans('Freelance'),
            self::Gift => trans('Gift'),
            self::GiftsAndDonations => trans('Gifts & donations'),
            self::Health => trans('Health'),
            self::Housing => trans('Housing'),
            self::Investments => trans('Investments'),
            self::LeisureAndTravel => trans('Leisure & travel'),
            self::Other => trans('Other'),
            self::Reimbursement => trans('Reimbursement'),
            self::Salary => trans('Salary'),
            self::Shopping => trans('Shopping'),
            self::SubscriptionsAndServices => trans('Subscriptions & services'),
            self::Transport => trans('Transport'),
            self::Utilities => trans('Utilities'),
        };
    }

    /**
     * Get the categories a movement of the given type can carry, in
     * the order the picker shows them. Types that are not recorded
     * by hand, such as the legs of a transfer, carry none.
     *
     * @return array<int, self>
     */
    public static function casesFor(TransactionType $type): array
    {
        return match ($type) {
            TransactionType::Expense => [
                self::Housing,
                self::Utilities,
                self::SubscriptionsAndServices,
                self::Food,
                self::Transport,
                self::Health,
                self::Education,
                self::LeisureAndTravel,
                self::Shopping,
                self::GiftsAndDonations,
                self::Financial,
                self::FamilyAndPets,
                self::Other,
            ],
            TransactionType::Income => [
                self::Salary,
                self::Freelance,
                self::Investments,
                self::Reimbursement,
                self::Gift,
                self::Other,
            ],
            default => [],
        };
    }

    /**
     * Get the name of the lucide icon that stands
     * for the category.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Education => 'graduation-cap',
            self::FamilyAndPets => 'paw-print',
            self::Financial => 'landmark',
            self::Food => 'utensils',
            self::Freelance => 'briefcase',
            self::Gift => 'gift',
            self::GiftsAndDonations => 'heart-handshake',
            self::Health => 'heart-pulse',
            self::Housing => 'house',
            self::Investments => 'trending-up',
            self::LeisureAndTravel => 'plane',
            self::Other => 'ellipsis',
            self::Reimbursement => 'rotate-ccw',
            self::Salary => 'wallet',
            self::Shopping => 'shopping-bag',
            self::SubscriptionsAndServices => 'repeat',
            self::Transport => 'bus',
            self::Utilities => 'plug',
        };
    }

    /**
     * Determine whether the category can be recorded
     * on a movement of the given type.
     */
    public function allowedFor(TransactionType $type): bool
    {
        return in_array($this, self::casesFor($type), true);
    }
}
