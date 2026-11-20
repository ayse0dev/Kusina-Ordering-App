<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedSmallInteger('width')->default(800);
            $t->unsignedSmallInteger('height')->default(500);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $t->string('label', 20);
            $t->unsignedTinyInteger('seats')->default(2);
            $t->string('shape', 10)->default('rect'); // rect | round
            $t->unsignedSmallInteger('x')->default(0);
            $t->unsignedSmallInteger('y')->default(0);
            $t->unsignedSmallInteger('w')->default(80);
            $t->unsignedSmallInteger('h')->default(80);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->foreignId('dining_table_id')->constrained('dining_tables')->cascadeOnDelete();
            $t->string('customer_name');
            $t->string('customer_phone', 30);
            $t->unsignedTinyInteger('party_size');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('status', 20)->default('confirmed'); // confirmed | seated | completed | cancelled | no_show
            $t->string('notes', 300)->nullable();
            $t->timestamps();
            $t->index(['dining_table_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('floors');
    }
};
