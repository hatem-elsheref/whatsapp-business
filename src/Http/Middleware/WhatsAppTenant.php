<?php

namespace WhatsApp\Business\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WhatsApp\Business\Models\Agent;

class WhatsAppTenant
{
    public function handle(Request $request, Closure $next)
    {
        $agent = $request->user();

        if (!$agent || !($agent instanceof Agent)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $customer = $agent->customer;

        if (!$customer || !$customer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account is inactive',
            ], 403);
        }

        if ($customer->isTokenExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Meta access token expired. Please reconnect your account.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        $request->attributes->set('whatsapp_customer', $customer);

        return $next($request);
    }
}
