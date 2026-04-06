<?php

use App\Actions\Bills\UseCases\ProcessAutoBillsAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(app(ProcessAutoBillsAction::class))
    ->name('bills:process-auto')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('bills:check-reminders')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer();
