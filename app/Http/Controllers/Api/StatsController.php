<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * A single summary endpoint for the frontend dashboard, so it doesn't
     * need to fetch every list just to show counts.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'webhook_endpoints' => [
                    'total' => WebhookEndpoint::count(),
                    'active' => WebhookEndpoint::where('is_active', true)->count(),
                ],
                'events' => [
                    'total' => Event::count(),
                ],
                'deliveries' => [
                    'total' => Delivery::count(),
                    'success' => Delivery::where('status', Delivery::STATUS_SUCCESS)->count(),
                    'pending' => Delivery::where('status', Delivery::STATUS_PENDING)->count(),
                    'failed' => Delivery::where('status', Delivery::STATUS_FAILED)->count(),
                ],
            ],
        ]);
    }
}
