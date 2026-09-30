<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence in depth for session auth. Tenant sessions are stored in the tenant
 * database, so a session ID from tenant A should simply not exist in tenant B.
 * But if sessions ever move to a shared store (Redis, a misconfigured cookie
 * domain...), a replayed session holding user_id=1 would authenticate as
 * tenant B's user #1. Binding each session to the tenant that created it
 * turns that failure mode into a plain logout.
 *
 * Must run after StartSession and before Authenticate (see bootstrap/app.php).
 */
class EnsureSessionBelongsToTenant
{
    public const SESSION_KEY = '_tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = (string) tenant()?->getTenantKey();
        $session = $request->session();
        $boundTo = $session->get(self::SESSION_KEY);

        if ($boundTo !== null && ! hash_equals((string) $boundTo, $tenantId)) {
            // Drop everything, including the auth guard's user id.
            $session->invalidate();
            $session->regenerateToken();
        }

        $session->put(self::SESSION_KEY, $tenantId);

        return $next($request);
    }
}
