<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reservations', 'orders'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('payment_account', 20)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['reservations', 'orders'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('payment_account'));
        }
    }
};
