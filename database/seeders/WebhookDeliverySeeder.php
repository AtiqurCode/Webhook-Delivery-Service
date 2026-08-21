<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;

/**
 * Fills the app with realistic-looking webhook endpoints, events, and
 * delivery history so the UI/API have something to show without needing
 * a live queue worker hitting real HTTP endpoints. Deliveries are written
 * directly with a status already assigned, rather than going through
 * EventService + the queue, since we want a settled history instantly.
 */
class WebhookDeliverySeeder extends Seeder
{
    public function run(): void
    {
        $endpoints = collect([
            ['name' => 'CRM', 'url' => 'https://crm.example.com/webhooks', 'is_active' => true],
            ['name' => 'Analytics', 'url' => 'https://analytics.example.com/ingest', 'is_active' => true],
            ['name' => 'Billing System', 'url' => 'https://billing.example.com/webhooks', 'is_active' => true],
            ['name' => 'Slack Notifications', 'url' => 'https://hooks.slack.com/services/T000/B000/XXXX', 'is_active' => true],
            ['name' => 'Legacy System (retired)', 'url' => 'https://legacy.example.com/webhooks', 'is_active' => false],
        ])->map(fn (array $attributes) => WebhookEndpoint::create($attributes));

        Event::factory()
            ->count(20)
            ->sequence(fn () => ['created_at' => fake()->dateTimeBetween('-3 weeks')])
            ->create()
            ->each(function (Event $event) use ($endpoints) {
                $targets = $endpoints->random(fake()->numberBetween(2, $endpoints->count()));

                foreach ($targets as $endpoint) {
                    $outcome = fake()->randomElement(['successful', 'successful', 'successful', 'failed', 'retrying']);

                    Delivery::factory()
                        ->{$outcome}()
                        ->create([
                            'event_id' => $event->id,
                            'webhook_endpoint_id' => $endpoint->id,
                        ]);
                }
            });
    }
}
