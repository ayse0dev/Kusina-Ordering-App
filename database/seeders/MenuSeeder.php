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
                ['Chicken Inasal', 'Bacolod-style grilled quarter chicken marinated in lemongrass, calamansi and annatto oil.', 185, [$rice, [
                    'name' => 'Add-ons', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Extra chicken oil', 'price_delta' => 10], ['name' => 'Atchara (pickled papaya)', 'price_delta' => 15]],
                ]]],
                ['Beef Bulalo', 'Rich, slow-simmered beef shank and marrow soup with sweet corn on the cob and pechay.', 310, [$rice, [
                    'name' => 'Extras', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Extra sweet corn', 'price_delta' => 20], ['name' => 'Extra bone marrow', 'price_delta' => 60]],
                ]]],
                ['Bicol Express', 'Tender pork belly cooked in creamy coconut milk, shrimp paste and fiery green chilies.', 235, [$rice, [
                    'name' => 'Spice level', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
                    'options' => [['name' => 'Mild', 'price_delta' => 0], ['name' => 'Spicy (Traditional)', 'price_delta' => 0], ['name' => 'Extra Spicy (Siling Labuyo)', 'price_delta' => 0]],
                ]]],
                ['Inihaw na Liempo', 'Charcoal-grilled marinated pork belly with crispy edges, served with spiced toyomansi dip.', 225, [$rice, [
                    'name' => 'Dip & Sides', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Spiced pinakurat vinegar', 'price_delta' => 10], ['name' => 'Ensaladang talong (eggplant salad)', 'price_delta' => 25]],
                ]]],
                ['Pinakbet', 'Traditional vegetable medley of squash, eggplant, okra, and sitaw sautéed with pork and bagoong.', 195, [$rice, [
                    'name' => 'Options', 'type' => 'single', 'is_required' => false, 'max_select' => 1,
                    'options' => [['name' => 'Add extra bagnet toppings', 'price_delta' => 45]],
                ]]],
            ],
            'Noodles & Sides' => [
                ['Pancit Canton', 'Stir-fried egg noodles with chicken, shrimp and veggies.', 180, [$size(['Solo' => 0, 'Good for 3' => 220])]],
                ['Lumpiang Shanghai', 'Crispy pork spring rolls with sweet chili sauce.', 120, [$size(['6 pcs' => 0, '12 pcs' => 110])]],
                ['Garlic Rice', 'Fragrant sinangag.', 45, []],
                ['Pancit Palabok', 'Rice noodles in golden shrimp-annatto sauce with crushed chicharon, boiled egg and shrimp.', 190, [$size(['Solo' => 0, 'Platter (Good for 3)' => 230]), [
                    'name' => 'Toppings', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Extra chicharon crumbs', 'price_delta' => 15], ['name' => 'Extra hard-boiled egg', 'price_delta' => 20]],
                ]]],
                ["Tokwa't Baboy", 'Golden fried tofu cubes and tender pork belly bites in tangy soy-vinegar dressing with onions.', 160, [$size(['Solo' => 0, 'Sharing' => 110]), [
                    'name' => 'Spice level', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
                    'options' => [['name' => 'Regular', 'price_delta' => 0], ['name' => 'Spicy with fresh siling labuyo', 'price_delta' => 0]],
                ]]],
                ['Filipino Pork BBQ (3 Skewers)', 'Sweet and savory charcoal-grilled pork skewers glazed with traditional banana ketchup BBQ sauce.', 140, [$size(['3 skewers' => 0, '6 skewers' => 120]), [
                    'name' => 'Rice', 'type' => 'single', 'is_required' => false, 'max_select' => 1,
                    'options' => [['name' => 'Add plain rice', 'price_delta' => 25], ['name' => 'Add garlic rice (sinangag)', 'price_delta' => 40]],
                ]]],
            ],
            'Desserts' => [
                ['Halo-Halo', 'Shaved ice with sweet beans, fruits, leche flan and ube ice cream.', 130, [[
                    'name' => 'Toppings', 'type' => 'multiple', 'is_required' => false, 'max_select' => 3,
                    'options' => [['name' => 'Extra leche flan', 'price_delta' => 25], ['name' => 'Ube ice cream', 'price_delta' => 30], ['name' => 'Macapuno', 'price_delta' => 20]],
                ]]],
                ['Leche Flan', 'Silky caramel custard.', 95, []],
                ['Turon (3 pcs)', 'Crispy golden fried spring rolls filled with ripe saba bananas, jackfruit (langka) and brown sugar.', 95, [[
                    'name' => 'A la mode', 'type' => 'single', 'is_required' => false, 'max_select' => 1,
                    'options' => [['name' => 'Add scoop of Ube ice cream', 'price_delta' => 30], ['name' => 'Add scoop of Vanilla ice cream', 'price_delta' => 25]],
                ]]],
                ['Buko Pandan', 'Chilled dessert salad with young shredded coconut, pandan gelatin cubes and rich sweet cream.', 115, [[
                    'name' => 'Toppings', 'type' => 'multiple', 'is_required' => false, 'max_select' => 2,
                    'options' => [['name' => 'Extra nata de coco', 'price_delta' => 20], ['name' => 'Grated cheese topping', 'price_delta' => 15]],
                ]]],
            ],
            'Drinks' => [
                ['Calamansi Juice', 'Fresh and tangy.', 70, [$size(['Regular' => 0, 'Large' => 20])]],
                ['Iced Tea', 'House-brewed.', 60, [$size(['Regular' => 0, 'Large' => 20])]],
                ['Soft Drink', 'Cola, lemon-lime or orange.', 55, [[
                    'name' => 'Flavor', 'type' => 'single', 'is_required' => true, 'max_select' => 1,
                    'options' => [['name' => 'Cola', 'price_delta' => 0], ['name' => 'Lemon-lime', 'price_delta' => 0], ['name' => 'Orange', 'price_delta' => 0]],
                ]]],
                ["Sago't Gulaman", 'Classic Filipino cooler with caramelized brown sugar syrup (arnibal), chewy tapioca pearls and jelly.', 65, [$size(['Regular' => 0, 'Large' => 20]), [
                    'name' => 'Add-ons', 'type' => 'multiple', 'is_required' => false, 'max_select' => 1,
                    'options' => [['name' => 'Extra sago pearls', 'price_delta' => 15]],
                ]]],
            ],
        ];

        $images = [
            'Chicken Adobo' => 'menu/chicken-adobo.webp',
            'Pork Sinigang' => 'menu/pork-sinigang.webp',
            'Beef Kare-Kare' => 'menu/beef-kare-kare.webp',
            'Crispy Pata' => 'menu/crispy-pata.webp',
            'Sizzling Sisig' => 'menu/sizzling-sisig.webp',
            'Chicken Inasal' => 'menu/chicken-inasal.webp',
            'Beef Bulalo' => 'menu/beef-bulalo.webp',
            'Bicol Express' => 'menu/bicol-express.webp',
            'Inihaw na Liempo' => 'menu/inihaw-na-liempo.webp',
            'Pinakbet' => 'menu/pinakbet.webp',
            'Pancit Canton' => 'menu/pancit-canton.webp',
            'Lumpiang Shanghai' => 'menu/lumpiang-shanghai.webp',
            'Garlic Rice' => 'menu/garlic-rice.webp',
            'Pancit Palabok' => 'menu/pancit-palabok.webp',
            "Tokwa't Baboy" => 'menu/tokwat-baboy.webp',
            'Filipino Pork BBQ (3 Skewers)' => 'menu/pork-bbq.webp',
            'Halo-Halo' => 'menu/halo-halo.webp',
            'Leche Flan' => 'menu/leche-flan.webp',
            'Turon (3 pcs)' => 'menu/turon.webp',
            'Buko Pandan' => 'menu/buko-pandan.webp',
            'Calamansi Juice' => 'menu/calamansi-juice.webp',
            'Iced Tea' => 'menu/iced-tea.webp',
            'Soft Drink' => 'menu/soft-drink.webp',
            "Sago't Gulaman" => 'menu/sagot-gulaman.webp',
        ];

        $ci = 0;
        foreach ($menu as $catName => $items) {
            $cat = Category::create(['name' => $catName, 'slug' => str($catName)->slug(), 'sort_order' => $ci++, 'is_active' => true]);
            foreach ($items as $ii => [$name, $desc, $price, $groups]) {
                $item = $cat->items()->create([
                    'name' => $name,
                    'description' => $desc,
                    'base_price' => $price,
                    'image_path' => $images[$name] ?? null,
                    'sort_order' => $ii,
                    'is_available' => true,
                ]);
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
