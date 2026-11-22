<?php

namespace Database\Seeders;

use App\Models\Floor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FloorSeeder extends Seeder
{
    public function run(): void
    {
        if (Floor::exists()) {
            return;
        }

        $this->rebuild();
    }

    public function rebuild(): void
    {
        DB::transaction(function () {
            Floor::query()->delete();

            foreach (self::layout() as $i => $def) {
                $floor = Floor::create([
                    'name' => $def['name'], 'width' => 800, 'height' => 620, 'sort_order' => $i,
                    'outline' => $def['outline'], 'zones' => $def['zones'],
                ]);

                foreach ($def['tables'] as $row) {
                    [$label, $seats, $shape, $x, $y, $w, $h] = $row;
                    $floor->tables()->create(compact('label', 'seats', 'shape', 'x', 'y', 'w', 'h') + [
                        'is_active' => true, 'is_vip' => $row[7] ?? false,
                    ]);
                }
            }
        });
    }

    public static function layout(): array
    {
        $zone = fn (string $type, string $label, int $x, int $y, int $w, int $h, array $extra = []) => compact('type', 'label', 'x', 'y', 'w', 'h') + $extra;

        return [
            [
                'name' => '1st Floor',
                'outline' => [[22, 17], [444, 17], [444, 167], [777, 167], [777, 603], [149, 603], [149, 327], [22, 327]],
                'zones' => [
                    $zone('kitchen', 'Kitchen', 22, 17, 422, 150, ['door' => ['side' => 'bottom', 'from' => 370, 'to' => 422]]),
                    $zone('patio', 'Patio', 444, 30, 300, 137),
                    $zone('bathroom', 'Bathroom', 22, 167, 127, 160, ['door' => ['side' => 'right', 'from' => 230, 'to' => 290]]),
                    $zone('bar', 'Bar', 640, 167, 137, 215, ['face' => 'right']),
                    $zone('stairs', 'Stairs', 692, 382, 85, 185, ['dir' => 'up', 'note' => 'To 2nd floor']),
                    $zone('reception', 'Reception', 313, 530, 166, 73),
                    $zone('waiting', 'Waiting chairs', 165, 548, 117, 55),
                    $zone('door', 'Entrance', 513, 598, 61, 10),
                    $zone('door', 'Patio door', 564, 162, 62, 10),
                ],
                'tables' => [
                    ['A1', 2, 'rect', 210, 265, 60, 60], ['A2', 2, 'rect', 305, 265, 60, 60],
                    ['A3', 4, 'rect', 400, 265, 70, 60], ['A4', 6, 'rect', 505, 265, 70, 60],
                    ['B1', 4, 'rect', 210, 420, 70, 60], ['B2', 4, 'rect', 320, 420, 70, 60],
                    ['B3', 6, 'rect', 430, 420, 80, 60], ['B4', 6, 'rect', 550, 420, 80, 60],
                    ['P1', 2, 'round', 470, 50, 60, 60], ['P2', 2, 'round', 556, 50, 60, 60], ['P3', 4, 'round', 642, 45, 76, 76],
                ],
            ],
            [
                'name' => '2nd Floor',
                'outline' => [[22, 17], [461, 17], [461, 167], [777, 167], [777, 603], [149, 603], [149, 325], [22, 325]],
                'zones' => [
                    $zone('vip', 'VIP', 22, 17, 439, 308, ['door' => ['side' => 'right', 'from' => 225, 'to' => 285]]),
                    $zone('office', 'Office', 149, 325, 148, 278, ['note' => 'Staff only', 'door' => ['side' => 'right', 'from' => 380, 'to' => 440]]),
                    $zone('stairs', 'Stairs', 692, 382, 85, 185, ['dir' => 'down', 'note' => 'To 1st floor']),
                ],
                'tables' => [
                    ['V1', 6, 'round', 45, 45, 100, 100, true], ['V2', 8, 'rect', 200, 50, 170, 80, true], ['V3', 10, 'rect', 130, 205, 250, 85, true],
                    ['U2', 4, 'rect', 590, 215, 70, 60], ['U3', 6, 'rect', 690, 215, 80, 60],
                    ['U5', 4, 'rect', 455, 365, 75, 60], ['U6', 4, 'rect', 575, 365, 75, 60],
                    ['U7', 4, 'rect', 335, 505, 75, 60], ['U8', 6, 'rect', 450, 505, 85, 60], ['U9', 6, 'rect', 575, 505, 70, 60],
                ],
            ],
        ];
    }
}
