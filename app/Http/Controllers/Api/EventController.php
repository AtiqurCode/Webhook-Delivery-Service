<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    public function index(): AnonymousResourceCollection
    {
        return EventResource::collection(
            Event::withCount('deliveries')->latest()->paginate(25)
        );
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->createEvent(
            $request->eventId(),
            $request->eventType(),
            $request->eventData(),
        );

        return EventResource::make($event->loadCount('deliveries'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Event $event): EventResource
    {
        return EventResource::make($event->loadCount('deliveries'));
    }
}
