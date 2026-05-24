<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $query = $user->notifications()->orderByDesc('created_at');

        if (isset($filters['unread']) && $filters['unread']) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(fn (DatabaseNotification $n) => $this->formatNotification($n));

        return response()->json($paginator);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($request, $id);
        $notification->markAsRead();

        return response()->json(['success' => true, 'data' => $this->formatNotification($notification)]);
    }

    public function markUnread(Request $request, string $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($request, $id);
        $notification->forceFill(['read_at' => null])->save();

        return response()->json(['success' => true, 'data' => $this->formatNotification($notification)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findOwnedNotification($request, $id)->delete();

        return response()->json(['success' => true]);
    }

    private function findOwnedNotification(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification === null) {
            abort(404);
        }

        return $notification;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'userId' => (string) $notification->notifiable_id,
            'title' => (string) ($data['title'] ?? 'Notification'),
            'message' => (string) ($data['message'] ?? ''),
            'type' => (string) ($data['type'] ?? 'info'),
            'read' => $notification->read_at !== null,
            'createdAt' => $notification->created_at?->toIso8601String() ?? '',
        ];
    }
}
