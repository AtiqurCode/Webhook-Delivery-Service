<?php

namespace App\Models;

use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'is_active',
    ];

    // Mirrors the migration's column default so a freshly created instance
    // reflects is_active without a round-trip to the database.
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // The secret signs every outgoing delivery for this endpoint (see
        // WebhookDeliveryService); it's generated server-side rather than
        // accepted from the request so a caller can't set a weak one.
        static::creating(function (WebhookEndpoint $endpoint) {
            $endpoint->secret ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
