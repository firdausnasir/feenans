<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $perPage = min($request->integer('per_page', 50), 100);

        $query = ActivityLog::query()
            ->where('ledger_id', $ledger->id)
            ->with('user')
            ->latest('created_at')
            ->latest('id');

        if ($request->filled('subject_type')) {
            $subjectType = $request->string('subject_type')->toString();
            $query->where(function ($q) use ($subjectType) {
                $q->where('subject_type', $subjectType)
                    ->orWhere('subject_type', 'LIKE', '%\\'.$subjectType);
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        $paginated = $query->paginate($perPage);

        $paginated->through(fn (ActivityLog $entry) => [
            'id' => $entry->id,
            'action' => $entry->action,
            'subject_type' => class_basename($entry->subject_type),
            'subject_id' => $entry->subject_id,
            'old_values' => $entry->old_values ?? [],
            'new_values' => $entry->new_values ?? [],
            'user' => $entry->user ? ['name' => $entry->user->name] : null,
            'created_at' => $entry->created_at?->toISOString(),
        ]);

        return response()->json($paginated);
    }
}
