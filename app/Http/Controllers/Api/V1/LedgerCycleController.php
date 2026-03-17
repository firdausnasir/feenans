<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerCycleController extends Controller
{
    public function show(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $offset = $request->integer('offset', 0);
        $now = CarbonImmutable::now();

        $referenceDate = $offset !== 0
            ? $now->addMonthsNoOverflow($offset)
            : $now;

        ['start' => $start, 'end' => $end] = $ledger->cycleBounds($referenceDate);

        $prevReference = $referenceDate->subMonthNoOverflow();
        ['start' => $prevStart, 'end' => $prevEnd] = $ledger->cycleBounds($prevReference);

        return response()->json([
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $end->toDateString(),
            'prev_cycle_start' => $prevStart->toDateString(),
            'prev_cycle_end' => $prevEnd->toDateString(),
            'offset' => $offset,
        ]);
    }
}
