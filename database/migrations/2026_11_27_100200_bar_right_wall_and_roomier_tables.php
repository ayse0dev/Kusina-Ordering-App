<?php

use Database\Seeders\FloorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (FloorSeeder::layout() as $def) {
            $floor = DB::table('floors')->where('name', $def['name'])->first();
            if (! $floor) {
                continue;
            }
            DB::table('floors')->where('id', $floor->id)->update(['zones' => json_encode($def['zones'])]);
            foreach ($def['tables'] as $row) {
                [$label, , , $x, $y, $w, $h] = $row;
                DB::table('dining_tables')->where('floor_id', $floor->id)->where('label', $label)->update(compact('x', 'y', 'w', 'h'));
            }
        }
    }

    public function down(): void {}
};
