<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse|SymfonyResponse
    {
        $request->authenticateForAdmin();

        $request->session()->regenerate();

        $destination = $this->resolveAdminLoginDestination($request);

        if ($request->header(Header::INERTIA)) {
            return Inertia::location($destination);
        }

        return redirect()->to($destination);
    }

    public function destroy(Request $request): RedirectResponse|SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($request->header(Header::INERTIA)) {
            return Inertia::location(route('admin.login'));
        }

        return redirect()->route('admin.login');
    }

    private function resolveAdminLoginDestination(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $path = parse_url($intended, PHP_URL_PATH) ?? '';

            if (str_starts_with($path, '/admin') && ! str_starts_with($path, '/admin/login')) {
                return $intended;
            }
        }

        return route('admin.dashboard', absolute: false);
    }
}
