<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::with('user:id,name,email')
            ->latest()
            ->paginate(20);

        return response()->json($feedbacks);
    }
}
