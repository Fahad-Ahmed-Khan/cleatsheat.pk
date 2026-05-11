<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentSettingsRequest;
use App\Models\PaymentMethodConfig;
use App\Models\PaymentSiteSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $methods = PaymentMethodConfig::query()
            ->orderBy('sort_order')
            ->orderBy('gateway_code')
            ->get();

        $site = PaymentSiteSetting::current();

        return Inertia::render('Admin/PaymentSettings/Index', [
            'methods' => $methods,
            'fallback_online_failed_to_cod' => $site->fallback_online_failed_to_cod,
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach ($data['methods'] as $row) {
            PaymentMethodConfig::query()->whereKey($row['id'])->update([
                'enabled' => $row['enabled'] ?? false,
                'customer_label' => $row['customer_label'],
                'fee_fixed' => $row['fee_fixed'],
                'fee_percent' => $row['fee_percent'],
                'sort_order' => $row['sort_order'],
            ]);
        }

        PaymentSiteSetting::current()->update([
            'fallback_online_failed_to_cod' => $data['fallback_online_failed_to_cod'] ?? false,
        ]);

        return redirect()->route('admin.payment-settings.edit')->with('success', 'Payment settings saved.');
    }
}
