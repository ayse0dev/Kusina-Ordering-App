<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $floor = DB::table('floors')->where('name', '2nd Floor')->value('id');
        $table = $floor ? DB::table('dining_tables')->where('floor_id', $floor)->where('label', 'U4')->first() : null;
        if (! $table) {
            return;
        }
        if (DB::table('reservations')->where('dining_table_id', $table->id)->exists()) {
            DB::table('dining_tables')->where('id', $table->id)->update(['is_active' => false]);
        } else {
            DB::table('dining_tables')->where('id', $table->id)->delete();
        }
    }

    public function down(): void {}
};
