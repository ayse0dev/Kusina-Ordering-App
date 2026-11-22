<?php

use Database\Seeders\FloorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floors', function (Blueprint $t) {
            $t->json('outline')->nullable()->after('height');
            $t->json('zones')->nullable()->after('outline');
        });

        if (DB::table('floors')->exists() && ! DB::table('reservations')->exists()) {
            (new FloorSeeder)->rebuild();
        }
    }

    public function down(): void
    {
        Schema::table('floors', fn (Blueprint $t) => $t->dropColumn(['outline', 'zones']));
    }
};
