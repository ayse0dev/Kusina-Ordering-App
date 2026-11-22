<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->string('customer_email', 150)->nullable()->after('customer_phone');
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_detail', 40)->nullable();
            $t->string('payment_status', 20)->default('unpaid');
            $t->string('payment_ref', 30)->nullable();
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->timestamp('paid_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->string('customer_email', 150)->nullable()->after('customer_phone');
            $t->string('payment_detail', 40)->nullable();
            $t->string('payment_ref', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn(['customer_email', 'payment_method', 'payment_detail', 'payment_status', 'payment_ref', 'subtotal', 'tax', 'total', 'paid_at']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['customer_email', 'payment_detail', 'payment_ref']));
    }
};
