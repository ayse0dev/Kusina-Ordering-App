<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuItem;
use App\Models\GalleryPhoto;

echo "=== Verifying Images ===\n";

$heroExists = file_exists(public_path('images/hero.jpg'));
$aboutExists = file_exists(public_path('images/about/feast.jpg'));
echo "Hero Image (public/images/hero.jpg): " . ($heroExists ? "EXISTS (" . filesize(public_path('images/hero.jpg')) . " bytes)" : "MISSING") . "\n";
echo "About Image (public/images/about/feast.jpg): " . ($aboutExists ? "EXISTS (" . filesize(public_path('images/about/feast.jpg')) . " bytes)" : "MISSING") . "\n";

echo "\n--- Menu Items ---\n";
$items = MenuItem::all();
foreach ($items as $item) {
    $exists = $item->image_path && (file_exists(public_path('storage/' . $item->image_path)) || file_exists(public_path('images/' . $item->image_path)));
    echo sprintf("%-20s | Path: %-25s | URL: %-30s | %s\n", 
        $item->name, 
        $item->image_path ?? 'NULL', 
        $item->image_url ?? 'NULL', 
        $exists ? 'FILE OK' : 'FILE MISSING'
    );
}

echo "\n--- Gallery Photos (" . GalleryPhoto::count() . " total) ---\n";
$photos = GalleryPhoto::orderBy('sort_order')->get();
foreach ($photos as $p) {
    $exists = file_exists(public_path('storage/' . $p->path)) || file_exists(public_path('images/' . $p->path));
    echo sprintf("#%02d | %-25s | %-60s | %s\n", 
        $p->sort_order, 
        $p->path, 
        substr($p->caption, 0, 58), 
        $exists ? 'FILE OK' : 'FILE MISSING'
    );
}

echo "\nVerification complete!\n";
