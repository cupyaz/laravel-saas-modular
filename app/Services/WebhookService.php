<?php

namespace App\Services;

use App\Models\Webhook;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class WebhookService
{
    /**
     * Dispatch webhook for a specific event.
     */
    public function dispatch(string $event, array $payload, ?int $userId = null, ?int $tenantId = null): void
    {
        $webhooks = $this->getWebhooksForEvent($event, $userId, $tenantId);

        foreach ($webhooks as $webhook) {
            if ($webhook->is_active && $webhook->handlesEvent($event)) {
                $this->dispatchWebhook($webhook, $event, $payload);
            }
        }
    }

    /**
     * Get webhooks that should receive this event.
     */
    protected function getWebhooksForEvent(string $event, ?int $userId = null, ?int $tenantId = null)
    {
        $query = Webhook::where('is_active', true);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->filter(function ($webhook) use ($event) {
            return $webhook->handlesEvent($event);
        });
    }

    /**
     * Dispatch individual webhook.
     */
    protected function dispatchWebhook(Webhook $webhook, string $event, array $payload): void
    {
        // Queue the webhook for asynchronous processing
        Queue::push(function ($job) use ($webhook, $event, $payload) {
            $this->sendWebhook($webhook, $event, $payload);
            $job->delete();
        });
    }

    /**
     * Send webhook HTTP request.
     */
    public function sendWebhook(Webhook $webhook, string $event, array $payload): bool
    {
        try {
            $webhookPayload = $this->buildWebhookPayload($webhook, $event, $payload);
            $headers = $this->buildWebhookHeaders($webhook, $webhookPayload);

            $response = Http::timeout($webhook->timeout ?? 30)
                ->withHeaders($headers)
                ->retry($webhook->retry_attempts ?? 3, 1000)
                ->post($webhook->url, $webhookPayload);

            if ($response->successful()) {
                $webhook->markAsTriggered();
                $webhook->resetFailures();
                
                Log::info('Webhook sent successfully', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'status' => $response->status(),
                ]);

                return true;
            } else {
                $this->handleWebhookFailure($webhook, $response, 'HTTP error: ' . $response->status());
                return false;
            }

        } catch (\Exception $e) {
            $this->handleWebhookFailure($webhook, null, $e->getMessage());
            return false;
        }
    }

    /**
     * Build webhook payload.
     */
    protected function buildWebhookPayload(Webhook $webhook, string $event, array $payload): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'webhook_id' => $webhook->id,
            'data' => $payload,
        ];
    }

    /**
     * Build webhook headers including signature.
     */
    protected function buildWebhookHeaders(Webhook $webhook, array $payload): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Laravel-Webhook/1.0',
            'X-Webhook-ID' => $webhook->id,
            'X-Webhook-Event' => $payload['event'],
            'X-Webhook-Timestamp' => $payload['timestamp'],
        ], $webhook->headers ?? []);

        // Add signature if secret is configured
        if ($webhook->secret) {
            $signature = $this->generateSignature($payload, $webhook->secret);
            $headers['X-Webhook-Signature'] = $signature;
        }

        return $headers;
    }

    /**
     * Generate webhook signature for payload verification.
     */
    protected function generateSignature(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return 'sha256=' . hash_hmac('sha256', $payloadJson, $secret);
    }

    /**
     * Handle webhook failure.
     */
    protected function handleWebhookFailure(Webhook $webhook, ?Response $response, string $reason): void
    {
        $webhook->markAsFailed($reason);

        Log::warning('Webhook failed', [
            'webhook_id' => $webhook->id,
            'url' => $webhook->url,
            'reason' => $reason,
            'status' => $response?->status(),
            'failure_count' => $webhook->failure_count,
        ]);

        // Disable webhook if too many failures
        if ($webhook->shouldBeDisabled()) {
            $webhook->update(['is_active' => false]);
            
            Log::error('Webhook disabled due to repeated failures', [
                'webhook_id' => $webhook->id,
                'failure_count' => $webhook->failure_count,
            ]);
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get available webhook events.
     */
    public static function getAvailableEvents(): array
    {
        return [
            'user.created',
            'user.updated',
            'user.deleted',
            'user.login',
            'user.logout',
            'user.password_changed',
            'user.email_verified',
            'tenant.created',
            'tenant.updated',
            'tenant.deleted',
            'subscription.created',
            'subscription.updated',
            'subscription.cancelled',
            'subscription.renewed',
            'payment.succeeded',
            'payment.failed',
            'security.suspicious_activity',
            'api.rate_limit_exceeded',
        ];
    }
}