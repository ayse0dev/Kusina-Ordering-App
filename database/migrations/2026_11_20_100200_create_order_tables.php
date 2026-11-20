<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('customer_name');
            $t->string('customer_phone', 30);
            $t->string('order_type', 20);          // dine_in | pickup | delivery
            $t->string('table_number', 20)->nullable();
            $t->text('delivery_address')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 30)->default('pending')->index();
            $t->string('payment_method', 20);      // counter | online
            $t->string('payment_status', 20)->default('unpaid'); // unpaid | paid | failed
            $t->string('paymongo_session_id')->nullable()->index();
            $t->decimal('subtotal', 10, 2);
            $t->decimal('delivery_fee', 10, 2)->default(0);
            $t->decimal('total', 10, 2);
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->decimal('unit_price', 10, 2);
            $t->unsignedSmallInteger('qty');
            $t->decimal('line_total', 10, 2);
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('order_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->string('group_name');
            $t->string('name');
            $t->decimal('price_delta', 10, 2)->default(0);
        });

        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('status', 30);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
