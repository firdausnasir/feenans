<?php

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;

test('scheduled bill tasks prevent overlaps across scheduler runs', function () {
    $events = collect(app(Schedule::class)->events());

    $processAutoBills = $events->first(
        fn (mixed $event) => $event instanceof CallbackEvent && $event->description === 'bills:process-auto'
    );

    $checkBillReminders = $events->first(
        fn (mixed $event) => str_contains((string) $event->command, 'bills:check-reminders')
    );

    $callback = $processAutoBills instanceof CallbackEvent
        ? (fn () => $this->callback)->call($processAutoBills)
        : null;

    $scheduledAction = is_callable($callback)
        ? app()->call($callback)
        : null;

    expect($processAutoBills)->not->toBeNull()
        ->and($scheduledAction)->toBeNull()
        ->and($processAutoBills->withoutOverlapping)->toBeTrue()
        ->and($processAutoBills->onOneServer)->toBeTrue()
        ->and($checkBillReminders)->not->toBeNull()
        ->and($checkBillReminders->withoutOverlapping)->toBeTrue()
        ->and($checkBillReminders->onOneServer)->toBeTrue();
});
