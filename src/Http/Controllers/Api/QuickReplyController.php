<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\QuickReply;

class QuickReplyController
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $query = QuickReply::forCustomer($customer->id)
            ->active()
            ->ordered();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $quickReplies = $query->get();

        return response()->json([
            'success' => true,
            'quick_replies' => $quickReplies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:512',
            'shortcut' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'is_global' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        if ($request->boolean('is_global') && !$agent->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can create global quick replies',
            ], 403);
        }

        $quickReply = QuickReply::create([
            'customer_id' => $request->boolean('is_global') ? null : $customer->id,
            'text' => $request->text,
            'shortcut' => $request->shortcut,
            'category' => $request->category,
            'is_global' => $request->boolean('is_global'),
            'is_active' => true,
            'sort_order' => $request->sort_order ?? 0,
            'created_by_agent_id' => $agent->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quick reply created',
            'quick_reply' => $quickReply,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'text' => 'nullable|string|max:512',
            'shortcut' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'is_global' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $quickReply = QuickReply::where(function ($query) use ($customer, $agent) {
            $query->where('customer_id', $customer->id)
                ->orWhere(function ($q) use ($agent) {
                    $q->whereNull('customer_id')
                        ->where('created_by_agent_id', $agent->id);
                });
        })->findOrFail($id);

        $updateData = array_filter([
            'text' => $request->text,
            'shortcut' => $request->shortcut,
            'category' => $request->category,
            'is_global' => $request->is_global,
            'is_active' => $request->is_active,
            'sort_order' => $request->sort_order,
        ], fn($v) => $v !== null);

        $quickReply->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Quick reply updated',
            'quick_reply' => $quickReply->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $quickReply = QuickReply::where('customer_id', $customer->id)
            ->orWhere(function ($query) use ($agent) {
                $query->whereNull('customer_id')
                    ->where('created_by_agent_id', $agent->id);
            })
            ->findOrFail($id);

        $quickReply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quick reply deleted',
        ]);
    }
}
