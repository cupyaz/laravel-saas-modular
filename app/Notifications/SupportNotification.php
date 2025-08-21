<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\ChatSession;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;

class SupportNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send ticket created notification.
     */
    public function ticketCreated(SupportTicket $ticket): void
    {
        $user = $ticket->user;

        $variables = [
            'user_name' => $user->name,
            'ticket_number' => $ticket->ticket_number,
            'ticket_title' => $ticket->title,
            'priority' => $ticket->priority,
            'created_at' => $ticket->created_at->format('F j, Y \a\t g:i A'),
            'action_url' => url("/support/tickets/{$ticket->ticket_number}"),
            'action_text' => 'View Ticket',
        ];

        $this->notificationService->send(
            $user,
            'support_ticket_created',
            $variables
        );
    }

    /**
     * Send ticket replied notification.
     */
    public function ticketReplied(SupportTicket $ticket, SupportMessage $message): void
    {
        $user = $ticket->user;
        $agent = $message->user;

        // Don't notify user of their own messages
        if ($user->id === $agent->id) {
            return;
        }

        $variables = [
            'user_name' => $user->name,
            'ticket_number' => $ticket->ticket_number,
            'agent_name' => $agent->name,
            'reply_excerpt' => str($message->message)->limit(100),
            'replied_at' => $message->created_at->format('F j, Y \a\t g:i A'),
            'action_url' => url("/support/tickets/{$ticket->ticket_number}"),
            'action_text' => 'View Reply',
        ];

        $this->notificationService->send(
            $user,
            'support_ticket_replied',
            $variables,
            [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_EMAIL, NotificationTemplate::CHANNEL_PUSH]
        );
    }

    /**
     * Send ticket status changed notification.
     */
    public function ticketStatusChanged(SupportTicket $ticket, string $oldStatus): void
    {
        $user = $ticket->user;
        $agent = $ticket->assignedTo;

        $variables = [
            'user_name' => $user->name,
            'ticket_number' => $ticket->ticket_number,
            'old_status' => ucfirst(str_replace('_', ' ', $oldStatus)),
            'new_status' => ucfirst(str_replace('_', ' ', $ticket->status)),
            'agent_name' => $agent?->name ?? 'Support Team',
            'updated_at' => $ticket->updated_at->format('F j, Y \a\t g:i A'),
            'action_url' => url("/support/tickets/{$ticket->ticket_number}"),
            'action_text' => 'View Ticket',
        ];

        $templateName = match ($ticket->status) {
            SupportTicket::STATUS_RESOLVED => 'support_ticket_resolved',
            SupportTicket::STATUS_CLOSED => 'support_ticket_closed',
            default => 'support_ticket_status_changed',
        };

        $this->notificationService->send(
            $user,
            $templateName,
            $variables
        );
    }

    /**
     * Send ticket assigned notification to agent.
     */
    public function ticketAssignedToAgent(SupportTicket $ticket): void
    {
        $agent = $ticket->assignedTo;
        if (!$agent) {
            return;
        }

        $variables = [
            'agent_name' => $agent->name,
            'ticket_number' => $ticket->ticket_number,
            'ticket_title' => $ticket->title,
            'priority' => ucfirst($ticket->priority),
            'customer_name' => $ticket->user->name,
            'assigned_at' => now()->format('F j, Y \a\t g:i A'),
            'action_url' => url("/agent/tickets/{$ticket->ticket_number}"),
            'action_text' => 'Handle Ticket',
        ];

        $this->notificationService->send(
            $agent,
            'agent_ticket_assigned',
            $variables,
            [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_EMAIL]
        );
    }

    /**
     * Send ticket escalated notification.
     */
    public function ticketEscalated(SupportTicket $ticket, string $escalationReason): void
    {
        $user = $ticket->user;
        $agent = $ticket->assignedTo;

        // Notify customer
        $customerVariables = [
            'user_name' => $user->name,
            'ticket_number' => $ticket->ticket_number,
            'escalation_reason' => $escalationReason,
            'agent_name' => $agent?->name ?? 'Senior Support Team',
            'escalated_at' => now()->format('F j, Y \a\t g:i A'),
            'action_url' => url("/support/tickets/{$ticket->ticket_number}"),
            'action_text' => 'View Ticket',
        ];

        $this->notificationService->send(
            $user,
            'support_ticket_escalated',
            $customerVariables
        );

        // Notify agent if assigned
        if ($agent) {
            $agentVariables = [
                'agent_name' => $agent->name,
                'ticket_number' => $ticket->ticket_number,
                'customer_name' => $user->name,
                'escalation_reason' => $escalationReason,
                'action_url' => url("/agent/tickets/{$ticket->ticket_number}"),
                'action_text' => 'Handle Escalation',
            ];

            $this->notificationService->send(
                $agent,
                'agent_ticket_escalated',
                $agentVariables
            );
        }
    }

    /**
     * Send chat session started notification to agents.
     */
    public function chatSessionStarted(ChatSession $session): void
    {
        // This would notify available agents of new chat session
        $variables = [
            'session_id' => $session->session_id,
            'visitor_name' => $session->visitor_display_name,
            'started_at' => $session->started_at->format('g:i A'),
            'action_url' => url("/agent/chat/{$session->session_id}"),
            'action_text' => 'Join Chat',
        ];

        // Get available agents and notify them
        $availableAgents = \App\Models\SupportAgent::available()->get();
        foreach ($availableAgents as $agent) {
            $this->notificationService->send(
                $agent->user,
                'agent_chat_session_available',
                array_merge($variables, ['agent_name' => $agent->user->name]),
                [NotificationTemplate::CHANNEL_DATABASE]
            );
        }
    }

    /**
     * Send chat session assigned notification.
     */
    public function chatSessionAssigned(ChatSession $session): void
    {
        $agent = $session->agent;
        if (!$agent) {
            return;
        }

        $variables = [
            'agent_name' => $agent->name,
            'session_id' => $session->session_id,
            'visitor_name' => $session->visitor_display_name,
            'assigned_at' => now()->format('g:i A'),
            'action_url' => url("/agent/chat/{$session->session_id}"),
            'action_text' => 'Start Chat',
        ];

        $this->notificationService->send(
            $agent,
            'agent_chat_assigned',
            $variables,
            [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_PUSH]
        );
    }

    /**
     * Send satisfaction survey request.
     */
    public function requestSatisfactionSurvey(SupportTicket $ticket): void
    {
        $user = $ticket->user;
        $agent = $ticket->assignedTo;

        $variables = [
            'user_name' => $user->name,
            'ticket_number' => $ticket->ticket_number,
            'agent_name' => $agent?->name ?? 'Our Support Team',
            'resolved_at' => $ticket->resolved_at?->format('F j, Y'),
            'action_url' => url("/support/survey/{$ticket->ticket_number}"),
            'action_text' => 'Share Feedback',
        ];

        $this->notificationService->send(
            $user,
            'support_satisfaction_survey',
            $variables,
            [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_EMAIL]
        );
    }

    /**
     * Send agent daily summary notification.
     */
    public function agentDailySummary(\App\Models\SupportAgent $agent, array $summary): void
    {
        $variables = [
            'agent_name' => $agent->user->name,
            'date' => now()->format('F j, Y'),
            'tickets_handled' => $summary['tickets_handled'],
            'tickets_resolved' => $summary['tickets_resolved'],
            'chats_handled' => $summary['chats_handled'],
            'avg_response_time' => $summary['avg_response_time'],
            'customer_satisfaction' => $summary['customer_satisfaction'],
            'action_url' => url('/agent/dashboard'),
            'action_text' => 'View Dashboard',
        ];

        $this->notificationService->send(
            $agent->user,
            'agent_daily_summary',
            $variables,
            [NotificationTemplate::CHANNEL_EMAIL]
        );
    }

    /**
     * Send overdue ticket notification to agents.
     */
    public function overdueTicketsAlert(array $overdueTickets): void
    {
        $agentTickets = collect($overdueTickets)->groupBy('assigned_to');

        foreach ($agentTickets as $agentId => $tickets) {
            $agent = \App\Models\User::find($agentId);
            if (!$agent) {
                continue;
            }

            $variables = [
                'agent_name' => $agent->name,
                'overdue_count' => $tickets->count(),
                'tickets_list' => $tickets->map(fn($t) => "#{$t['ticket_number']} - {$t['title']}")->join("\n"),
                'action_url' => url('/agent/tickets?filter=overdue'),
                'action_text' => 'Review Overdue Tickets',
            ];

            $this->notificationService->send(
                $agent,
                'agent_overdue_tickets',
                $variables,
                [NotificationTemplate::CHANNEL_DATABASE, NotificationTemplate::CHANNEL_EMAIL]
            );
        }
    }
}