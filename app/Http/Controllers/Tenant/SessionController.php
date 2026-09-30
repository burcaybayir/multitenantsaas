<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Session auth for the first-party dashboard. The public API for third
 * parties uses OAuth (Passport + PKCE) instead; see the security phase.
 */
class SessionController extends Controller
{
    public function store(LoginRequest $request): UserResource
    {
        $request->authenticate();

        // New session ID on privilege change: prevents session fixation.
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
