<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MergePayeesRequest;
use App\Http\Requests\UpdatePayeeRequest;
use App\Http\Resources\PayeeResource;
use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PayeeController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $query = $ledger->payees();

        if ($request->boolean('with_counts')) {
            $query->withCount('transactions');
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $payees = $query->orderBy('name')->get();

        return PayeeResource::collection($payees);
    }

    public function show(Ledger $ledger, Payee $payee): PayeeResource
    {
        $this->authorize('view', $ledger);

        return new PayeeResource($payee);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $payee = $ledger->payees()->create($validated);

        return (new PayeeResource($payee))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePayeeRequest $request, Ledger $ledger, Payee $payee): PayeeResource
    {
        $this->authorize('update', $ledger);

        $payee->update($request->validated());

        return new PayeeResource($payee->fresh());
    }

    public function destroy(Ledger $ledger, Payee $payee): JsonResponse
    {
        $this->authorize('delete', $ledger);

        $payee->delete();

        return response()->json(null, 204);
    }

    public function merge(MergePayeesRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('update', $ledger);

        $sourcePayee = $ledger->payees()->findOrFail($request->integer('source_id'));
        $targetPayee = $ledger->payees()->findOrFail($request->integer('target_id'));

        DB::transaction(function () use ($sourcePayee, $targetPayee): void {
            $sourcePayee->transactions()->update(['payee_id' => $targetPayee->id]);
            $sourcePayee->delete();
        });

        return response()->json(['message' => 'Payees merged successfully.']);
    }
}
