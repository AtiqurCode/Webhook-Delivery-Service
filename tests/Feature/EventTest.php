<?php

use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;

test('creating an event delivers it to every active webhook endpoint', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $active = WebhookEndpoint::factory()->count(2)->create(['is_active' => true]);
    WebhookEndpoint::factory()->create(['is_active' => false]);

    $response = $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.event_type', 'user.created')
        ->assertJsonPath('data.deliveries_count', 2);

    $event = Event::firstWhere('event_type', 'user.created');

    expect(Delivery::where('event_id', $event->id)->pluck('webhook_endpoint_id')->sort()->values()->all())
        ->toBe($active->pluck('id')->sort()->values()->all());

    Http::assertSentCount(2);
});

test('an event requires an event type and a data payload', function () {
    $this->postJson('/api/events', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['event_type', 'data']);
});

test('submitting the same event id twice does not create duplicate deliveries', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    WebhookEndpoint::factory()->create(['is_active' => true]);

    $payload = [
        'event_id' => 'evt_fixed_123',
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ];

    $this->postJson('/api/events', $payload)->assertCreated();
    $this->postJson('/api/events', $payload)->assertCreated();

    expect(Event::where('event_id', 'evt_fixed_123')->count())->toBe(1);
    expect(Delivery::count())->toBe(1);
    Http::assertSentCount(1);
});

test('a disabled endpoint does not receive new events', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    WebhookEndpoint::factory()->create(['is_active' => false]);

    $this->postJson('/api/events', [
        'event_type' => 'user.created',
        'data' => ['id' => 1],
    ])->assertCreated();

    expect(Delivery::count())->toBe(0);
    Http::assertNothingSent();
});
