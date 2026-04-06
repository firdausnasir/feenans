<?php

namespace App\Data\Tags\Input;

use App\Data\Shared\Input\BaseInputData;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Normalizers\Normalizer;

class UpdateTagData extends BaseInputData
{
    public function __construct(
        public string $name,
        public ?string $color,
        #[FromRouteParameter('ledger')] public Ledger $ledger,
        #[FromRouteParameter('tag')] public Tag $tag,
        #[FromAuthenticatedUser] public User $user,
    ) {}

    public static function normalizers(): array
    {
        return [UpdateTagRequestNormalizer::class, ...parent::normalizers()];
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

        /** @var Tag|null $tag */
        $tag = request()->route('tag');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Tag::class, 'name')
                    ->where(fn ($query) => $query->where('ledger_id', $ledger?->id))
                    ->ignore($tag),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Please enter a tag name.',
            'color.regex' => 'Please enter a valid hex color like #FF0000.',
        ];
    }
}

class UpdateTagRequestNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        if (! $value instanceof Request) {
            return null;
        }

        return $value->all();
    }
}
