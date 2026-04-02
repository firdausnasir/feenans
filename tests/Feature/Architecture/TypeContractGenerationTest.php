<?php

use Illuminate\Support\Facades\File;

test('report typescript contracts can be generated and consumed by report pages', function () {
    $outputPath = resource_path('js/types/generated/report-contracts.d.ts');
    $manifestPath = resource_path('js/types/generated/typescript-transformer-manifest.json');
    $originalContents = File::exists($outputPath)
        ? File::get($outputPath)
        : null;

    expect($originalContents)->not->toBeNull();

    try {
        File::delete($outputPath);

        $this->artisan('typescript:transform')
            ->assertSuccessful();

        expect(File::exists($outputPath))->toBeTrue();

        $generatedContracts = File::get($outputPath);

        expect($generatedContracts)
            ->toContain('export type FinancialHealthReportData')
            ->toContain('export type BudgetPerformanceReportData')
            ->toContain('export type CashFlowReportData')
            ->toBe($originalContents);

        expect(File::get(resource_path('js/pages/ledgers/reports/financial-health.tsx')))
            ->toContain('App.Data.Reports.Output.Web.FinancialHealthReportData')
            ->not->toContain('type NetWorthEntry = {')
            ->not->toContain('type SavingsRateEntry = {')
            ->not->toContain('type CurrentSnapshot = {');

        expect(File::get(resource_path('js/pages/ledgers/reports/budget-performance.tsx')))
            ->toContain('App.Data.Reports.Output.Web.BudgetPerformanceReportData')
            ->not->toContain('type BudgetStat = {');

        expect(File::get(resource_path('js/pages/ledgers/reports/cash-flow.tsx')))
            ->toContain('App.Data.Reports.Output.Web.CashFlowReportData')
            ->not->toContain('type DailyCashFlowEntry = {')
            ->not->toContain('type UpcomingBill = {');
    } finally {
        if ($originalContents !== null) {
            File::put($outputPath, $originalContents);
        } else {
            File::delete($outputPath);
        }

        File::delete($manifestPath);
    }
});
