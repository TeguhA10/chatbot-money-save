<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AuthenticateGatewayRequest
{
    public function handle(Request $request, Closure $next)
    {
        $expected=(string)config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        if ($expected === '' || !hash_equals($expected, (string)$request->header('X-Gateway-Secret'))) return response()->json(['message'=>'Unauthorized'],401);
        return $next($request);
    }
}
