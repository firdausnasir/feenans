<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMembershipRequest;
use App\Models\MembershipChangeLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMembershipController extends Controller
{
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
        $membership = $user->membership()->firstOrCreate([], ['tier' => 'free', 'status' => 'active']);

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

        $tier = $request->query('tier');
        if (is_string($tier) && $tier !== '') {
            $query->whereHas('membership', function ($q) use ($tier): void {
                $q->where('tier', $tier);
            });
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->whereHas('membership', function ($q) use ($status): void {
                $q->where('status', $status);
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
                'tier' => $tier,
                'status' => $status,
            ],
        ]);
    }

    public function update(UpdateMembershipRequest $request, User $user): JsonResponse
    {
        $membership = $user->membership()->firstOrCreate([], ['tier' => 'free', 'status' => 'active']);

        $previousTier = $membership->tier;
        $previousStatus = $membership->status;

        $membership->update($request->safe()->only(['tier', 'status']));

        MembershipChangeLog::query()->create([
            'user_id' => $user->id,
            'changed_by_user_id' => $request->user()->id,
            'previous_tier' => $previousTier,
            'previous_status' => $previousStatus,
            'new_tier' => $membership->tier,
            'new_status' => $membership->status,
            'reason' => $request->validated('reason'),
        ]);

        return response()->json([
            'membership' => [
                'tier' => $membership->tier,
                'status' => $membership->status,
                'started_at' => $membership->started_at?->toIso8601String(),
                'ends_at' => $membership->ends_at?->toIso8601String(),
            ],
        ]);
    }
}
