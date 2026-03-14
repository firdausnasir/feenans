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
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (ActivityLog $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'subject_type' => class_basename($entry->subject_type),
                    'subject_id' => $entry->subject_id,
                    'old_values' => $entry->old_values ?? [],
                    'new_values' => $entry->new_values ?? [],
                    'user' => $entry->user ? ['name' => $entry->user->name] : null,
                    'created_at' => $entry->created_at?->toISOString(),
                ]),
        ]);
    }
}
