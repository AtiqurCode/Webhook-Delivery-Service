<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Matches Delivery::MAX_ATTEMPTS: we manually release() the job between
     * attempts, so this only caps runaway retries as a safety net.
     */
    public int $tries = 3;

    /**
     * Short, escalating delay between attempts rather than a
     * production-style exponential curve — this is a small demo queue.
     *
     * @var array<int, int>
     */
    private const RETRY_DELAYS_SECONDS = [10, 30];

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookDeliveryService $service): void
    {
        $delivery = Delivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $service->attempt($delivery);

        // Still "pending" after an attempt means it failed but has retries
        // left (see WebhookDeliveryService::recordFailure).
        if ($delivery->status === Delivery::STATUS_PENDING && $delivery->attempt_count < Delivery::MAX_ATTEMPTS) {
            $this->release(self::RETRY_DELAYS_SECONDS[$delivery->attempt_count - 1] ?? 30);
        }
    }
}
