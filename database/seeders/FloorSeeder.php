<?php

namespace Database\Seeders;

use App\Models\Floor;
use Illuminate\Database\Seeder;

class FloorSeeder extends Seeder
{
    public function run(): void
    {
        if (Floor::exists()) {
            return;
        }

        $indoor = Floor::create(['name' => 'Indoor', 'width' => 800, 'height' => 500, 'sort_order' => 0]);
        $patio = Floor::create(['name' => 'Patio', 'width' => 800, 'height' => 400, 'sort_order' => 1]);
        $second = Floor::create(['name' => '2nd Floor', 'width' => 800, 'height' => 500, 'sort_order' => 2]);

        // label, seats, shape, x, y, w, h, vip
        $layouts = [
            [$indoor, [
                ['A1', 2, 'rect', 40, 40, 70, 70], ['A2', 2, 'rect', 150, 40, 70, 70], ['A3', 2, 'rect', 260, 40, 70, 70],
                ['B1', 4, 'rect', 40, 180, 100, 80], ['B2', 4, 'rect', 190, 180, 100, 80], ['B3', 4, 'round', 350, 170, 100, 100],
                ['C1', 6, 'rect', 520, 60, 160, 90], ['C2', 8, 'rect', 520, 220, 220, 100],
                ['D1', 4, 'round', 60, 360, 100, 100], ['D2', 10, 'rect', 260, 370, 280, 90],
            ]],
            [$patio, [
                ['P1', 2, 'round', 60, 60, 80, 80], ['P2', 2, 'round', 200, 60, 80, 80],
                ['P3', 4, 'round', 380, 50, 100, 100], ['P4', 6, 'rect', 560, 60, 170, 90],
                ['P5', 4, 'rect', 100, 240, 110, 80], ['P6', 8, 'rect', 340, 240, 230, 90],
            ]],
            [$second, [
                ['U1', 4, 'rect', 40, 40, 100, 80], ['U2', 4, 'rect', 190, 40, 100, 80],
                ['U3', 4, 'rect', 40, 180, 100, 80], ['U4', 4, 'rect', 190, 180, 100, 80],
                ['U5', 4, 'round', 60, 330, 100, 100], ['U6', 6, 'rect', 220, 330, 180, 90],
                // VIP lounge
                ['V1', 6, 'round', 540, 40, 120, 120, true], ['V2', 8, 'rect', 540, 200, 200, 100, true],
                ['V3', 10, 'rect', 500, 350, 280, 100, true],
            ]],
        ];

        foreach ($layouts as [$floor, $tables]) {
            foreach ($tables as $row) {
                [$label, $seats, $shape, $x, $y, $w, $h] = $row;
                $floor->tables()->create(compact('label', 'seats', 'shape', 'x', 'y', 'w', 'h') + [
                    'is_active' => true, 'is_vip' => $row[7] ?? false,
                ]);
            }
        }
    }
}
