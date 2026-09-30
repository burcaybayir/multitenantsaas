<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\NotASubdomainException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain as BaseMiddleware;

/**
 * Identifies the tenant from the host and initializes tenancy, with two
 * differences from the package middleware:
 *
 * 1. Strict host parsing. The package accepts any host that merely *ends
 *    with* a central domain and takes its first label, so
 *    `acme.evilinstallhub.localhost` would serve acme's workspace on a domain
 *    we don't own. Here a host is a tenant host only if it is exactly
 *    `<one-label>.<central domain>`.
 *
 * 2. Only active tenants are initialized. A tenant still being provisioned
 *    has no (complete) database, so we answer 503 *before* tenancy switches
 *    any connection, cache or filesystem to it.
 */
class InitializeTenancyBySubdomain extends BaseMiddleware
{
    /**
     * @return string|NotASubdomainException
     */
    protected function makeSubdomain(string $hostname)
    {
        $hostname = strtolower($hostname);

        foreach (config('tenancy.central_domains') as $centralDomain) {
            $suffix = '.'.strtolower($centralDomain);

            if (! str_ends_with($hostname, $suffix)) {
                continue;
            }

            $label = substr($hostname, 0, -strlen($suffix));

            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) === 1) {
                return $label;
            }
        }

        return new NotASubdomainException($hostname);
    }

    /**
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function initializeTenancy($request, $next, ...$resolverArguments)
    {
        try {
            $tenant = $this->resolver->resolve(...$resolverArguments);
        } catch (TenantCouldNotBeIdentifiedException $e) {
            $onFail = static::$onFail ?? static fn ($e) => throw $e;

            return $onFail($e, $request, $next);
        }

        if (! $tenant instanceof Tenant || ! $tenant->status->canServeRequests()) {
            abort(503, 'This workspace is not available yet.', ['Retry-After' => '30']);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
