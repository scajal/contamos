<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\Transaction\Category.
 */
it('translates the category label', function (Category $category, string $label): void {
    App::setLocale('en');

    expect($category->getLabel())->toBe($label);
})->with([
    'eating out' => [Category::EatingOut, 'Eating out'],
    'education' => [Category::Education, 'Education'],
    'entertainment' => [Category::Entertainment, 'Entertainment'],
    'freelance' => [Category::Freelance, 'Freelance & business'],
    'gifts and donations' => [Category::GiftsAndDonations, 'Gifts & donations'],
    'gifts received' => [Category::GiftsReceived, 'Gifts received'],
    'groceries' => [Category::Groceries, 'Groceries'],
    'health' => [Category::Health, 'Health'],
    'housing' => [Category::Housing, 'Housing'],
    'insurance' => [Category::Insurance, 'Insurance'],
    'investment returns' => [Category::InvestmentReturns, 'Investment returns'],
    'kids and family' => [Category::KidsAndFamily, 'Kids & family'],
    'other' => [Category::Other, 'Other'],
    'personal care' => [Category::PersonalCare, 'Personal care'],
    'pets' => [Category::Pets, 'Pets'],
    'refunds' => [Category::Refunds, 'Refunds & reimbursements'],
    'salary' => [Category::Salary, 'Salary'],
    'shopping' => [Category::Shopping, 'Shopping'],
    'subscriptions' => [Category::Subscriptions, 'Subscriptions'],
    'taxes and fees' => [Category::TaxesAndFees, 'Taxes & fees'],
    'transport' => [Category::Transport, 'Transport'],
    'travel' => [Category::Travel, 'Travel'],
    'utilities' => [Category::Utilities, 'Utilities'],
]);

it('lists the expense categories in the designed order', function (): void {
    expect(array_column(Category::casesFor(TransactionType::Expense), 'value'))->toBe([
        'housing',
        'utilities',
        'groceries',
        'eating_out',
        'transport',
        'health',
        'personal_care',
        'pets',
        'kids_and_family',
        'education',
        'shopping',
        'entertainment',
        'travel',
        'subscriptions',
        'insurance',
        'taxes_and_fees',
        'gifts_and_donations',
        'other',
    ]);
});

it('lists the income categories in the designed order', function (): void {
    expect(array_column(Category::casesFor(TransactionType::Income), 'value'))->toBe([
        'salary',
        'freelance',
        'investment_returns',
        'refunds',
        'gifts_received',
        'other',
    ]);
});

it('offers no category to a type that is not recorded by hand', function (TransactionType $type): void {
    expect(Category::casesFor($type))->toBe([]);
})->with([
    'opening balance' => [TransactionType::OpeningBalance],
    'transfer in' => [TransactionType::TransferIn],
    'transfer out' => [TransactionType::TransferOut],
]);

it('offers other last in both lists', function (TransactionType $type): void {
    $cases = Category::casesFor($type);

    expect(end($cases))->toBe(Category::Other);
})->with([
    'expense' => [TransactionType::Expense],
    'income' => [TransactionType::Income],
]);

it('knows which type a category belongs to', function (Category $category, TransactionType $type, bool $allowed): void {
    expect($category->allowedFor($type))->toBe($allowed);
})->with([
    'housing on an expense' => [Category::Housing, TransactionType::Expense, true],
    'housing on an income' => [Category::Housing, TransactionType::Income, false],
    'salary on an income' => [Category::Salary, TransactionType::Income, true],
    'salary on an expense' => [Category::Salary, TransactionType::Expense, false],
    'other on an expense' => [Category::Other, TransactionType::Expense, true],
    'other on an income' => [Category::Other, TransactionType::Income, true],
    'other on a transfer leg' => [Category::Other, TransactionType::TransferOut, false],
]);

it('names a lucide icon and a snake case value for every category', function (Category $category): void {
    expect($category->icon())->not->toBeEmpty()
        ->and($category->icon())->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')
        ->and($category->value)->toMatch('/^[a-z]+(_[a-z]+)*$/');
})->with(fn (): array => array_map(fn (Category $category): array => [$category], Category::cases()));
