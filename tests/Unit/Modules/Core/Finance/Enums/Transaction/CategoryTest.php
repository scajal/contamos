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
    'education' => [Category::Education, 'Education'],
    'family and pets' => [Category::FamilyAndPets, 'Family & pets'],
    'financial' => [Category::Financial, 'Financial'],
    'food' => [Category::Food, 'Food'],
    'freelance' => [Category::Freelance, 'Freelance'],
    'gift' => [Category::Gift, 'Gift'],
    'gifts and donations' => [Category::GiftsAndDonations, 'Gifts & donations'],
    'health' => [Category::Health, 'Health'],
    'housing' => [Category::Housing, 'Housing'],
    'investments' => [Category::Investments, 'Investments'],
    'leisure and travel' => [Category::LeisureAndTravel, 'Leisure & travel'],
    'other' => [Category::Other, 'Other'],
    'reimbursement' => [Category::Reimbursement, 'Reimbursement'],
    'salary' => [Category::Salary, 'Salary'],
    'shopping' => [Category::Shopping, 'Shopping'],
    'subscriptions and services' => [Category::SubscriptionsAndServices, 'Subscriptions & services'],
    'transport' => [Category::Transport, 'Transport'],
    'utilities' => [Category::Utilities, 'Utilities'],
]);

it('lists the expense categories in the designed order', function (): void {
    expect(array_column(Category::casesFor(TransactionType::Expense), 'value'))->toBe([
        'housing',
        'utilities',
        'subscriptions_and_services',
        'food',
        'transport',
        'health',
        'education',
        'leisure_and_travel',
        'shopping',
        'gifts_and_donations',
        'financial',
        'family_and_pets',
        'other',
    ]);
});

it('lists the income categories in the designed order', function (): void {
    expect(array_column(Category::casesFor(TransactionType::Income), 'value'))->toBe([
        'salary',
        'freelance',
        'investments',
        'reimbursement',
        'gift',
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
