<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WhatsAppInboundMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppInboxAdminController extends Controller
{
    public function index(Request $request): Response
    {

        $search = trim((string) $request->query('search', ''));

        $phones = WhatsAppInboundMessage::query()

            ->selectRaw('from_number, MAX(received_at) as last_at, COUNT(*) as message_count')

            ->when($search !== '', fn ($q) => $q->where('from_number', 'like', '%'.$search.'%')

                ->orWhere('body', 'like', '%'.$search.'%'))

            ->groupBy('from_number')

            ->orderByDesc('last_at')

            ->paginate(25)

            ->withQueryString();

        $phones->through(function ($row) {

            $latest = WhatsAppInboundMessage::query()

                ->where('from_number', $row->from_number)

                ->orderByDesc('received_at')

                ->first();

            $order = $latest?->order_id

                ? Order::query()->find($latest->order_id)

                : null;

            return [

                'from_number' => $row->from_number,

                'message_count' => (int) $row->message_count,

                'last_at' => $latest?->received_at?->toIso8601String(),

                'last_body' => $latest?->body,

                'last_handled_as' => $latest?->handled_as,

                'order_id' => $order?->id,

                'order_number' => $order?->order_number,

            ];

        });

        $threadPhone = trim((string) $request->query('thread', ''));

        $thread = [];

        if ($threadPhone !== '') {

            $thread = WhatsAppInboundMessage::query()

                ->where('from_number', $threadPhone)

                ->orderByDesc('received_at')

                ->limit(100)

                ->get()

                ->map(fn (WhatsAppInboundMessage $m): array => [

                    'id' => $m->id,

                    'type' => $m->type,

                    'body' => $m->body,

                    'button_payload' => $m->button_payload,

                    'handled_as' => $m->handled_as,

                    'handler_notes' => $m->handler_notes,

                    'order_id' => $m->order_id,

                    'received_at' => $m->received_at?->toIso8601String(),

                ])

                ->values()

                ->all();

        }

        return Inertia::render('Admin/WhatsApp/Inbox', [

            'conversations' => $phones,

            'filters' => ['search' => $search, 'thread' => $threadPhone],

            'thread_messages' => $thread,

        ]);

    }

}
