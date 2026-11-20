<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('provider', 20);                       // manual | uber | grab
            $t->string('status', 20)->default('pending')->index();
            $t->string('provider_delivery_id')->nullable()->index();
            $t->string('quote_id')->nullable();
            $t->decimal('quoted_fee', 10, 2)->default(0);
            $t->string('fee_source', 10)->default('flat');    // courier | flat
            $t->unsignedSmallInteger('eta_minutes')->nullable();
            $t->decimal('dropoff_lat', 10, 7)->nullable();
            $t->decimal('dropoff_lng', 10, 7)->nullable();
            $t->string('tracking_url', 500)->nullable();
            $t->string('courier_name', 100)->nullable();
            $t->string('courier_phone', 30)->nullable();
            $t->string('failure_reason', 300)->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        Schema::create('delivery_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20);
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('deliveries');
    }
};
