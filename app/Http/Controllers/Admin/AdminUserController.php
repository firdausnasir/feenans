<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     *     is_admin: bool,
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
            'is_admin' => $user->is_admin,
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

    public function destroy(User $user): JsonResponse
    {
        abort_if($user->is_admin, 403);

        DB::transaction(function () use ($user): void {
            $attachments = Attachment::query()
                ->whereHas('transaction.ledger', fn ($query) => $query->whereBelongsTo($user))
                ->get(['id', 'path']);

            $attachmentIds = $attachments->pluck('id')->all();
            $attachmentPaths = $attachments->pluck('path')->all();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user::class)
                ->where('tokenable_id', $user->id)
                ->delete();
            DB::table('notifications')
                ->where('notifiable_type', $user::class)
                ->where('notifiable_id', $user->id)
                ->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();

            DB::afterCommit(function () use ($attachmentIds, $attachmentPaths, $user): void {
                try {
                    if (Attachment::deleteFiles($attachmentPaths)) {
                        return;
                    }

                    Log::warning('Attachment cleanup failed after commit.', [
                        'user_id' => $user->id,
                        'attachment_ids' => $attachmentIds,
                        'paths' => $attachmentPaths,
                        'exception' => null,
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('Attachment cleanup failed after commit.', [
                        'user_id' => $user->id,
                        'attachment_ids' => $attachmentIds,
                        'paths' => $attachmentPaths,
                        'exception' => $exception,
                    ]);
                }
            });
        });

        return response()->json(status: 204);
    }
}
