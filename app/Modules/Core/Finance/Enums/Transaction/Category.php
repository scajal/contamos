<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Transaction;

use Filament\Support\Contracts\HasLabel;

enum Category: string implements HasLabel
{
    /**
     * Represents going out, hobbies, streaming and the
     * things done for fun.
     */
    case Entertainment = 'entertainment';

    /**
     * Represents restaurants, delivery and bars.
     */
    case EatingOut = 'eating_out';

    /**
     * Represents schooling, courses, books and training.
     */
    case Education = 'education';

    /**
     * Represents invoices, side work and a business
     * one runs.
     */
    case Freelance = 'freelance';

    /**
     * Represents presents given and money donated.
     */
    case GiftsAndDonations = 'gifts_and_donations';

    /**
     * Represents money received as a present.
     */
    case GiftsReceived = 'gifts_received';

    /**
     * Represents the supermarket, the corner shop and
     * the bakery.
     */
    case Groceries = 'groceries';

    /**
     * Represents doctors, pharmacy and therapy.
     */
    case Health = 'health';

    /**
     * Represents rent, mortgage, building fees, repairs
     * and household help.
     */
    case Housing = 'housing';

    /**
     * Represents health plans and the insurance on the
     * car, the home and the rest.
     */
    case Insurance = 'insurance';

    /**
     * Represents interest, dividends and fund yields.
     */
    case InvestmentReturns = 'investment_returns';

    /**
     * Represents childcare, school fees and support to
     * relatives.
     */
    case KidsAndFamily = 'kids_and_family';

    /**
     * Represents a movement that fits none of the
     * other categories.
     */
    case Other = 'other';

    /**
     * Represents hair, cosmetics and the gym.
     */
    case PersonalCare = 'personal_care';

    /**
     * Represents what the pets eat and the vet.
     */
    case Pets = 'pets';

    /**
     * Represents returns, insurance payouts and money
     * paid back for something advanced earlier.
     */
    case Refunds = 'refunds';

    /**
     * Represents money earned as an employee.
     */
    case Salary = 'salary';

    /**
     * Represents clothes, devices and home goods.
     */
    case Shopping = 'shopping';

    /**
     * Represents software and the services billed month
     * after month.
     */
    case Subscriptions = 'subscriptions';

    /**
     * Represents taxes, professional dues, bank fees
     * and interest paid.
     */
    case TaxesAndFees = 'taxes_and_fees';

    /**
     * Represents fuel, transit, taxis, parking and
     * keeping a vehicle on the road.
     */
    case Transport = 'transport';

    /**
     * Represents trips, lodging and tickets away
     * from home.
     */
    case Travel = 'travel';

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
            self::Entertainment => trans('Entertainment'),
            self::EatingOut => trans('Eating out'),
            self::Education => trans('Education'),
            self::Freelance => trans('Freelance & business'),
            self::GiftsAndDonations => trans('Gifts & donations'),
            self::GiftsReceived => trans('Gifts received'),
            self::Groceries => trans('Groceries'),
            self::Health => trans('Health'),
            self::Housing => trans('Housing'),
            self::Insurance => trans('Insurance'),
            self::InvestmentReturns => trans('Investment returns'),
            self::KidsAndFamily => trans('Kids & family'),
            self::Other => trans('Other'),
            self::PersonalCare => trans('Personal care'),
            self::Pets => trans('Pets'),
            self::Refunds => trans('Refunds & reimbursements'),
            self::Salary => trans('Salary'),
            self::Shopping => trans('Shopping'),
            self::Subscriptions => trans('Subscriptions'),
            self::TaxesAndFees => trans('Taxes & fees'),
            self::Transport => trans('Transport'),
            self::Travel => trans('Travel'),
            self::Utilities => trans('Utilities'),
        };
    }

    /**
     * Get the categories a movement of the given type can carry, in
     * the order the picker shows them: the roof and the bills
     * first, then the everyday, then the occasional, with the
     * money-about-money areas before the residue. Types that
     * are not recorded by hand, such as the legs of a
     * transfer, carry none.
     *
     * @return array<int, self>
     */
    public static function casesFor(TransactionType $type): array
    {
        return match ($type) {
            TransactionType::Expense => [
                self::Housing,
                self::Utilities,
                self::Groceries,
                self::EatingOut,
                self::Transport,
                self::Health,
                self::PersonalCare,
                self::Pets,
                self::KidsAndFamily,
                self::Education,
                self::Shopping,
                self::Entertainment,
                self::Travel,
                self::Subscriptions,
                self::Insurance,
                self::TaxesAndFees,
                self::GiftsAndDonations,
                self::Other,
            ],
            TransactionType::Income => [
                self::Salary,
                self::Freelance,
                self::InvestmentReturns,
                self::Refunds,
                self::GiftsReceived,
                self::Other,
            ],
            default => [],
        };
    }

    /**
     * Get every category a movement can be filed under, in the
     * order a breakdown lists them: the expense categories
     * first, then the income ones the expenses do not
     * already name, with Other closing the list as
     * the bucket for everything else.
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        // The two pickers overlap on Other, so a category is kept
        // the first time either of them names it.
        $ordered = array_values(array_unique([
            ...self::casesFor(TransactionType::Expense),
            ...self::casesFor(TransactionType::Income),
        ], SORT_REGULAR));

        // Other is a residue rather than an area, so it is read
        // last however the pickers happen to order it.
        $ordered = array_values(array_filter(
            $ordered,
            fn (self $category): bool => $category !== self::Other,
        ));

        return [...$ordered, self::Other];
    }

    /**
     * Get the name of the lucide icon that stands
     * for the category.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Entertainment => 'ticket',
            self::EatingOut => 'utensils',
            self::Education => 'graduation-cap',
            self::Freelance => 'briefcase',
            self::GiftsAndDonations => 'heart-handshake',
            self::GiftsReceived => 'gift',
            self::Groceries => 'shopping-cart',
            self::Health => 'heart-pulse',
            self::Housing => 'house',
            self::Insurance => 'shield-check',
            self::InvestmentReturns => 'trending-up',
            self::KidsAndFamily => 'baby',
            self::Other => 'ellipsis',
            self::PersonalCare => 'sparkles',
            self::Pets => 'paw-print',
            self::Refunds => 'rotate-ccw',
            self::Salary => 'wallet',
            self::Shopping => 'shopping-bag',
            self::Subscriptions => 'repeat',
            self::TaxesAndFees => 'landmark',
            self::Transport => 'bus',
            self::Travel => 'plane',
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
