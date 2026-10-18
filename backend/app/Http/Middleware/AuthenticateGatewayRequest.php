<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * AuthenticateGatewayRequest
 *
 * Enforces secret token verification on requests originating from the Baileys WhatsApp Gateway.
 */
class AuthenticateGatewayRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Allow Meta Webhook handshake (GET) and webhook endpoints
        if ($request->isMethod('GET') || $request->is('api/webhook/whatsapp*') || $request->is('webhook/whatsapp*')) {
            return $next($request);
        }

        // 2. Allow Meta Cloud API webhook request
        if ($request->hasHeader('X-Hub-Signature-256') || $request->input('object') === 'whatsapp_business_account') {
            return $next($request);
        }

        // 3. Allow Baileys WhatsApp Gateway sidecar
        $expected = (string) config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        if ($expected !== '' && hash_equals($expected, (string) $request->header('X-Gateway-Secret'))) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
