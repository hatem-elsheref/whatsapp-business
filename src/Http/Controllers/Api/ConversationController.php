<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\Message;
use WhatsApp\Business\Services\ConversationService;
use Illuminate\Support\Facades\DB;

class ConversationController
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $query = Conversation::with(['phoneNumber', 'assignedAgent'])
            ->where('customer_id', $customer->id);

        if ($request->has('phone_number_id')) {
            $query->where('phone_number_id', $request->phone_number_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        if ($request->has('assigned_agent_id')) {
            $query->where('assigned_agent_id', $request->assigned_agent_id);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_agent_id');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('wa_id', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'last_message_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->integer('per_page', 20);
        $conversations = $query->paginate($perPage);

        return response()->json($conversations);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::with(['phoneNumber', 'assignedAgent'])
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        $this->conversationService->markConversationAsRead($conversation);

        return response()->json($conversation);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::where('customer_id', $customer->id)
            ->findOrFail($id);

        $messages = Message::with(['sentByAgent'])
            ->where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json($messages);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'required_without:media_url|string|max:4096',
            'media_url' => 'nullable|url',
            'media_type' => 'nullable|in:image,video,document,audio',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::where('customer_id', $customer->id)
            ->findOrFail($id);

        if (!$conversation->canSendFreeformMessage()) {
            return response()->json([
                'success' => false,
                'message' => '24-hour message window has expired. Please use a template message.',
            ], 400);
        }

        $message = $this->conversationService->sendMessage(
            $conversation,
            $request->body ?? '',
            $agent->id,
            null,
            $request->media_url,
            $request->media_type ?? 'image'
        );

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'nullable|exists:wa_agents,id',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::where('customer_id', $customer->id)
            ->findOrFail($id);

        $this->conversationService->assignConversation($conversation, $request->agent_id);

        return response()->json([
            'success' => true,
            'message' => 'Conversation assigned successfully',
        ]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::where('customer_id', $customer->id)
            ->findOrFail($id);

        $this->conversationService->archiveConversation($conversation);

        return response()->json([
            'success' => true,
            'message' => 'Conversation archived',
        ]);
    }

    public function block(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $conversation = Conversation::where('customer_id', $customer->id)
            ->findOrFail($id);

        $this->conversationService->blockConversation($conversation);

        return response()->json([
            'success' => true,
            'message' => 'Conversation blocked',
        ]);
    }
}
