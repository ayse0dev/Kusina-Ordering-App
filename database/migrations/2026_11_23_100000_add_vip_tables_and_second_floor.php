<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $t) {
            $t->boolean('is_vip')->default(false)->after('is_active');
        });

        // Existing installs: add the 2nd floor (with a VIP lounge). Fresh installs get it from FloorSeeder.
        if (DB::table('floors')->exists() && ! DB::table('floors')->where('name', '2nd Floor')->exists()) {
            $floorId = DB::table('floors')->insertGetId([
                'name' => '2nd Floor', 'width' => 800, 'height' => 500,
                'sort_order' => (int) DB::table('floors')->max('sort_order') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // label, seats, shape, x, y, w, h, vip
            $tables = [
                ['U1', 4, 'rect', 40, 40, 100, 80, false], ['U2', 4, 'rect', 190, 40, 100, 80, false],
                ['U3', 4, 'rect', 40, 180, 100, 80, false], ['U4', 4, 'rect', 190, 180, 100, 80, false],
                ['U5', 4, 'round', 60, 330, 100, 100, false], ['U6', 6, 'rect', 220, 330, 180, 90, false],
                ['V1', 6, 'round', 540, 40, 120, 120, true], ['V2', 8, 'rect', 540, 200, 200, 100, true],
                ['V3', 10, 'rect', 500, 350, 280, 100, true],
            ];
            foreach ($tables as [$label, $seats, $shape, $x, $y, $w, $h, $vip]) {
                DB::table('dining_tables')->insert([
                    'floor_id' => $floorId, 'label' => $label, 'seats' => $seats, 'shape' => $shape,
                    'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'is_active' => true, 'is_vip' => $vip,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $floor = DB::table('floors')->where('name', '2nd Floor')->first();
        if ($floor) {
            DB::table('dining_tables')->where('floor_id', $floor->id)->delete();
            DB::table('floors')->where('id', $floor->id)->delete();
        }
        Schema::table('dining_tables', fn (Blueprint $t) => $t->dropColumn('is_vip'));
    }
};
