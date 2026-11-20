<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::exists()) {
            return;
        }

        $size = fn (array $prices) => [
            'name' => 'Size', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
            'options' => collect($prices)->map(fn ($d, $n) => ['name' => $n, 'price_delta' => $d])->values()->all(),
        ];
        $rice = [
            'name' => 'Rice', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
            'options' => [
                ['name' => 'Plain rice', 'price_delta' => 0],
                ['name' => 'Garlic rice', 'price_delta' => 15],
                ['name' => 'No rice', 'price_delta' => -20],
            ],
        ];

        $menu = [
            'Mains' => [
                ['Chicken Adobo', 'Chicken braised in soy, vinegar, garlic and bay leaf.', 165, [$rice, [
                    'name' => 'Add-ons', 'type' => 'multiple', 'is_required' => false, 'max_select' => 0,
                    'options' => [['name' => 'Egg', 'price_delta' => 20], ['name' => 'Extra sauce', 'price_delta' => 10]],
                ]]],
                ['Pork Sinigang', 'Sour tamarind soup with pork, kangkong and vegetables.', 220, [$rice]],
                ['Beef Kare-Kare', 'Oxtail and tripe in rich peanut sauce with bagoong.', 295, [$rice, [
                    'name' => 'Extras', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Extra bagoong', 'price_delta' => 15], ['name' => 'Extra veggies', 'price_delta' => 25]],
                ]]],
                ['Crispy Pata', 'Deep-fried pork knuckle with liver sauce.', 520, [$size(['Half' => 0, 'Whole' => 480])]],
                ['Sizzling Sisig', 'Chopped pork face and ears on a hot plate.', 245, [$rice]],
            ],
            'Noodles & Sides' => [
                ['Pancit Canton', 'Stir-fried egg noodles with chicken, shrimp and veggies.', 180, [$size(['Solo' => 0, 'Good for 3' => 220])]],
                ['Lumpiang Shanghai', 'Crispy pork spring rolls with sweet chili sauce.', 120, [$size(['6 pcs' => 0, '12 pcs' => 110])]],
                ['Garlic Rice', 'Fragrant sinangag.', 45, []],
            ],
            'Desserts' => [
                ['Halo-Halo', 'Shaved ice with sweet beans, fruits, leche flan and ube ice cream.', 130, [[
                    'name' => 'Toppings', 'type' => 'multiple', 'is_required' => false, 'max_select' => 3,
                    'options' => [['name' => 'Extra leche flan', 'price_delta' => 25], ['name' => 'Ube ice cream', 'price_delta' => 30], ['name' => 'Macapuno', 'price_delta' => 20]],
                ]]],
                ['Leche Flan', 'Silky caramel custard.', 95, []],
            ],
            'Drinks' => [
                ['Calamansi Juice', 'Fresh and tangy.', 70, [$size(['Regular' => 0, 'Large' => 20])]],
                ['Iced Tea', 'House-brewed.', 60, [$size(['Regular' => 0, 'Large' => 20])]],
                ['Soft Drink', 'Cola, lemon-lime or orange.', 55, [[
                    'name' => 'Flavor', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
                    'options' => [['name' => 'Cola', 'price_delta' => 0], ['name' => 'Lemon-lime', 'price_delta' => 0], ['name' => 'Orange', 'price_delta' => 0]],
                ]]],
            ],
        ];

        $ci = 0;
        foreach ($menu as $catName => $items) {
            $cat = Category::create(['name' => $catName, 'slug' => str($catName)->slug(), 'sort_order' => $ci++, 'is_active' => true]);
            foreach ($items as $ii => [$name, $desc, $price, $groups]) {
                $item = $cat->items()->create(['name' => $name, 'description' => $desc, 'base_price' => $price, 'sort_order' => $ii, 'is_available' => true]);
                foreach ($groups as $gi => $g) {
                    $group = $item->optionGroups()->create([
                        'name' => $g['name'], 'type' => $g['type'], 'is_required' => $g['is_required'],
                        'max_select' => $g['max_select'], 'sort_order' => $gi,
                    ]);
                    foreach ($g['options'] as $oi => $o) {
                        $group->options()->create($o + ['sort_order' => $oi, 'is_available' => true]);
                    }
                }
            }
        }
    }
}
