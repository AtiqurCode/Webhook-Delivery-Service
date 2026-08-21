<?php

use App\Jobs\DeliverWebhookJob;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDeliveryService;
use Illuminate\Support\Facades\Http;

test('a failing delivery is retried up to the maximum attempt count then marked failed', function () {
    Http::fake(['*' => Http::response('server error', 500)]);

    $delivery = Delivery::factory()->create([
        'event_id' => Event::factory()->create()->id,
        'webhook_endpoint_id' => WebhookEndpoint::factory()->create()->id,
    ]);

    // Each call represents one pass of the queue worker picking the
    // released job back up.
    for ($i = 0; $i < Delivery::MAX_ATTEMPTS; $i++) {
        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDeliveryService::class));
    }

    $delivery->refresh();

    expect($delivery->attempt_count)->toBe(Delivery::MAX_ATTEMPTS)
        ->and($delivery->status)->toBe(Delivery::STATUS_FAILED);

    Http::assertSentCount(Delivery::MAX_ATTEMPTS);
});

test('a delivery that succeeds on a later attempt is not retried further', function () {
    Http::fakeSequence()
        ->push('server error', 500)
        ->push(['ok' => true], 200);

    $delivery = Delivery::factory()->create([
        'event_id' => Event::factory()->create()->id,
        'webhook_endpoint_id' => WebhookEndpoint::factory()->create()->id,
    ]);

    (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDeliveryService::class));
    (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDeliveryService::class));

    $delivery->refresh();

    expect($delivery->attempt_count)->toBe(2)
        ->and($delivery->status)->toBe(Delivery::STATUS_SUCCESS);
});

test('a missing delivery is handled gracefully', function () {
    (new DeliverWebhookJob(999999))->handle(app(WebhookDeliveryService::class));
})->throwsNoExceptions();
