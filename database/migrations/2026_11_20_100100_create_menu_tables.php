<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('base_price', 10, 2);
            $t->string('image_path')->nullable();
            $t->boolean('is_available')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('option_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type', 10)->default('single');
            $t->boolean('is_required')->default(false);
            $t->unsignedSmallInteger('max_select')->default(0);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('price_delta', 10, 2)->default(0);
            $t->boolean('is_available')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('categories');
    }
};
