<?php

use Database\Seeders\FloorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (FloorSeeder::layout() as $def) {
            DB::table('floors')->where('name', $def['name'])->update(['zones' => json_encode($def['zones'])]);
        }
    }

    public function down(): void {}
};
