<?php

use App\Models\Ledger;
use Carbon\CarbonImmutable;

test('cycleBounds returns correct start and end for cycle_start_day 1', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 1]);

    $reference = CarbonImmutable::parse('2024-03-15');
    $bounds = $ledger->cycleBounds($reference);

    expect($bounds['start']->toDateString())->toBe('2024-03-01');
    expect($bounds['end']->toDateString())->toBe('2024-03-31');
});

test('cycleBounds returns correct start and end for mid-month cycle_start_day', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 25]);

    // March 27 is after the 25th, so cycle starts March 25 and ends April 24
    $reference = CarbonImmutable::parse('2024-03-27');
    $bounds = $ledger->cycleBounds($reference);

    expect($bounds['start']->toDateString())->toBe('2024-03-25');
    expect($bounds['end']->toDateString())->toBe('2024-04-24');
});

test('cycleBounds when reference date is before cycle_start_day in month', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 25]);

    // March 15 is before the 25th, so cycle starts Feb 25 and ends March 24
    $reference = CarbonImmutable::parse('2024-03-15');
    $bounds = $ledger->cycleBounds($reference);

    expect($bounds['start']->toDateString())->toBe('2024-02-25');
    expect($bounds['end']->toDateString())->toBe('2024-03-24');
});

test('cycleBounds handles cycle_start_day 31 when prior month is February', function () {
    $ledger = Ledger::factory()->create(['cycle_start_day' => 31]);

    // March 15 day(15) < startDay(31), so we go back to February.
    // February 2024 has 29 days (leap year), so start = Feb 29.
    // end = Feb 29 + 1 month (no overflow) - 1 day = March 29 - 1 day = March 28.
    $reference = CarbonImmutable::parse('2024-03-15');
    $bounds = $ledger->cycleBounds($reference);

    expect($bounds['start']->toDateString())->toBe('2024-02-29');
    expect($bounds['end']->toDateString())->toBe('2024-03-28');
});
