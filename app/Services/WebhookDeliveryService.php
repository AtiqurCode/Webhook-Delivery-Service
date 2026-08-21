<?php

namespace App\Services;

use App\Models\Delivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDeliveryService
{
    private const TIMEOUT_SECONDS = 5;

    /**
     * Attempt to deliver an event to a webhook endpoint and record the result.
     */
    public function attempt(Delivery $delivery): void
    {
        $delivery->loadMissing(['event', 'webhookEndpoint']);
        $event = $delivery->event;
        $endpoint = $delivery->webhookEndpoint;

        $delivery->increment('attempt_count');

        $body = json_encode([
            'id' => $event->event_id,
            'event' => $event->event_type,
            'data' => $event->payload,
        ]) ?: '{}';

        // Sign the exact bytes we send so the receiver can verify the
        // request actually came from us (and wasn't tampered with) by
        // recomputing the same HMAC over the raw body with their secret.
        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withBody($body, 'application/json')
                ->withHeaders(['X-Webhook-Signature' => "sha256={$signature}"])
                ->post($endpoint->url);

            if ($response->successful()) {
                $delivery->update([
                    'status' => Delivery::STATUS_SUCCESS,
                    'http_status' => $response->status(),
                    'last_error' => null,
                    'delivered_at' => now(),
                ]);

                return;
            }

            $this->recordFailure($delivery, "Webhook returned HTTP {$response->status()}", $response->status());
        } catch (ConnectionException $e) {
            $this->recordFailure($delivery, "Connection failure: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure($delivery, $e->getMessage());
        }
    }

    /**
     * Record a failed attempt. The delivery stays "pending" as long as
     * attempts remain so the job knows to retry it; it only becomes
     * "failed" once the retry budget is exhausted.
     */
    private function recordFailure(Delivery $delivery, string $message, ?int $httpStatus = null): void
    {
        $delivery->update([
            'status' => $delivery->attempt_count >= Delivery::MAX_ATTEMPTS
                ? Delivery::STATUS_FAILED
                : Delivery::STATUS_PENDING,
            'http_status' => $httpStatus,
            'last_error' => $message,
        ]);
    }
}
