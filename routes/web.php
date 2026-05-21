<?php

use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/storefront.php';
require __DIR__.'/payments.php';
require __DIR__.'/shipping.php';
require __DIR__.'/whatsapp.php';

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/welcome-skeleton', function () {
    return Inertia::render('Welcome');
})->name('welcome.skeleton');

Route::redirect('/dashboard', '/account')->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::redirect('/profile', '/account/profile')->name('profile.edit');
});

require __DIR__.'/auth.php';

require __DIR__.'/admin.php';
