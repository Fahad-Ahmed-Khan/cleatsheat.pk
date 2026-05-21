<?php

namespace App\Http\Controllers\Web\Store\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Store\Account\Concerns\BuildsAccountSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    use BuildsAccountSeo;

    public function edit(): Response
    {
        return Inertia::render('Store/Account/Password', [
            'seo' => $this->accountSeo('Change password', '/account/password'),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
