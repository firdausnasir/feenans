<?php

namespace App\Data\Accounts\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class ReorderAccountsData extends BaseInputData
{
    /**
     * @param  array<int, array{id: int, position: int}>  $items
     */
    public function __construct(
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromAuthenticatedUser] public User $user,
        public array $items,
    ) {}

    public static function normalizers(): array
    {
        return [ReorderAccountsRequestNormalizer::class, ...parent::normalizers()];
    }

    public static function authorize(Request $request): bool
    {
        $user = $request->user();
        $ledger = $request->route('ledger');

        return $user !== null
            && $ledger instanceof Ledger
            && $user->can('update', $ledger);
    }

    public static function rules(): array
    {
        /** @var Ledger|null $ledger */
        $ledger = request()->route('ledger');
        $idRules = ['required', 'integer', 'distinct:strict'];

        if (request()->expectsJson()) {
            $idRules[] = Rule::exists('accounts', 'id')->where('ledger_id', $ledger?->id);
        }

        return [
            'items' => ['required', 'array'],
            'items.*.id' => $idRules,
            'items.*.position' => ['required', 'integer', 'min:0', 'distinct:strict'],
        ];
    }
}

class ReorderAccountsRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
