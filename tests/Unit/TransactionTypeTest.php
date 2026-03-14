<?php

use App\Enums\TransactionType;

test('transaction type enum exposes supported types', function () {
    expect(TransactionType::cases())
        ->toHaveCount(3)
        ->and(TransactionType::Expense->value)->toBe('expense')
        ->and(TransactionType::Income->value)->toBe('income')
        ->and(TransactionType::Transfer->value)->toBe('transfer');
});

test('transaction type enum knows whether it uses categories', function () {
    expect(TransactionType::Expense->usesCategory())->toBeTrue()
        ->and(TransactionType::Income->usesCategory())->toBeTrue()
        ->and(TransactionType::Transfer->usesCategory())->toBeFalse();
});
