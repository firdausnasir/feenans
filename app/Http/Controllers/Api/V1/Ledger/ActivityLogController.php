<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ledger;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $subjectType = $request->string('subject_type')->toString() ?: null;
        $action = $request->string('action')->toString() ?: null;
        $page = max(1, $request->integer('page', 1));

        $query = ActivityLog::query()
            ->where('ledger_id', $ledger->id)
            ->with('user')
            ->latest('created_at')
            ->latest('id');

        if ($subjectType !== null) {
            $query->where(function ($builder) use ($subjectType): void {
                $builder->where('subject_type', $subjectType)
                    ->orWhere('subject_type', 'LIKE', '%\\'.$subjectType);
            });
        }

        if ($action !== null) {
            $query->where('action', $action);
        }

        $activity = $query->paginate(50, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($activity->items())
                ->map(fn (ActivityLog $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'subject_type' => class_basename($entry->subject_type),
                    'subject_id' => $entry->subject_id,
                    'old_values' => $entry->old_values ?? [],
                    'new_values' => $entry->new_values ?? [],
                    'user' => $entry->user ? ['name' => $entry->user->name] : null,
                    'created_at' => $entry->created_at instanceof CarbonInterface
                        ? $entry->created_at->toISOString()
                        : null,
                ])
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $activity->currentPage(),
                'last_page' => $activity->lastPage(),
                'per_page' => $activity->perPage(),
                'total' => $activity->total(),
            ],
        ]);
    }
}
