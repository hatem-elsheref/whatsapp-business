<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use WhatsApp\Business\Models\Agent;
use Illuminate\Auth\AuthenticationException;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $agent = Agent::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$agent || !Hash::check($request->password, $agent->password)) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة'],
            ]);
        }

        $token = $agent->createToken('agent-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'role' => $agent->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $agent = $request->user();
        
        $agent->update(['last_active_at' => now()]);

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => $agent->role,
            'avatar_url' => $agent->avatar_url,
            'customer_id' => $agent->customer_id,
            'last_active_at' => $agent->last_active_at,
        ]);
    }
}
