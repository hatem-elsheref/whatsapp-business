<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use WhatsApp\Business\Models\Flow;
use WhatsApp\Business\Models\FlowStep;
use WhatsApp\Business\Services\FlowEngine;

class FlowController
{
    public function __construct(
        private FlowEngine $flowEngine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $query = Flow::where('customer_id', $customer->id)
            ->with('steps');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        $flows = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'flows' => $flows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:keyword,new_conversation,button_click,flow_completion,scheduled,api',
            'trigger_value' => 'nullable|string|max:255',
            'phone_number_id' => 'nullable|exists:wa_phone_numbers,id',
            'max_steps' => 'nullable|integer|min:1|max:100',
            'timeout_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::create([
            'customer_id' => $customer->id,
            'name' => $request->name,
            'description' => $request->description,
            'trigger_type' => $request->trigger_type,
            'trigger_value' => $request->trigger_value,
            'phone_number_id' => $request->phone_number_id,
            'is_active' => false,
            'max_steps' => $request->max_steps ?? 50,
            'timeout_minutes' => $request->timeout_minutes ?? 30,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow created',
            'flow' => $flow,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::where('customer_id', $customer->id)
            ->with(['steps' => fn($q) => $q->orderBy('step_order')])
            ->findOrFail($id);

        $stats = [
            'total_runs' => $flow->userData()->count(),
            'active_runs' => $flow->userData()->where('status', 'active')->count(),
            'completed_runs' => $flow->userData()->where('status', 'completed')->count(),
            'abandoned_runs' => $flow->userData()->where('status', 'abandoned')->count(),
        ];

        return response()->json([
            'success' => true,
            'flow' => $flow,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'nullable|in:keyword,new_conversation,button_click,flow_completion,scheduled,api',
            'trigger_value' => 'nullable|string|max:255',
            'phone_number_id' => 'nullable|exists:wa_phone_numbers,id',
            'max_steps' => 'nullable|integer|min:1|max:100',
            'timeout_minutes' => 'nullable|integer|min:1|max:1440',
            'allow_user_interruption' => 'nullable|boolean',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::where('customer_id', $customer->id)
            ->findOrFail($id);

        $updateData = array_filter([
            'name' => $request->name,
            'description' => $request->description,
            'trigger_type' => $request->trigger_type,
            'trigger_value' => $request->trigger_value,
            'phone_number_id' => $request->phone_number_id,
            'max_steps' => $request->max_steps,
            'timeout_minutes' => $request->timeout_minutes,
            'allow_user_interruption' => $request->allow_user_interruption,
        ], fn($v) => $v !== null);

        $flow->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Flow updated',
            'flow' => $flow->fresh()->load('steps'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::where('customer_id', $customer->id)
            ->findOrFail($id);

        $flow->steps()->delete();
        $flow->userData()->delete();
        $flow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Flow deleted',
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::where('customer_id', $customer->id)
            ->with('steps')
            ->findOrFail($id);

        if ($flow->is_active && !$flow->steps()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot activate flow without steps',
            ], 400);
        }

        $flow->update(['is_active' => !$flow->is_active]);

        return response()->json([
            'success' => true,
            'message' => $flow->is_active ? 'Flow activated' : 'Flow deactivated',
            'flow' => $flow,
        ]);
    }

    public function updateSteps(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'steps' => 'required|array',
            'steps.*.step_type' => 'required|in:message,question,condition,action,delay,end,api_call,ticket',
            'steps.*.step_order' => 'required|integer|min:0',
            'steps.*.content' => 'nullable|array',
            'steps.*.collected_variable' => 'nullable|string',
            'steps.*.timeout_seconds' => 'nullable|integer',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $flow = Flow::where('customer_id', $customer->id)
            ->findOrFail($id);

        $flow->steps()->delete();

        $steps = [];
        $previousStepId = null;

        foreach ($request->steps as $index => $stepData) {
            $stepId = Str::uuid()->toString();

            $step = FlowStep::create([
                'flow_id' => $flow->id,
                'step_order' => $stepData['step_order'],
                'step_id' => $stepId,
                'step_type' => $stepData['step_type'],
                'content' => $stepData['content'] ?? null,
                'next_step_id' => null,
                'step_timeout_seconds' => $stepData['timeout_seconds'] ?? 0,
                'collected_variable' => $stepData['collected_variable'] ?? null,
                'variable_type' => $stepData['variable_type'] ?? null,
                'validation_rules' => $stepData['validation_rules'] ?? null,
                'actions' => $stepData['actions'] ?? null,
                'branches' => $stepData['branches'] ?? null,
            ]);

            if ($previousStepId) {
                $previousStep = FlowStep::findByStepId($previousStepId);
                if ($previousStep) {
                    $previousStep->update(['next_step_id' => $step->id]);
                }
            }

            $steps[] = $step;
            $previousStepId = $stepId;
        }

        return response()->json([
            'success' => true,
            'message' => 'Steps updated',
            'steps' => $steps,
        ]);
    }
}
