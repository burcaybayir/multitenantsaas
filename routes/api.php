<?php

declare(strict_types=1);

use App\Http\Controllers\Central\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

/*
 * Central domain only (registered per central domain in bootstrap/app.php).
 */
Route::post('/tenants', [TenantRegistrationController::class, 'store'])
    ->middleware('throttle:tenant-registrations')
    ->name('central.tenants.store');

Route::get('/tenants/{tenant}/status', [TenantRegistrationController::class, 'status'])
    ->middleware('throttle:60,1')
    ->whereUuid('tenant')
    ->name('central.tenants.status');
