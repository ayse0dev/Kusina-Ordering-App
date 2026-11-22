<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('discount', 10, 2)->default(0)->after('delivery_fee');
            $t->decimal('tax', 10, 2)->default(0)->after('discount');
            $t->decimal('gift_applied', 10, 2)->default(0)->after('total');
            $t->decimal('amount_due', 10, 2)->default(0)->after('gift_applied');
            $t->string('stripe_session_id')->nullable()->index()->after('paymongo_session_id');
        });

        Schema::create('vouchers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('kind', 10);
            $t->string('status', 10)->default('active');
            $t->string('type', 10)->nullable();
            $t->decimal('value', 10, 2);
            $t->decimal('balance', 10, 2)->nullable();
            $t->decimal('min_spend', 10, 2)->nullable();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->string('description')->nullable();
            $t->string('buyer_name')->nullable();
            $t->string('buyer_email')->nullable();
            $t->string('recipient_name')->nullable();
            $t->string('recipient_email')->nullable();
            $t->string('message', 300)->nullable();
            $t->string('public_token', 40)->nullable()->unique();
            $t->string('stripe_session_id')->nullable()->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->timestamps();
            $t->unique(['voucher_id', 'order_id']);
        });

        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('email', 150);
            $t->string('phone', 30)->nullable();
            $t->text('message');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['discount', 'tax', 'gift_applied', 'amount_due', 'stripe_session_id']));
    }
};
