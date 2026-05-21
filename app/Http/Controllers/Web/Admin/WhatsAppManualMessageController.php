<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\ManualMessageService;
use App\Http\Controllers\Controller;
use App\Models\CourierRider;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsAppManualMessageController extends Controller
{
    public function sendOrder(Request $request, Order $order, ManualMessageService $manual): RedirectResponse
    {

        $data = $request->validate([

            'template_key' => ['nullable', 'string', 'max:64'],

            'message' => ['nullable', 'string', 'max:4096'],

        ]);

        $result = $manual->sendToOrder(

            $order,

            $data['template_key'] ?? null,

            $data['message'] ?? null,

        );

        return $result['ok']

            ? back()->with('status', $result['message'])

            : back()->with('error', $result['message']);

    }

    public function sendCustomer(Request $request, User $customer, ManualMessageService $manual): RedirectResponse
    {

        $data = $request->validate([

            'template_key' => ['nullable', 'string', 'max:64'],

            'message' => ['nullable', 'string', 'max:4096'],

        ]);

        $result = $manual->sendToUser(

            $customer,

            $data['template_key'] ?? null,

            $data['message'] ?? null,

        );

        return $result['ok']

            ? back()->with('status', $result['message'])

            : back()->with('error', $result['message']);

    }

    public function sendRider(Request $request, CourierRider $rider, ManualMessageService $manual): RedirectResponse
    {

        $data = $request->validate([

            'template_key' => ['nullable', 'string', 'max:64'],

            'message' => ['nullable', 'string', 'max:4096'],

        ]);

        $result = $manual->sendToRider(

            $rider,

            $data['template_key'] ?? null,

            $data['message'] ?? null,

        );

        return $result['ok']

            ? back()->with('status', $result['message'])

            : back()->with('error', $result['message']);

    }

}
