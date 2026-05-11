<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return ApiResponder::ok([]);
        }

        $paginator = $request->user()
            ->notifications()
            ->latest()
            ->paginate(min((int) $request->query('per_page', 20), 50));

        $items = $paginator->getCollection()->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ])->values()->all();

        return ApiResponder::ok($items, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return ApiResponder::error('Notifications are not enabled.', 404, code: 'notifications_disabled');
        }

        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification === null) {
            return ApiResponder::error('Notification not found.', 404, code: 'notification_not_found');
        }

        $notification->markAsRead();

        return ApiResponder::ok(['read' => true]);
    }
}
