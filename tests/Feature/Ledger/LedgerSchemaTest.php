<?php

use Illuminate\Support\Facades\Schema;

test('financial tracker tables contain the expected columns', function () {
    expect(Schema::hasTable('ledgers'))->toBeTrue();
    expect(Schema::hasColumns('ledgers', [
        'id',
        'user_id',
        'name',
        'currency_code',
        'uses_seeded_categories',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('account_types'))->toBeTrue();
    expect(Schema::hasColumns('account_types', [
        'id',
        'ledger_id',
        'name',
        'color',
        'position',
        'is_credit',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('accounts'))->toBeTrue();
    expect(Schema::hasColumns('accounts', [
        'id',
        'ledger_id',
        'account_type_id',
        'name',
        'initial_balance',
        'statement_day',
        'include_in_totals',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasColumns('categories', [
        'id',
        'ledger_id',
        'name',
        'transaction_type',
        'color',
        'icon',
        'position',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('payees'))->toBeTrue();
    expect(Schema::hasColumns('payees', [
        'id',
        'ledger_id',
        'name',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('transactions'))->toBeTrue();
    expect(Schema::hasColumns('transactions', [
        'id',
        'ledger_id',
        'account_id',
        'category_id',
        'payee_id',
        'transaction_type',
        'amount',
        'description',
        'notes',
        'transaction_date',
        'transfer_pair_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('ledgers table has cycle_start_day column with default 1', function () {
    expect(Schema::hasColumn('ledgers', 'cycle_start_day'))->toBeTrue();

    $column = collect(Schema::getColumns('ledgers'))
        ->firstWhere('name', 'cycle_start_day');

    expect($column)->not->toBeNull();
    expect($column['type_name'])->toBe('integer');
    expect($column['nullable'])->toBeFalse();
    expect($column['default'])->toContain('1');
});

test('categories table has parent_id nullable foreign key column', function () {
    expect(Schema::hasColumn('categories', 'parent_id'))->toBeTrue();

    $column = collect(Schema::getColumns('categories'))
        ->firstWhere('name', 'parent_id');

    expect($column)->not->toBeNull();
    expect($column['nullable'])->toBeTrue();
});

test('users table has onboarding_step and onboarding_data columns', function () {
    expect(Schema::hasColumns('users', ['onboarding_step', 'onboarding_data']))->toBeTrue();

    $columns = collect(Schema::getColumns('users'))->keyBy('name');

    $onboardingStep = $columns->get('onboarding_step');
    expect($onboardingStep)->not->toBeNull();
    expect($onboardingStep['type_name'])->toBe('integer');
    expect($onboardingStep['nullable'])->toBeTrue();

    $onboardingData = $columns->get('onboarding_data');
    expect($onboardingData)->not->toBeNull();
    expect($onboardingData['nullable'])->toBeTrue();
});

test('bills table exists with all required columns', function () {
    expect(Schema::hasTable('bills'))->toBeTrue();
    expect(Schema::hasColumns('bills', [
        'id',
        'ledger_id',
        'account_id',
        'category_id',
        'payee_id',
        'name',
        'amount',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_day',
        'next_due_date',
        'auto_create',
        'end_type',
        'end_date',
        'end_after_occurrences',
        'occurrences_count',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    $columns = collect(Schema::getColumns('bills'))->keyBy('name');

    expect($columns->get('category_id')['nullable'])->toBeTrue();
    expect($columns->get('payee_id')['nullable'])->toBeTrue();
    expect($columns->get('recurrence_day')['nullable'])->toBeTrue();
    expect($columns->get('end_type')['nullable'])->toBeTrue();
    expect($columns->get('end_date')['nullable'])->toBeTrue();
    expect($columns->get('end_after_occurrences')['nullable'])->toBeTrue();
    expect($columns->get('auto_create')['default'])->toContain('0');
    expect($columns->get('is_active')['default'])->toContain('1');
    expect($columns->get('occurrences_count')['default'])->toContain('0');
    expect($columns->get('recurrence_interval')['default'])->toContain('1');
});

test('phase 5 performance indexes are present', function () {
    $indexes = collect(Schema::getIndexes('transactions'))->pluck('name')
        ->merge(collect(Schema::getIndexes('bills'))->pluck('name'))
        ->merge(collect(Schema::getIndexes('categories'))->pluck('name'))
        ->merge(collect(Schema::getIndexes('budgets'))->pluck('name'))
        ->merge(collect(Schema::getIndexes('tags'))->pluck('name'))
        ->merge(collect(Schema::getIndexes('tag_transaction'))->pluck('name'));

    expect($indexes)->toContain('transactions_account_id_index')
        ->toContain('transactions_category_id_index')
        ->toContain('transactions_payee_id_index')
        ->toContain('transactions_transaction_type_index')
        ->toContain('transactions_account_id_transaction_date_index')
        ->toContain('bills_next_due_date_index')
        ->toContain('bills_is_active_index')
        ->toContain('bills_ledger_id_is_active_next_due_date_index')
        ->toContain('categories_parent_id_index')
        ->toContain('budgets_ledger_id_is_active_index')
        ->toContain('budgets_ledger_id_category_id_index')
        ->toContain('tags_ledger_id_index')
        ->toContain('tag_transaction_transaction_id_index')
        ->toContain('tag_transaction_tag_id_index');
});
