<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookEndpointRequest;
use App\Http\Requests\UpdateWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WebhookEndpointController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return WebhookEndpointResource::collection(
            WebhookEndpoint::latest()->paginate(25)
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = WebhookEndpoint::create($request->validated());

        return WebhookEndpointResource::make($endpoint)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        $webhookEndpoint->update($request->validated());

        return WebhookEndpointResource::make($webhookEndpoint);
    }

    public function destroy(WebhookEndpoint $webhookEndpoint): Response
    {
        $webhookEndpoint->delete();

        return response()->noContent();
    }
}
