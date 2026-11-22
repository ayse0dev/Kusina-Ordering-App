<?php

ini_set('memory_limit', '512M');

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuItem;
use App\Models\GalleryPhoto;

echo "=== Processing Authentic Filipino Food Pictures ===\n";

$dirs = [
    public_path('images'),
    public_path('images/menu'),
    public_path('images/gallery'),
    public_path('images/about'),
    storage_path('app/public'),
    storage_path('app/public/menu'),
    storage_path('app/public/gallery'),
    public_path('storage'),
    public_path('storage/menu'),
    public_path('storage/gallery'),
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
    }
}

function downloadRaw(string $wikiFilename): ?string {
    $url = "https://commons.wikimedia.org/wiki/Special:FilePath/" . rawurlencode($wikiFilename);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'KusinaOrderingApp/1.0 (info@kusina.ca)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 45,
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data)) {
        return $data;
    }
    return null;
}

function processAndSaveWebp(string $raw, string $relativeName, int $maxW = 900, int $maxH = 900): bool {
    $src = @imagecreatefromstring($raw);
    if (!$src) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, min($maxW / $w, $maxH / $h));
    $dstW = (int) round($w * $scale);
    $dstH = (int) round($h * $scale);

    $dst = imagecreatetruecolor($dstW, $dstH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);

    ob_start();
    imagewebp($dst, null, 85);
    $webp = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    file_put_contents(storage_path('app/public/' . $relativeName), $webp);
    file_put_contents(public_path('storage/' . $relativeName), $webp);
    file_put_contents(public_path('images/' . $relativeName), $webp);

    return true;
}

function processAndSaveJpg(string $raw, string $destFullPath, int $maxW = 1920, int $maxH = 1080): bool {
    $src = @imagecreatefromstring($raw);
    if (!$src) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, min($maxW / $w, $maxH / $h));
    $dstW = (int) round($w * $scale);
    $dstH = (int) round($h * $scale);

    $dst = imagecreatetruecolor($dstW, $dstH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);

    ob_start();
    imagejpeg($dst, null, 88);
    $jpg = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    file_put_contents($destFullPath, $jpg);
    return true;
}

echo "1. Fetching Hero and About feast photo (Kamayan)...\n";
$kamayanRaw = downloadRaw('Boodle_fight_(kamayan)_01.jpg');
if ($kamayanRaw) {
    processAndSaveJpg($kamayanRaw, public_path('images/hero.jpg'), 1920, 1080);
    processAndSaveJpg($kamayanRaw, public_path('images/about/feast.jpg'), 1200, 800);
    echo " -> Hero and About photos saved.\n";
} else {
    echo " -> Failed to download Kamayan raw image.\n";
}

$menuMap = [
    'Chicken Adobo' => [
        'wiki' => 'Chicken_adobo.jpg',
        'file' => 'menu/chicken-adobo.webp',
    ],
    'Pork Sinigang' => [
        'wiki' => 'Sinigang_na_Baboy.jpg',
        'file' => 'menu/pork-sinigang.webp',
    ],
    'Beef Kare-Kare' => [
        'wiki' => 'Kare-kare.jpg',
        'file' => 'menu/beef-kare-kare.webp',
    ],
    'Crispy Pata' => [
        'wiki' => 'Crispy_pata.jpg',
        'file' => 'menu/crispy-pata.webp',
    ],
    'Sizzling Sisig' => [
        'wiki' => 'Sizzling_Sisig.jpg',
        'file' => 'menu/sizzling-sisig.webp',
    ],
    'Pancit Canton' => [
        'wiki' => 'Pancit_Canton.jpg',
        'file' => 'menu/pancit-canton.webp',
    ],
    'Lumpiang Shanghai' => [
        'wiki' => 'Lumpiang_Shanghai.jpg',
        'file' => 'menu/lumpiang-shanghai.webp',
    ],
    'Garlic Rice' => [
        'wiki' => 'Sinangag_(garlic_rice)_from_the_Philippines,_with_egg_01.jpg',
        'file' => 'menu/garlic-rice.webp',
    ],
    'Halo-Halo' => [
        'wiki' => 'Halo-Halo.jpg',
        'file' => 'menu/halo-halo.webp',
    ],
    'Leche Flan' => [
        'wiki' => 'Leche_flan.jpg',
        'file' => 'menu/leche-flan.webp',
    ],
    'Calamansi Juice' => [
        'wiki' => 'Calamansi_juice_(Philippines).jpg',
        'file' => 'menu/calamansi-juice.webp',
    ],
    'Iced Tea' => [
        'wiki' => 'Lipton_Ice_Tea_glass,_Winschoten_(2018)_01.jpg',
        'file' => 'menu/iced-tea.webp',
    ],
    'Soft Drink' => [
        'wiki' => 'Glass_of_Cola.jpg',
        'file' => 'menu/soft-drink.webp',
    ],
];

echo "2. Processing 13 Menu Items...\n";
foreach ($menuMap as $name => $item) {
    echo " -> {$name}... ";
    $raw = downloadRaw($item['wiki']);
    if ($raw && processAndSaveWebp($raw, $item['file'], 900, 900)) {
        MenuItem::where('name', $name)->update(['image_path' => $item['file']]);
        echo "OK\n";
    } else {
        echo "FAILED\n";
    }
    unset($raw);
    gc_collect_cycles();
}

echo "3. Populating 12 Gallery Photos...\n";
$galleryMap = [
    [
        'wiki' => 'Chicken_adobo.jpg',
        'file' => 'gallery/adobo.webp',
        'caption' => 'Chicken Adobo — slowly simmered in soy sauce, cane vinegar, garlic & bay leaves',
    ],
    [
        'wiki' => 'Sinigang_na_Baboy.jpg',
        'file' => 'gallery/sinigang.webp',
        'caption' => 'Pork Sinigang — sour tamarind broth with tender pork and kangkong greens',
    ],
    [
        'wiki' => 'Kare-kare.jpg',
        'file' => 'gallery/karekare.webp',
        'caption' => 'Beef Kare-Kare — savory peanut stew served with toasted bagoong alamang',
    ],
    [
        'wiki' => 'Sizzling_Sisig.jpg',
        'file' => 'gallery/sisig.webp',
        'caption' => 'Sizzling Pork Sisig — crispy pork face, onions, calamansi & fresh egg on a hot plate',
    ],
    [
        'wiki' => 'Crispy_pata.jpg',
        'file' => 'gallery/crispypata.webp',
        'caption' => 'Crispy Pata — deep-fried crispy pork knuckle with spiced soy-vinegar dip',
    ],
    [
        'wiki' => 'Pancit_Canton.jpg',
        'file' => 'gallery/pancit.webp',
        'caption' => 'Pancit Canton — stir-fried heritage noodles with tender chicken and vegetables',
    ],
    [
        'wiki' => 'Lumpiang_Shanghai.jpg',
        'file' => 'gallery/lumpia.webp',
        'caption' => 'Lumpiang Shanghai — golden crispy Filipino spring rolls with sweet chili sauce',
    ],
    [
        'wiki' => 'Halo-Halo.jpg',
        'file' => 'gallery/halohalo.webp',
        'caption' => 'Special Halo-Halo — shaved ice loaded with ube ice cream, leche flan & sweet beans',
    ],
    [
        'wiki' => 'Leche_flan.jpg',
        'file' => 'gallery/lecheflan.webp',
        'caption' => 'Leche Flan — velvety smooth caramel custard dessert',
    ],
    [
        'wiki' => 'Sinangag_(garlic_rice)_from_the_Philippines,_with_egg_01.jpg',
        'file' => 'gallery/sinangag.webp',
        'caption' => 'Garlic Sinangag — fragrant jasmine rice toasted with crunchy garlic',
    ],
    [
        'wiki' => 'Bicol_Express.jpg',
        'file' => 'gallery/bicolexpress.webp',
        'caption' => 'Bicol Express — tender pork in rich coconut cream and spicy chilies',
    ],
    [
        'wiki' => 'Boodle_fight_(kamayan)_01.jpg',
        'file' => 'gallery/kamayan.webp',
        'caption' => 'Kamayan Salo-Salo — traditional Filipino family communal feast on banana leaves',
    ],
];

GalleryPhoto::truncate();

foreach ($galleryMap as $idx => $g) {
    echo " -> Gallery #{$idx} ({$g['file']})... ";
    $raw = downloadRaw($g['wiki']);
    if ($raw && processAndSaveWebp($raw, $g['file'], 1200, 1000)) {
        GalleryPhoto::create([
            'path' => $g['file'],
            'caption' => $g['caption'],
            'sort_order' => $idx + 1,
        ]);
        echo "OK\n";
    } else {
        echo "FAILED\n";
    }
    unset($raw);
    gc_collect_cycles();
}

echo "=== Finished Successfully! ===\n";
