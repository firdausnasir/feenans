<?php

namespace App\Http\Controllers\Api\V1\Architecture;

use App\Data\Architecture\Input\ProofTagData;
use App\Exceptions\Domain\ProofTagDomainException;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;

class ProofTagController extends Controller
{
    public function exception(Ledger $ledger): never
    {
        $this->authorize('view', $ledger);

        throw new ProofTagDomainException('Proof exception from route');
    }

    public function store(Ledger $ledger, ProofTagData $data): JsonResponse
    {
        return response()->json([
            'data' => [
                'ledger_id' => $data->ledger->id,
                'user_id' => $data->user->id,
                'name' => $data->name,
                'color' => $data->color,
                'icon_uploaded' => $data->icon !== null,
            ],
        ], 201);
    }
}
