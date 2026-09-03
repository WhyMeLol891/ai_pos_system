<?php
/**
 * SVG Product Graphic Generator
 * Generates crisp vector badges for demo catalog items
 */

$items = [
    'coca_cola.svg' => ['bg' => '#E11D48', 'text' => 'Coca-Cola', 'sub' => '320ml Can', 'icon' => '🥤'],
    'pepsi.svg' => ['bg' => '#1D4ED8', 'text' => 'Pepsi', 'sub' => '320ml Can', 'icon' => '🥤'],
    'mineral_water.svg' => ['bg' => '#0284C7', 'text' => 'Spritzer', 'sub' => '600ml Water', 'icon' => '💧'],
    '100plus.svg' => ['bg' => '#0D9488', 'text' => '100 Plus', 'sub' => '325ml Isotonic', 'icon' => '⚡'],
    'milo.svg' => ['bg' => '#15803D', 'text' => 'Milo Active', 'sub' => '200ml UHT', 'icon' => '🍫'],
    'lays_chips.svg' => ['bg' => '#EAB308', 'text' => "Lay's Chips", 'sub' => '50g Classic', 'icon' => '🥔'],
    'pringles.svg' => ['bg' => '#DC2626', 'text' => 'Pringles', 'sub' => '107g Original', 'icon' => '🍟'],
    'oreo.svg' => ['bg' => '#1E293B', 'text' => 'Oreo Cookies', 'sub' => '133g Vanilla', 'icon' => '🍪'],
    'kitkat.svg' => ['bg' => '#B91C1C', 'text' => 'KitKat', 'sub' => '4-Finger Chocolate', 'icon' => '🍫'],
    'gardenia_bread.svg' => ['bg' => '#F59E0B', 'text' => 'Gardenia', 'sub' => 'White Bread 400g', 'icon' => '🍞'],
    'wheat_bread.svg' => ['bg' => '#D97706', 'text' => 'Massimo', 'sub' => 'Wheat Bread 400g', 'icon' => '🥖'],
    'maggi_curry.svg' => ['bg' => '#EA580C', 'text' => 'Maggi Curry', 'sub' => 'Instant Noodles', 'icon' => '🍜'],
    'samyang_ramen.svg' => ['bg' => '#991B1B', 'text' => 'Samyang Ramen', 'sub' => '140g Hot Spicy', 'icon' => '🔥'],
    'fresh_milk.svg' => ['bg' => '#2563EB', 'text' => 'Dutch Lady', 'sub' => 'Full Cream 1L', 'icon' => '🥛'],
    'default_product.svg' => ['bg' => '#64748B', 'text' => 'Product', 'sub' => 'Item', 'icon' => '📦']
];

$dir = __DIR__ . '/../assets/uploads/products/';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ($items as $filename => $data) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200" width="300" height="200">
    <rect width="300" height="200" rx="12" fill="{$data['bg']}"/>
    <rect x="10" y="10" width="280" height="180" rx="8" fill="#ffffff" fill-opacity="0.12" stroke="#ffffff" stroke-opacity="0.25" stroke-width="2"/>
    <text x="150" y="80" font-family="system-ui, -apple-system, sans-serif" font-size="46" text-anchor="middle" dominant-baseline="middle">{$data['icon']}</text>
    <text x="150" y="130" font-family="system-ui, -apple-system, sans-serif" font-size="20" font-weight="bold" fill="#ffffff" text-anchor="middle">{$data['text']}</text>
    <text x="150" y="158" font-family="system-ui, -apple-system, sans-serif" font-size="14" fill="#ffffff" fill-opacity="0.85" text-anchor="middle">{$data['sub']}</text>
</svg>
SVG;
    file_put_contents($dir . $filename, $svg);
}

echo "Created " . count($items) . " product SVG images in assets/uploads/products/" . PHP_EOL;
