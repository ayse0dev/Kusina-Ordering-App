<?php

namespace Database\Seeders;

use App\Models\GalleryPhoto;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        if (GalleryPhoto::exists()) {
            return;
        }

        $photos = [
            ['adobo', 'Chicken Adobo — slowly simmered in soy sauce, cane vinegar, garlic & bay leaves'],
            ['sinigang', 'Pork Sinigang — sour tamarind broth with tender pork and kangkong greens'],
            ['karekare', 'Beef Kare-Kare — savory peanut stew served with toasted bagoong alamang'],
            ['sisig', 'Sizzling Pork Sisig — crispy pork face, onions, calamansi & fresh egg on a hot plate'],
            ['crispypata', 'Crispy Pata — deep-fried crispy pork knuckle with spiced soy-vinegar dip'],
            ['pancit', 'Pancit Canton — stir-fried heritage noodles with tender chicken and vegetables'],
            ['lumpia', 'Lumpiang Shanghai — golden crispy Filipino spring rolls with sweet chili sauce'],
            ['halohalo', 'Special Halo-Halo — shaved ice loaded with ube ice cream, leche flan & sweet beans'],
            ['lecheflan', 'Leche Flan — velvety smooth caramel custard dessert'],
            ['sinangag', 'Garlic Sinangag — fragrant jasmine rice toasted with crunchy garlic'],
            ['bicolexpress', 'Bicol Express — tender pork in rich coconut cream and spicy chilies'],
            ['kamayan', 'Kamayan Salo-Salo — traditional Filipino family communal feast on banana leaves'],
        ];

        foreach ($photos as $i => [$file, $caption]) {
            GalleryPhoto::create(['path' => "gallery/{$file}.webp", 'caption' => $caption, 'sort_order' => $i + 1]);
        }
    }
}
