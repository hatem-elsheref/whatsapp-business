<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\Message;
use WhatsApp\Business\Models\Agent;
use WhatsApp\Business\Models\AnalyticsEvent;

class AnalyticsController
{
    public function overview(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $startDate = $request->get('start_date', now()->subDays(7)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());
        $phoneNumberId = $request->get('phone_number_id');

        $query = Message::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        }

        $totalMessages = $query->count();
        $inboundMessages = (clone $query)->where('direction', 'inbound')->count();
        $outboundMessages = (clone $query)->where('direction', 'outbound')->count();
        $deliveredMessages = (clone $query)->whereIn('status', ['delivered', 'read'])->count();
        $failedMessages = (clone $query)->where('status', 'like', '%failed%')->count();

        $activeConversations = Conversation::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();

        $unassignedConversations = Conversation::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereNull('assigned_agent_id')
            ->count();

        $avgResponseTime = $this->calculateAverageResponseTime($customer->id, $startDate, $endDate, $phoneNumberId);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'messages' => [
                    'total' => $totalMessages,
                    'inbound' => $inboundMessages,
                    'outbound' => $outboundMessages,
                    'delivered' => $deliveredMessages,
                    'failed' => $failedMessages,
                    'delivery_rate' => $totalMessages > 0 
                        ? round(($deliveredMessages / $totalMessages) * 100, 1) 
                        : 0,
                ],
                'conversations' => [
                    'active' => $activeConversations,
                    'unassigned' => $unassignedConversations,
                ],
                'performance' => [
                    'avg_response_time_minutes' => round($avgResponseTime, 1),
                ],
            ],
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());
        $phoneNumberId = $request->get('phone_number_id');
        $groupBy = $request->get('group_by', 'day');

        $query = Message::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        }

        $format = match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $dailyStats = Message::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($phoneNumberId, fn($q) => $q->where('phone_number_id', $phoneNumberId))
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as date")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound")
            ->selectRaw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dailyStats,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        $totalConversations = Conversation::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $activeConversations = Conversation::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'active')
            ->count();

        $archivedConversations = Conversation::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'archived')
            ->count();

        $avgDuration = Conversation::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('last_message_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, last_message_at)) as avg_duration')
            ->value('avg_duration');

        $byPhoneNumber = Conversation::where('wa_conversations.customer_id', $customer->id)
            ->whereBetween('wa_conversations.created_at', [$startDate, $endDate])
            ->join('wa_phone_numbers', 'wa_conversations.phone_number_id', '=', 'wa_phone_numbers.id')
            ->selectRaw('wa_phone_numbers.display_number, COUNT(*) as count')
            ->groupBy('wa_phone_numbers.display_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalConversations,
                'active' => $activeConversations,
                'archived' => $archivedConversations,
                'avg_duration_minutes' => round($avgDuration ?? 0, 1),
                'by_phone_number' => $byPhoneNumber,
            ],
        ]);
    }

    public function agents(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        $agents = Agent::where('customer_id', $customer->id)
            ->withCount([
                'assignedTickets as open_tickets_count' => fn($q) => $q->where('status', 'open'),
                'assignedTickets as resolved_tickets_count' => fn($q) => $q->where('status', 'resolved'),
            ])
            ->get();

        $agentStats = [];
        foreach ($agents as $agentItem) {
            $messages = Message::where('sent_by_agent_id', $agentItem->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $avgResponseTime = $this->calculateAgentResponseTime($agentItem->id, $startDate, $endDate);

            $conversations = Conversation::where('assigned_agent_id', $agentItem->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $agentStats[] = [
                'id' => $agentItem->id,
                'name' => $agentItem->name,
                'role' => $agentItem->role,
                'messages_sent' => $messages,
                'conversations_handled' => $conversations,
                'open_tickets' => $agentItem->open_tickets_count ?? 0,
                'resolved_tickets' => $agentItem->resolved_tickets_count ?? 0,
                'avg_response_time_minutes' => round($avgResponseTime, 1),
                'is_online' => $agentItem->last_active_at && $agentItem->last_active_at->diffInMinutes(now()) < 5,
            ];
        }

        usort($agentStats, fn($a, $b) => $b['messages_sent'] <=> $a['messages_sent']);

        return response()->json([
            'success' => true,
            'agents' => $agentStats,
        ]);
    }

    private function calculateAverageResponseTime(int $customerId, $startDate, $endDate, $phoneNumberId = null): float
    {
        $query = Message::where('customer_id', $customerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('direction', 'outbound')
            ->whereNotNull('sent_by_agent_id');

        if ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        }

        $avgMinutes = $query->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, 
            (SELECT MAX(m2.created_at) FROM wa_messages m2 
             WHERE m2.conversation_id = wa_messages.conversation_id 
             AND m2.direction = "inbound" 
             AND m2.created_at < wa_messages.created_at)
            , wa_messages.created_at)) as response_time')
            ->value('response_time');

        return $avgMinutes ?? 0;
    }

    private function calculateAgentResponseTime(int $agentId, $startDate, $endDate): float
    {
        $avgMinutes = Message::where('sent_by_agent_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('direction', 'outbound')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, 
                (SELECT MAX(m2.created_at) FROM wa_messages m2 
                 WHERE m2.conversation_id = wa_messages.conversation_id 
                 AND m2.direction = "inbound" 
                 AND m2.created_at < wa_messages.created_at)
                , wa_messages.created_at)) as response_time')
            ->value('response_time');

        return $avgMinutes ?? 0;
    }
}
