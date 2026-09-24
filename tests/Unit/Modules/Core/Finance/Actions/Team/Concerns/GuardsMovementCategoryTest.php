<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsMovementCategory;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\Concerns\GuardsMovementCategory.
 */

/**
 * An object that guards categories the way the movement
 * Actions do, exposing the guard to the test.
 */
function movementCategoryGuard(): object
{
    return new class
    {
        use GuardsMovementCategory;

        /**
         * Guard the attributes against the given current type.
         *
         * @param  array<string, mixed>  $attributes
         */
        public function guard(array $attributes, ?TransactionType $currentType = null): void
        {
            $this->guardCategory($attributes, $currentType);
        }
    };
}

it('leaves the category unchecked when no type is known', function (array $attributes): void {
    expect(fn () => movementCategoryGuard()->guard($attributes))->not->toThrow(Exception::class);
})->with([
    'no type' => [['category' => Category::Salary->value]],
    'an unknown type' => [['category' => Category::Salary->value, 'type' => 'bogus']],
    'no type and no category' => [[]],
]);

it('holds the category to the current type when the attributes name none', function (): void {
    movementCategoryGuard()->guard(['category' => Category::Salary->value], TransactionType::Expense);
})->throws(Exception::class, 'The category salary does not belong to a movement of type expense.');
