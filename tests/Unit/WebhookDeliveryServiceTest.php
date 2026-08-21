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

test('the request is signed with an HMAC of the raw body using the endpoint secret', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $endpoint = WebhookEndpoint::factory()->create();
    $event = Event::factory()->create();
    $delivery = Delivery::factory()->create([
        'event_id' => $event->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    app(WebhookDeliveryService::class)->attempt($delivery);

    Http::assertSent(function ($request) use ($endpoint, $event) {
        $expectedBody = json_encode([
            'id' => $event->event_id,
            'event' => $event->event_type,
            'data' => $event->payload,
        ]);
        $expectedSignature = 'sha256='.hash_hmac('sha256', $expectedBody, $endpoint->secret);

        return $request->body() === $expectedBody
            && $request->header('X-Webhook-Signature')[0] === $expectedSignature;
    });
});

test('a fresh webhook endpoint always has a signing secret', function () {
    expect(WebhookEndpoint::factory()->create()->secret)
        ->toBeString()
        ->not->toBeEmpty();
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
