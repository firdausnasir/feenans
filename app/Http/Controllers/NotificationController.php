<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        $notifications = $user->unreadNotifications()
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $notification = $request->user()?->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response
    {
        $request->user()?->unreadNotifications()->update(['read_at' => now()]);

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->noContent();
    }

    public function destroy(Request $request, string $id): Response
    {
        $request->user()?->notifications()->findOrFail($id)->delete();

        return response()->noContent();
    }
}
