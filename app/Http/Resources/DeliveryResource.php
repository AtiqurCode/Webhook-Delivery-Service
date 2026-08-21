<?php

namespace App\Http\Resources;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'http_status' => $this->http_status,
            'attempt_count' => $this->attempt_count,
            'last_error' => $this->last_error,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'event' => [
                'id' => $this->event?->id,
                'event_id' => $this->event?->event_id,
                'event_type' => $this->event?->event_type,
                'payload' => $this->event?->payload,
            ],
            'webhook_endpoint' => [
                'id' => $this->webhookEndpoint?->id,
                'name' => $this->webhookEndpoint?->name,
                'url' => $this->webhookEndpoint?->url,
            ],
        ];
    }
}
