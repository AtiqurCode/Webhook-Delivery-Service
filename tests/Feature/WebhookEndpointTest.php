<?php

use App\Models\WebhookEndpoint;

test('a webhook endpoint can be registered', function () {
    $response = $this->postJson('/api/webhooks', [
        'name' => 'CRM',
        'url' => 'https://crm.example.com/webhooks',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'CRM')
        ->assertJsonPath('data.url', 'https://crm.example.com/webhooks')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('webhook_endpoints', [
        'name' => 'CRM',
        'is_active' => true,
    ]);
});

test('registering an endpoint requires a name and a valid url', function () {
    $response = $this->postJson('/api/webhooks', [
        'name' => '',
        'url' => 'not-a-url',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'url']);
});

test('registered endpoints can be listed', function () {
    WebhookEndpoint::factory()->count(3)->create();

    $response = $this->getJson('/api/webhooks');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('an endpoint can be disabled and re-enabled', function () {
    $endpoint = WebhookEndpoint::factory()->create(['is_active' => true]);

    $this->patchJson("/api/webhooks/{$endpoint->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($endpoint->fresh()->is_active)->toBeFalse();
});

test('an endpoint can be removed', function () {
    $endpoint = WebhookEndpoint::factory()->create();

    $this->deleteJson("/api/webhooks/{$endpoint->id}")->assertNoContent();

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
});
