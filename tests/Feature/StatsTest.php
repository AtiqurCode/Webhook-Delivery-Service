<?php

use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;

test('the stats endpoint summarizes endpoints, events, and deliveries by status', function () {
    [$activeA, $activeB] = WebhookEndpoint::factory()->count(2)->create(['is_active' => true]);
    $inactive = WebhookEndpoint::factory()->create(['is_active' => false]);

    $eventOne = Event::factory()->create();
    $eventTwo = Event::factory()->create();

    Delivery::factory()->create(['event_id' => $eventOne->id, 'webhook_endpoint_id' => $activeA->id, 'status' => Delivery::STATUS_SUCCESS]);
    Delivery::factory()->create(['event_id' => $eventOne->id, 'webhook_endpoint_id' => $activeB->id, 'status' => Delivery::STATUS_SUCCESS]);
    Delivery::factory()->create(['event_id' => $eventTwo->id, 'webhook_endpoint_id' => $activeA->id, 'status' => Delivery::STATUS_FAILED]);
    Delivery::factory()->create(['event_id' => $eventTwo->id, 'webhook_endpoint_id' => $inactive->id, 'status' => Delivery::STATUS_PENDING]);

    $this->getJson('/api/stats')
        ->assertOk()
        ->assertJson([
            'data' => [
                'webhook_endpoints' => ['total' => 3, 'active' => 2],
                'events' => ['total' => 2],
                'deliveries' => ['total' => 4, 'success' => 2, 'pending' => 1, 'failed' => 1],
            ],
        ]);
});
