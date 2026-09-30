<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subdomain = $this->domains->first()?->domain;
        $centralDomain = config('tenancy.central_domains')[0];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'url' => $subdomain ? $request->getScheme()."://{$subdomain}.{$centralDomain}" : null,
            'provisioned_at' => $this->provisioned_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
