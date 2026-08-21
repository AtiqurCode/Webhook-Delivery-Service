<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'status' => Delivery::STATUS_PENDING,
            'http_status' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'delivered_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(function () {
            $deliveredAt = fake()->dateTimeBetween('-2 weeks');

            return [
                'status' => Delivery::STATUS_SUCCESS,
                'http_status' => fake()->randomElement([200, 200, 201, 202, 204]),
                'attempt_count' => 1,
                'last_error' => null,
                'delivered_at' => $deliveredAt,
                'created_at' => $deliveredAt,
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function () {
            [$httpStatus, $error] = fake()->randomElement([
                [500, 'Webhook returned HTTP 500'],
                [404, 'Webhook returned HTTP 404'],
                [503, 'Webhook returned HTTP 503'],
                [null, 'Connection failure: cURL error 28: Operation timed out after 5000 milliseconds'],
                [null, 'Connection failure: cURL error 6: Could not resolve host'],
            ]);

            return [
                'status' => Delivery::STATUS_FAILED,
                'http_status' => $httpStatus,
                'attempt_count' => Delivery::MAX_ATTEMPTS,
                'last_error' => $error,
                'delivered_at' => null,
                'created_at' => fake()->dateTimeBetween('-2 weeks'),
            ];
        });
    }

    public function retrying(): static
    {
        return $this->state(fn () => [
            'status' => Delivery::STATUS_PENDING,
            'http_status' => 500,
            'attempt_count' => fake()->numberBetween(1, Delivery::MAX_ATTEMPTS - 1),
            'last_error' => 'Webhook returned HTTP 500',
            'delivered_at' => null,
            'created_at' => fake()->dateTimeBetween('-2 weeks'),
        ]);
    }
}
