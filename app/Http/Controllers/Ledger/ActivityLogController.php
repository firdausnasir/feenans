<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $subjectType = $request->string('subject_type')->toString() ?: null;
        $action = $request->string('action')->toString() ?: null;
        $page = max(1, $request->integer('page', 1));

        return Inertia::render('ledgers/activity/index', [
            'filters' => [
                'subject_type' => $subjectType,
                'action' => $action,
                'page' => $page,
            ],
        ]);
    }
}
