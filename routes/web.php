<?php

use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/storefront.php';
require __DIR__.'/payments.php';
require __DIR__.'/shipping.php';
require __DIR__.'/whatsapp.php';
require __DIR__.'/deploy.php';

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap/{segment}.xml', [SitemapController::class, 'segment'])
    ->where('segment', '[a-z0-9\-]+')
    ->name('sitemap.segment');

Route::get('/welcome-skeleton', function () {
    return Inertia::render('Welcome');
})->name('welcome.skeleton');

Route::get('/dashboard', function () {
    $user = auth()->user();

    return redirect($user?->isAdmin()
        ? route('admin.dashboard', absolute: false)
        : route('store.account.dashboard', absolute: false));
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::redirect('/profile', '/account/profile')->name('profile.edit');
});

require __DIR__.'/auth.php';

require __DIR__.'/admin.php';
