<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['general', 'bug', 'feature'])],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $request->user()->feedbacks()->create($validated);

        return back()->with('success', 'Thank you for your feedback!');
    }
}
