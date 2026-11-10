<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    /**
     * List the seller's notifications and mark them read.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->merchant === null) {
            return redirect()->route('store.index');
        }

        $notifications = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'body' => $notification->data['body'] ?? '',
                'href' => $notification->data['href'] ?? null,
                'read' => $notification->read_at !== null,
                'at' => $notification->created_at?->diffForHumans(),
            ]);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('notifications/index', [
            'merchantName' => $user->merchant->name,
            'notifications' => $notifications,
        ]);
    }
}
