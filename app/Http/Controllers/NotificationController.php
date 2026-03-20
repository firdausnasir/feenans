<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()?->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->findOrFail($id)->delete();

        return redirect()->back();
    }
}
