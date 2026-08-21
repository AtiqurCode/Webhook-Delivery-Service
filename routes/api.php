<?php

use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

Route::apiResource('webhooks', WebhookEndpointController::class)
    ->except(['show'])
    ->parameters(['webhooks' => 'webhookEndpoint']);

Route::post('events', [EventController::class, 'store'])->name('events.store');
Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{event:event_id}', [EventController::class, 'show'])->name('events.show');

Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
Route::post('deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])->name('deliveries.retry');
