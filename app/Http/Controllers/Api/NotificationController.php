<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    use RespondsWithApi;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25));

        $unreadCount = Notification::where('user_id', $user->id)->where('is_read', false)->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Notifications fetched successfully.');
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        $notification->update(['is_read' => true]);

        return $this->success([
            'notification' => $notification,
        ], 'Notification marked as read successfully.');
    }

    public function broadcast(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['sometimes', Rule::in(['Email', 'SMS', 'WhatsApp', 'Push'])],
        ]);

        $type = $payload['type'] ?? 'Push';
        $users = User::all();

        foreach ($users as $user) {
            $this->notificationService->send($user, $payload['title'], $payload['message'], $type);
        }

        return $this->success(message: sprintf('Broadcast announcements sent to %d users successfully.', $users->count()));
    }
}
