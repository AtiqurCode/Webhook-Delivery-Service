<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $deliveries = Delivery::with(['event', 'webhookEndpoint'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('event_id'), fn ($query) => $query->whereRelation('event', 'event_id', $request->string('event_id')))
            ->latest()
            ->paginate(25);

        return DeliveryResource::collection($deliveries);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        return DeliveryResource::make($delivery->load(['event', 'webhookEndpoint']));
    }
}
