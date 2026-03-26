<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PremiumPageController extends Controller
{
    /**
     * Show the premium upsell page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('premium/index');
    }
}
