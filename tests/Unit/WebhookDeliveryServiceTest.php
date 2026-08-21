<?php

use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDeliveryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeDelivery(): Delivery
{
    return Delivery::factory()->create([
        'event_id' => Event::factory()->create()->id,
        'webhook_endpoint_id' => WebhookEndpoint::factory()->create()->id,
    ]);
}

test('a successful response marks the delivery as delivered', function () {
    Http::fake(['*' => Http::response(['ok' => true], 202)]);

    $delivery = makeDelivery();
    app(WebhookDeliveryService::class)->attempt($delivery);

    expect($delivery->status)->toBe(Delivery::STATUS_SUCCESS)
        ->and($delivery->http_status)->toBe(202)
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->last_error)->toBeNull()
        ->and($delivery->delivered_at)->not->toBeNull();
});

test('a 4xx or 5xx response is treated as a failed attempt, not an exception', function () {
    Http::fake(['*' => Http::response('not found', 404)]);

    $delivery = makeDelivery();
    app(WebhookDeliveryService::class)->attempt($delivery);

    expect($delivery->status)->toBe(Delivery::STATUS_PENDING)
        ->and($delivery->http_status)->toBe(404)
        ->and($delivery->last_error)->toContain('404');
});

test('a connection failure is recorded with no http status', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not resolve host');
    });

    $delivery = makeDelivery();
    app(WebhookDeliveryService::class)->attempt($delivery);

    expect($delivery->status)->toBe(Delivery::STATUS_PENDING)
        ->and($delivery->http_status)->toBeNull()
        ->and($delivery->last_error)->toContain('Could not resolve host');
});

test('the delivery is marked failed for good once the maximum attempt count is reached', function () {
    Http::fake(['*' => Http::response('server error', 500)]);

    $delivery = makeDelivery();
    $service = app(WebhookDeliveryService::class);

    // Simulate the queue worker retrying this job up to the retry budget.
    for ($i = 0; $i < Delivery::MAX_ATTEMPTS; $i++) {
        $service->attempt($delivery);
    }

    expect($delivery->attempt_count)->toBe(Delivery::MAX_ATTEMPTS)
        ->and($delivery->status)->toBe(Delivery::STATUS_FAILED);
});
