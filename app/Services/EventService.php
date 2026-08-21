<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;

class EventService
{
    /**
     * Create an event (idempotently, by event_id) and dispatch deliveries
     * to every active webhook endpoint.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createEvent(string $eventId, string $eventType, array $payload): Event
    {
        $event = Event::firstWhere('event_id', $eventId);

        if ($event) {
            // Duplicate submission: return the existing event untouched
            // rather than creating new deliveries for it again.
            return $event;
        }

        $event = Event::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);

        WebhookEndpoint::where('is_active', true)->each(function (WebhookEndpoint $endpoint) use ($event) {
            $delivery = Delivery::create([
                'event_id' => $event->id,
                'webhook_endpoint_id' => $endpoint->id,
                'status' => Delivery::STATUS_PENDING,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        });

        return $event;
    }
}
