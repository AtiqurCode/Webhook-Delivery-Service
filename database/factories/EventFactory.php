<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    private const EVENT_TYPES = [
        'user.created',
        'user.updated',
        'order.created',
        'order.shipped',
        'invoice.paid',
    ];

    public function definition(): array
    {
        $eventType = fake()->randomElement(self::EVENT_TYPES);

        return [
            'event_id' => 'evt_'.Str::ulid(),
            'event_type' => $eventType,
            'payload' => $this->payloadFor($eventType),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $eventType): array
    {
        return match ($eventType) {
            'order.created', 'order.shipped' => [
                'id' => fake()->randomNumber(),
                'customer_email' => fake()->safeEmail(),
                'total' => fake()->randomFloat(2, 10, 500),
                'currency' => 'USD',
            ],
            'invoice.paid' => [
                'id' => fake()->randomNumber(),
                'amount' => fake()->randomFloat(2, 10, 2000),
                'currency' => 'USD',
                'paid_at' => fake()->dateTimeBetween('-1 month')->format(DATE_ATOM),
            ],
            default => [
                'id' => fake()->randomNumber(),
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
            ],
        };
    }
}
