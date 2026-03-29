<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\Ticket;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\TicketMessage;

class TicketController
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $query = Ticket::with(['assignedAgent', 'conversation'])
            ->where('customer_id', $customer->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_agent_id')) {
            $query->where('assigned_agent_id', $request->assigned_agent_id);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_agent_id');
        }

        if ($request->boolean('my_tickets')) {
            $query->where('assigned_agent_id', $agent->id);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->integer('per_page', 20);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'nullable|exists:wa_conversations,id',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $ticketNumber = Ticket::generateTicketNumber();

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'conversation_id' => $request->conversation_id,
            'ticket_number' => $ticketNumber,
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
            'created_by_agent_id' => $agent->id,
        ]);

        if ($request->conversation_id) {
            $conversation = Conversation::find($request->conversation_id);
            if ($conversation) {
                $ticket->update([
                    'assigned_agent_id' => $conversation->assigned_agent_id,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket' => $ticket->load(['assignedAgent', 'conversation']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::with(['assignedAgent', 'createdBy', 'conversation', 'messages.agent'])
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:open,pending,on_hold,resolved,closed',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::where('customer_id', $customer->id)
            ->findOrFail($id);

        $updateData = array_filter([
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => $request->status,
        ], fn($v) => $v !== null);

        $ticket->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'ticket' => $ticket->fresh()->load(['assignedAgent']),
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'nullable|exists:wa_agents,id',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::where('customer_id', $customer->id)
            ->findOrFail($id);

        $ticket->update(['assigned_agent_id' => $request->agent_id]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::where('customer_id', $customer->id)
            ->findOrFail($id);

        $ticket->markAsResolved($agent->id, $request->notes);

        return response()->json([
            'success' => true,
            'message' => 'Ticket resolved successfully',
            'ticket' => $ticket->fresh(),
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::where('customer_id', $customer->id)
            ->findOrFail($id);

        $ticket->markAsClosed($agent->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully',
            'ticket' => $ticket->fresh(),
        ]);
    }

    public function addMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'is_internal' => 'nullable|boolean',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $ticket = Ticket::where('customer_id', $customer->id)
            ->findOrFail($id);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'agent_id' => $agent->id,
            'type' => $request->boolean('is_internal') ? 'internal' : 'note',
            'content' => $request->content,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        $ticket->recordFirstResponse();

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }
}
