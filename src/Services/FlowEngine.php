<?php

namespace WhatsApp\Business\Services;

use WhatsApp\Business\Models\Flow;
use WhatsApp\Business\Models\FlowStep;
use WhatsApp\Business\Models\FlowUserData;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class FlowEngine
{
    public function __construct(
        private WhatsAppCloudService $whatsAppService
    ) {}

    public function startFlow(Flow $flow, Conversation $conversation): ?FlowUserData
    {
        $startStep = $flow->getStartStep();
        if (!$startStep) {
            Log::warning('Flow has no start step', ['flow_id' => $flow->id]);
            return null;
        }

        $userData = FlowUserData::create([
            'customer_id' => $conversation->customer_id,
            'conversation_id' => $conversation->id,
            'flow_id' => $flow->id,
            'variables' => [],
            'current_step' => 0,
            'current_step_id' => $startStep->step_id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMinutes($flow->timeout_minutes),
        ]);

        Event::dispatch(new \WhatsApp\Business\Events\FlowStarted($userData));

        $this->executeStep($userData, $startStep);

        return $userData;
    }

    public function processResponse(FlowUserData $userData, string $response): ?FlowStep
    {
        $currentStep = FlowStep::where('flow_id', $userData->flow_id)
            ->where('step_id', $userData->current_step_id)
            ->first();

        if (!$currentStep) {
            return null;
        }

        if ($currentStep->collected_variable) {
            $userData->setVariable($currentStep->collected_variable, $response);
            $userData->save();
        }

        $nextStep = $this->determineNextStep($currentStep, $response);

        if ($nextStep) {
            $userData->current_step_id = $nextStep->step_id;
            $userData->current_step = $currentStep->step_order + 1;
            $userData->save();

            $this->executeStep($userData, $nextStep);
        } else {
            $userData->complete();
            Event::dispatch(new \WhatsApp\Business\Events\FlowCompleted($userData));
        }

        return $nextStep;
    }

    public function executeStep(FlowUserData $userData, FlowStep $step): void
    {
        $conversation = $userData->conversation;
        $customer = $conversation->customer;
        $phoneNumber = $conversation->phoneNumber;

        switch ($step->step_type) {
            case 'message':
                $this->sendMessageStep($userData, $step, $customer, $phoneNumber, $conversation);
                break;

            case 'question':
                $this->sendMessageStep($userData, $step, $customer, $phoneNumber, $conversation);
                break;

            case 'action':
                $this->executeActionStep($userData, $step);
                $this->moveToNextStep($userData, $step);
                break;

            case 'delay':
                $this->executeDelayStep($userData, $step);
                break;

            case 'api_call':
                $this->executeApiCallStep($userData, $step);
                $this->moveToNextStep($userData, $step);
                break;

            case 'ticket':
                $this->createTicketStep($userData, $step);
                $this->moveToNextStep($userData, $step);
                break;

            case 'end':
                $userData->complete();
                Event::dispatch(new \WhatsApp\Business\Events\FlowCompleted($userData));
                break;
        }
    }

    private function sendMessageStep(
        FlowUserData $userData,
        FlowStep $step,
        Customer $customer,
        PhoneNumber $phoneNumber,
        Conversation $conversation
    ): void {
        $content = $this->replaceVariables($step->getMessageText() ?? '', $userData->getAllVariables());
        
        $buttons = $step->getButtons();
        
        if (!empty($buttons)) {
            $formattedButtons = array_map(function ($button) use ($userData) {
                return [
                    'id' => $button['id'] ?? uniqid('btn_'),
                    'title' => $this->replaceVariables($button['title'] ?? '', $userData->getAllVariables()),
                ];
            }, $buttons);

            $this->whatsAppService->sendInteractiveButtonsMessage(
                $customer,
                $phoneNumber,
                $conversation->wa_id,
                $content,
                $formattedButtons
            );
        } else {
            $this->whatsAppService->sendTextMessage(
                $customer,
                $phoneNumber,
                $conversation->wa_id,
                $content
            );
        }
    }

    private function executeActionStep(FlowUserData $userData, FlowStep $step): void
    {
        $actions = $step->actions ?? [];
        
        foreach ($actions as $action) {
            match ($action['type'] ?? '') {
                'set_variable' => $this->setVariableAction($userData, $action),
                'webhook' => $this->webhookAction($userData, $action),
                'notify_agent' => $this->notifyAgentAction($userData, $action),
                default => Log::warning('Unknown action type', ['action' => $action]),
            };
        }
    }

    private function executeDelayStep(FlowUserData $userData, FlowStep $step): void
    {
        $delaySeconds = $step->content['delay'] ?? 0;
        
        if ($delaySeconds > 0) {
            $userData->update(['expires_at' => now()->addSeconds($delaySeconds)]);
        }
        
        $this->moveToNextStep($userData, $step);
    }

    private function executeApiCallStep(FlowUserData $userData, FlowStep $step): void
    {
        $config = $step->actions ?? [];
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders($config['headers'] ?? [])
                ->{$config['method'] ?? 'post'}(
                    $config['url'] ?? '',
                    $this->replaceVariablesInArray($config['body'] ?? [], $userData->getAllVariables())
                );

            if (isset($config['save_response_as'])) {
                $userData->setVariable($config['save_response_as'], $response->json());
                $userData->save();
            }
        } catch (\Exception $e) {
            Log::error('API call step failed', [
                'flow_user_data_id' => $userData->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createTicketStep(FlowUserData $userData, FlowStep $step): void
    {
        $config = $step->actions ?? [];
        
        $ticketNumber = \WhatsApp\Business\Models\Ticket::generateTicketNumber();
        
        $ticket = \WhatsApp\Business\Models\Ticket::create([
            'customer_id' => $userData->customer_id,
            'conversation_id' => $userData->conversation_id,
            'ticket_number' => $ticketNumber,
            'subject' => $this->replaceVariables($config['subject'] ?? 'Support Request', $userData->getAllVariables()),
            'description' => $this->replaceVariables($config['description'] ?? '', $userData->getAllVariables()),
            'priority' => $config['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        Event::dispatch(new \WhatsApp\Business\Events\TicketCreated($ticket));
    }

    private function moveToNextStep(FlowUserData $userData, FlowStep $currentStep): void
    {
        $nextStep = $currentStep->nextStep;
        
        if ($nextStep) {
            $userData->update([
                'current_step_id' => $nextStep->step_id,
                'current_step' => $nextStep->step_order,
            ]);
            
            $this->executeStep($userData, $nextStep);
        } else {
            $userData->complete();
            Event::dispatch(new \WhatsApp\Business\Events\FlowCompleted($userData));
        }
    }

    private function determineNextStep(FlowStep $currentStep, string $response): ?FlowStep
    {
        if ($currentStep->step_type === 'condition') {
            $branches = $currentStep->branches ?? [];
            
            foreach ($branches as $branch) {
                if ($this->evaluateCondition($branch['condition'] ?? '', $response)) {
                    return FlowStep::where('flow_id', $currentStep->flow_id)
                        ->where('step_id', $branch['next_step_id'])
                        ->first();
                }
            }
        }

        return $currentStep->nextStep;
    }

    private function evaluateCondition(string $condition, string $response): bool
    {
        $condition = trim($condition);
        
        if (str_starts_with($condition, 'equals:')) {
            $expected = trim(substr($condition, 7));
            return strtolower($response) === strtolower($expected);
        }
        
        if (str_starts_with($condition, 'contains:')) {
            $keyword = trim(substr($condition, 8));
            return stripos($response, $keyword) !== false;
        }
        
        if (str_starts_with($condition, 'matches:')) {
            $pattern = trim(substr($condition, 8));
            return (bool) preg_match($pattern, $response);
        }
        
        if ($condition === 'any') {
            return true;
        }
        
        return false;
    }

    private function setVariableAction(FlowUserData $userData, array $action): void
    {
        $userData->setVariable(
            $action['name'] ?? '',
            $action['value'] ?? ''
        );
        $userData->save();
    }

    private function webhookAction(FlowUserData $userData, array $action): void
    {
        try {
            \Illuminate\Support\Facades\Http::timeout(30)
                ->post($action['url'] ?? '', [
                    'flow_id' => $userData->flow_id,
                    'conversation_id' => $userData->conversation_id,
                    'variables' => $userData->getAllVariables(),
                ]);
        } catch (\Exception $e) {
            Log::error('Webhook action failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyAgentAction(FlowUserData $userData, array $action): void
    {
        Event::dispatch(new \WhatsApp\Business\Events\FlowRequiresAgent($userData, $action['message'] ?? ''));
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }
        
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);
        foreach ($matches[1] ?? [] as $index) {
            $text = str_replace('{{' . $index . '}}', $variables[$index - 1] ?? '', $text);
        }
        
        return $text;
    }

    private function replaceVariablesInArray(array $data, array $variables): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->replaceVariablesInArray($value, $variables);
            } elseif (is_string($value)) {
                $result[$key] = $this->replaceVariables($value, $variables);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}
