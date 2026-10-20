<?php

declare(strict_types=1);

use App\Http\Controllers\IntegrationsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]));

Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Existing dashboard route is here...

    // Management Dashboard Panel Layout
    Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations.index');

    // Catch-All OAuth Link Pipeline Handshakes
    Route::get('/auth/{provider}/redirect', [IntegrationsController::class, 'redirectToProvider'])->name('auth.redirect');
    Route::get('/auth/{provider}/callback', [IntegrationsController::class, 'handleProviderCallback']);

    // AJAX Dynamic File Browser API Engine Hook
    Route::get('/integrations/{integration_id}/files', [IntegrationsController::class, 'listFiles'])->name('integrations.files');

    Route::delete('/integrations/{integration_id}', [IntegrationsController::class, 'destroy'])->name('integrations.destroy');
    Route::post('/integrations/{integration_id}/sync', [IntegrationsController::class, 'sync'])->name('integrations.sync');
});

require __DIR__.'/auth.php';
