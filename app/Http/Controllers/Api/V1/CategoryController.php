<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $categories = $ledger->categories()
            ->with('children')
            ->parents()
            ->orderBy('position')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(Ledger $ledger, Category $category): CategoryResource
    {
        $this->authorize('view', $ledger);

        return new CategoryResource($category->load('children'));
    }
}
