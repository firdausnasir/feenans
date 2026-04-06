<?php

namespace App\Http\Controllers\Web\Architecture;

use App\Data\Architecture\Input\ProofTagData;
use App\Exceptions\Domain\ProofTagDomainException;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProofTagController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        if ($request->string('throw')->toString() === 'domain') {
            throw new ProofTagDomainException('Proof exception from route');
        }

        return Inertia::render('architecture/proof-tags/index', [
            'proof' => [
                'ledger_id' => $ledger->id,
                'user_id' => $request->user()?->id,
            ],
        ]);
    }

    public function store(Request $request, Ledger $ledger, ProofTagData $data): RedirectResponse
    {
        if ($request->string('throw')->toString() === 'domain') {
            throw new ProofTagDomainException('Proof exception from action');
        }

        return to_route('architecture.proof-tags.index', $data->ledger)
            ->with('success', 'Proof tag accepted for ledger '.$data->ledger->id.' by user '.$data->user->id.'.');
    }
}
