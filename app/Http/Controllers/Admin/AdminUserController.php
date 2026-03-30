<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    private function membershipFor(User $user): UserMembership
    {
        $membership = $user->relationLoaded('membership')
            ? $user->getRelation('membership')
            : $user->membership()->first();

        if ($membership instanceof UserMembership) {
            $user->setRelation('membership', $membership);

            return $membership;
        }

        $membership = $user->membership()->create([
            'tier' => 'free',
            'status' => 'active',
        ]);

        $user->setRelation('membership', $membership);

        return $membership;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     email_verified_at: string|null,
     *     created_at: string,
     *     membership: array{tier: string, status: string, started_at: string|null, ends_at: string|null},
     * }
     */
    private function formatUserRow(User $user): array
    {
        $membership = $this->membershipFor($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
            'membership' => [
                'tier' => $membership->tier,
                'status' => $membership->status,
                'started_at' => $membership->started_at?->toIso8601String(),
                'ends_at' => $membership->ends_at?->toIso8601String(),
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('membership');

        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user) => $this->formatUserRow($user))->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
