<?php

use App\Models\Delivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;

test('a successful webhook delivery is recorded', function () {
    Http::fake(['*' => Http::response(['received' => true], 200)]);
    WebhookEndpoint::factory()->create(['url' => 'https://good.example.com/hook']);

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    $delivery = Delivery::sole();

    expect($delivery->status)->toBe(Delivery::STATUS_SUCCESS)
        ->and($delivery->http_status)->toBe(200)
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->delivered_at)->not->toBeNull();
});

test('a non-2xx response is recorded as a failed delivery attempt', function () {
    Http::fake(['*' => Http::response('server error', 500)]);
    WebhookEndpoint::factory()->create();

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    $delivery = Delivery::sole();

    expect($delivery->status)->toBe(Delivery::STATUS_PENDING)
        ->and($delivery->http_status)->toBe(500)
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->last_error)->toContain('500');
});

test('deliveries can be listed and filtered by status', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    WebhookEndpoint::factory()->create();

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    $this->getJson('/api/deliveries?status=success')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/deliveries?status=failed')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a failed delivery can be retried', function () {
    $delivery = Delivery::factory()->create([
        'status' => Delivery::STATUS_FAILED,
        'attempt_count' => Delivery::MAX_ATTEMPTS,
        'http_status' => 500,
        'last_error' => 'Webhook returned HTTP 500',
    ]);

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $this->postJson("/api/deliveries/{$delivery->id}/retry")
        ->assertOk()
        ->assertJsonPath('data.status', Delivery::STATUS_SUCCESS);

    expect($delivery->fresh())
        ->attempt_count->toBe(Delivery::MAX_ATTEMPTS + 1)
        ->http_status->toBe(200);
});

test('a delivery that is not failed cannot be retried', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    WebhookEndpoint::factory()->create();

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    $delivery = Delivery::sole();

    expect($delivery->status)->toBe(Delivery::STATUS_SUCCESS);

    $this->postJson("/api/deliveries/{$delivery->id}/retry")
        ->assertStatus(422);
});

test('a single delivery can be viewed with event and endpoint details', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    WebhookEndpoint::factory()->create(['name' => 'CRM']);

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    $delivery = Delivery::sole();

    $this->getJson("/api/deliveries/{$delivery->id}")
        ->assertOk()
        ->assertJsonPath('data.status', Delivery::STATUS_SUCCESS)
        ->assertJsonPath('data.event.event_type', 'user.created')
        ->assertJsonPath('data.webhook_endpoint.name', 'CRM');
});
