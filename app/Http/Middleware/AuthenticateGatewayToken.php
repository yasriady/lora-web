<?php

namespace App\Http\Middleware;

use App\Models\Gateway;
use App\Models\GatewayLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGatewayToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $gateway = $token === null ? null : Gateway::query()
            ->where('api_token', hash('sha256', $token))->where('enabled', true)->first();

        if ($gateway === null) {
            GatewayLog::query()->create([
                'gateway_id' => null,
                'level' => 'warning',
                'event' => 'authentication_failed',
                'message' => 'Gateway request rejected because its bearer token is missing, invalid, or disabled.',
            ]);

            return response()->json(['message' => 'Unauthenticated gateway.'], 401);
        }

        $request->attributes->set('authenticated_gateway', $gateway);

        return $next($request);
    }
}
