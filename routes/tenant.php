<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\SessionController;
use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Http\Middleware\InitializeTenancyBySubdomain;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * Tenant routes: <subdomain>.<central domain>.
 *
 * Middleware order matters and is enforced via the priority list:
 * identify an *active* tenant -> switch DB/cache/storage -> start session (tenant DB)
 * -> bind session to tenant -> authenticate -> route model binding.
 */
Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureSessionBelongsToTenant::class,
])->name('tenant.')->group(function (): void {
    // SPA bootstrap: sets the XSRF-TOKEN cookie before the first POST.
    Route::get('/csrf-cookie', fn () => response()->noContent())->name('csrf-cookie');

    Route::post('/login', [SessionController::class, 'store'])->middleware('guest')->name('login');

    Route::middleware('auth')->group(function (): void {
        Route::get('/me', [SessionController::class, 'show'])->name('me');
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show']);
    });
});
