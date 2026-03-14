<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/activity/index', [
            'ledger' => $ledger,
            'activity' => ActivityLog::query()
                ->where('ledger_id', $ledger->id)
                ->with('user')
                ->latest('created_at')
                ->limit(100)
                ->get(),
        ]);
    }
}
