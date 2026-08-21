<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | success | failed
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'webhook_endpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
