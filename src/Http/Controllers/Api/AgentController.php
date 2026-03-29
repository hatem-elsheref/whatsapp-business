<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use WhatsApp\Business\Models\Agent;

class AgentController
{
    public function index(Request $request): JsonResponse
    {
        $currentAgent = $request->user();
        $customer = $currentAgent->customer;

        $agents = Agent::where('customer_id', $customer->id)
            ->withCount(['conversations', 'assignedTickets'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'agents' => $agents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:wa_agents,email',
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:admin,agent',
        ]);

        $currentAgent = $request->user();
        $customer = $currentAgent->customer;

        if (!$currentAgent->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can create agents',
            ], 403);
        }

        $agent = Agent::create([
            'customer_id' => $customer->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password ? Hash::make($request->password) : null,
            'role' => $request->role ?? 'agent',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agent created successfully',
            'agent' => $agent,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $currentAgent = $request->user();
        $customer = $currentAgent->customer;

        $agent = Agent::where('customer_id', $customer->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'agent' => $agent,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:wa_agents,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:admin,agent',
            'is_active' => 'nullable|boolean',
        ]);

        $currentAgent = $request->user();
        $customer = $currentAgent->customer;

        $agent = Agent::where('customer_id', $customer->id)
            ->findOrFail($id);

        if ($currentAgent->id === $agent->id && $request->has('role') && $request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change your own role',
            ], 403);
        }

        $updateData = array_filter([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => $request->is_active,
        ], fn($v) => $v !== null);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $agent->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Agent updated successfully',
            'agent' => $agent->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentAgent = $request->user();
        $customer = $currentAgent->customer;

        if ($currentAgent->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 403);
        }

        $agent = Agent::where('customer_id', $customer->id)
            ->findOrFail($id);

        $agent->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Agent deactivated successfully',
        ]);
    }
}
