<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\Tenancy\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TenantRegistrationController extends Controller
{
    /**
     * 202 Accepted: the tenant exists, but its workspace is still being
     * provisioned in the background. Clients poll the status URL.
     */
    public function store(RegisterTenantRequest $request, RegisterTenant $registerTenant): JsonResponse
    {
        $tenant = $registerTenant->handle($request->toRegistration());

        return TenantResource::make($tenant->load('domains'))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED)
            ->header('Location', route('central.tenants.status', $tenant));
    }

    public function status(Tenant $tenant): TenantResource
    {
        return TenantResource::make($tenant->load('domains'));
    }
}
