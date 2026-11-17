<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\product_warehouse;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\ProductOptionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demo products for the Shopify-style variant options system
 * (Color / Size / ... -> generated combinations).
 *
 * Six variable products showing every feature: one, two and three options,
 * color swatches, per-variant images, a price that differs by size, a
 * combination that deliberately does not exist (disabled on the storefront)
 * and one that exists but has no stock (struck through).
 *
 *   php artisan db:seed --class=VariantDemoSeeder
 *
 * Idempotent: skips when any VD-* product exists. Delete the VD-* products
 * to reseed. Creates nothing but its own category / brands / products; it
 * does not hide or touch other catalogues.
 */
class VariantDemoSeeder extends Seeder
{
    private const IMG = [
        'tee_white' => '1521572163474-6864f9cf17ab',
        'tee_black' => '1583743814966-8936f5b7be1a',
        'tee_flat' => '1576566588028-4147f3842f27',
        'jeans' => '1542272604-787c3835535d',
        'jeans_2' => '1541099649105-f69ad21f3246',
        'sneaker_red' => '1542291026-7eec264c27ff',
        'sneaker_white' => '1600185365483-26d7a4cc7519',
        'sneaker_black' => '1595950653106-6c9ebd614d3a',
        'hoodie' => '1556821840-3a63f95609a7',
        'hoodie_2' => '1620799140408-edc6dcb6d633',
        'mug' => '1514228742587-6b1558fcca3d',
        'mug_2' => '1517256064527-09c73fc73e38',
        'yoga' => '1601925260368-ae2f83cf8b7f',
        'yoga_2' => '1592432678016-e910b452f9a2',
    ];

    private static function img(string $key, int $w = 900): string
    {
        return 'https://images.unsplash.com/photo-'.self::IMG[$key].'?w='.$w.'&q=80&auto=format&fit=crop';
    }

    public function run(): void
    {
        if (Product::where('code', 'like', 'VD-%')->exists()) {
            $this->command?->warn('Variant demo products already present. Delete the VD-* products to reseed.');

            return;
        }

        $svc = app(ProductOptionService::class);

        DB::transaction(function () use ($svc) {
            $unit = Unit::firstOrCreate(
                ['ShortName' => 'pc'],
                ['name' => 'Piece', 'base_unit' => null, 'operator' => '*', 'operator_value' => 1, 'is_active' => 1]
            );
            $warehouses = Warehouse::whereNull('deleted_at')->get();
            if ($warehouses->isEmpty()) {
                $warehouses = collect([Warehouse::create(['name' => 'Main Warehouse', 'city' => 'Main', 'country' => '', 'mobile' => '', 'email' => '', 'zip' => ''])]);
            }

            $apparel = Category::firstOrCreate(['name' => 'Apparel'], ['code' => 'VD-C1', 'icon' => 'shirt']);
            $footwear = Category::firstOrCreate(['name' => 'Footwear'], ['code' => 'VD-C2', 'icon' => 'footprints']);
            $home = Category::firstOrCreate(['name' => 'Home & Living'], ['code' => 'VD-C3', 'icon' => 'home']);
            $sport = Category::firstOrCreate(['name' => 'Sports'], ['code' => 'VD-C4', 'icon' => 'dumbbell']);

            $sub = function (Category $cat, string $name): ?int {
                if (! Schema::hasTable('sub_categories')) {
                    return null;
                }

                return SubCategory::firstOrCreate(['category_id' => $cat->id, 'name' => $name], ['description' => '', 'status' => 1])->id;
            };

            $brands = [];
            foreach (['Northwind Apparel', 'Stride', 'Hearth & Home', 'Zenflex'] as $b) {
                $brands[$b] = Brand::firstOrCreate(['name' => $b], ['description' => $b.' products']);
            }

            // --------------------------------------------------------------
            // Each entry: product data + options + a variant callback that
            // returns [cost, price, stock qty | null (= no such combination), image key | null]
            // for a combination [Color, Size] etc.
            // --------------------------------------------------------------
            $products = [
                [
                    'code' => 'VD-TSHIRT',
                    'name' => 'Classic Cotton T-Shirt',
                    'category' => $apparel, 'sub' => 'T-Shirts', 'brand' => 'Northwind Apparel',
                    'images' => ['tee_white', 'tee_black', 'tee_flat'],
                    'featured' => 1,
                    'note' => 'Heavyweight 100% organic cotton tee with a relaxed fit. Pre-shrunk, reinforced collar. Pick a color and a size — XL and XXL cost a little more.',
                    'tags' => ['tshirt', 'cotton', 'basics'],
                    'options' => [
                        ['name' => 'Color', 'values' => [
                            ['value' => 'Black', 'swatch' => '#111111'],
                            ['value' => 'White', 'swatch' => '#f4f4f4'],
                            ['value' => 'Navy', 'swatch' => '#1f3a5f'],
                            ['value' => 'Red', 'swatch' => '#d62828'],
                        ]],
                        ['name' => 'Size', 'values' => ['S', 'M', 'L', 'XL', 'XXL']],
                    ],
                    'variant' => function (array $v): ?array {
                        [$color, $size] = $v;
                        $extra = in_array($size, ['XL', 'XXL'], true) ? 2 : 0;
                        $img = $color === 'Black' ? 'tee_black' : ($color === 'White' ? 'tee_white' : null);

                        return [8.50, 19.90 + $extra, random_int(6, 40), $img];
                    },
                ],
                [
                    'code' => 'VD-JEANS',
                    'name' => 'Slim Fit Denim Jeans',
                    'category' => $apparel, 'sub' => 'Jeans', 'brand' => 'Northwind Apparel',
                    'images' => ['jeans', 'jeans_2'],
                    'featured' => 0,
                    'note' => 'Mid-rise slim fit in stretch denim. Sold by waist and inside length, the way jeans are sized in store.',
                    'tags' => ['jeans', 'denim'],
                    'options' => [
                        ['name' => 'Waist', 'values' => ['30', '32', '34', '36']],
                        ['name' => 'Length', 'values' => ['30', '32', '34']],
                    ],
                    'variant' => function (array $v): ?array {
                        [$waist, $length] = $v;
                        // 36/34 is not made: shows a disabled combination on the store.
                        if ($waist === '36' && $length === '34') {
                            return null;
                        }

                        return [22.00, 49.00, random_int(3, 25), null];
                    },
                ],
                [
                    'code' => 'VD-SNEAKER',
                    'name' => 'Everyday Running Sneakers',
                    'category' => $footwear, 'sub' => 'Sneakers', 'brand' => 'Stride',
                    'images' => ['sneaker_red', 'sneaker_white', 'sneaker_black'],
                    'featured' => 1,
                    'note' => 'Lightweight mesh upper with a cushioned sole. EU sizing. Black 44 is out of stock on purpose to show the struck-through state; Red 40 is not made.',
                    'tags' => ['sneakers', 'running', 'shoes'],
                    'options' => [
                        ['name' => 'Color', 'values' => [
                            ['value' => 'Red', 'swatch' => '#c1121f'],
                            ['value' => 'White', 'swatch' => '#fafafa'],
                            ['value' => 'Black', 'swatch' => '#1a1a1a'],
                        ]],
                        ['name' => 'Size', 'values' => ['40', '41', '42', '43', '44']],
                    ],
                    'variant' => function (array $v): ?array {
                        [$color, $size] = $v;
                        if ($color === 'Red' && $size === '40') {
                            return null;                       // combination does not exist
                        }
                        $qty = ($color === 'Black' && $size === '44') ? 0 : random_int(2, 18); // exists, no stock
                        $img = ['Red' => 'sneaker_red', 'White' => 'sneaker_white', 'Black' => 'sneaker_black'][$color];

                        return [38.00, 89.00, $qty, $img];
                    },
                ],
                [
                    'code' => 'VD-HOODIE',
                    'name' => 'Fleece Pullover Hoodie',
                    'category' => $apparel, 'sub' => 'Hoodies', 'brand' => 'Northwind Apparel',
                    'images' => ['hoodie', 'hoodie_2'],
                    'featured' => 0,
                    'note' => 'Brushed fleece hoodie with kangaroo pocket. Three options: color, size and fit — the maximum the system allows.',
                    'tags' => ['hoodie', 'fleece'],
                    'options' => [
                        ['name' => 'Color', 'values' => [
                            ['value' => 'Heather Grey', 'swatch' => '#9a9a9a'],
                            ['value' => 'Black', 'swatch' => '#111111'],
                        ]],
                        ['name' => 'Size', 'values' => ['S', 'M', 'L']],
                        ['name' => 'Fit', 'values' => ['Regular', 'Oversized']],
                    ],
                    'variant' => function (array $v): ?array {
                        [, , $fit] = $v;

                        return [18.00, $fit === 'Oversized' ? 44.00 : 39.00, random_int(4, 20), null];
                    },
                ],
                [
                    'code' => 'VD-MUG',
                    'name' => 'Stoneware Coffee Mug',
                    'category' => $home, 'sub' => 'Kitchen', 'brand' => 'Hearth & Home',
                    'images' => ['mug', 'mug_2'],
                    'featured' => 0,
                    'note' => 'Hand-glazed stoneware, dishwasher and microwave safe. A single option (capacity) is enough for this one.',
                    'tags' => ['mug', 'coffee', 'kitchen'],
                    'options' => [
                        ['name' => 'Capacity', 'values' => ['250 ml', '350 ml', '500 ml']],
                    ],
                    'variant' => function (array $v): ?array {
                        [$cap] = $v;
                        $price = ['250 ml' => 9.90, '350 ml' => 11.90, '500 ml' => 14.90][$cap];

                        return [3.20, $price, random_int(10, 60), null];
                    },
                ],
                [
                    'code' => 'VD-YOGAMAT',
                    'name' => 'Non-Slip Yoga Mat',
                    'category' => $sport, 'sub' => 'Yoga', 'brand' => 'Zenflex',
                    'images' => ['yoga', 'yoga_2'],
                    'featured' => 1,
                    'note' => 'TPE mat with alignment lines and carry strap. Choose a color and a thickness.',
                    'tags' => ['yoga', 'fitness', 'mat'],
                    'options' => [
                        ['name' => 'Color', 'values' => [
                            ['value' => 'Purple', 'swatch' => '#6a4c93'],
                            ['value' => 'Teal', 'swatch' => '#1b998b'],
                            ['value' => 'Coral', 'swatch' => '#f4845f'],
                        ]],
                        ['name' => 'Thickness', 'values' => ['4 mm', '6 mm']],
                    ],
                    'variant' => function (array $v): ?array {
                        [, $thick] = $v;

                        return [11.00, $thick === '6 mm' ? 34.00 : 29.00, random_int(5, 30), null];
                    },
                ],
            ];

            $i = 0;
            foreach ($products as $def) {
                $data = [
                    'code' => $def['code'],
                    'Type_barcode' => 'CODE128',
                    'name' => $def['name'],
                    'cost' => 0,
                    'price' => 0,
                    'unit_id' => $unit->id,
                    'unit_sale_id' => $unit->id,
                    'unit_purchase_id' => $unit->id,
                    'stock_alert' => 3,
                    'category_id' => $def['category']->id,
                    'brand_id' => $brands[$def['brand']]->id ?? null,
                    'is_variant' => 1,
                    'tax_method' => '1',
                    'TaxNet' => 0,
                    'type' => 'is_variant',
                    'is_active' => 1,
                    'image' => self::img($def['images'][0]),
                    'note' => $def['note'],
                    'discount' => null,
                    'discount_method' => '1',
                    'created_at' => Carbon::now()->subDays(random_int(0, 60))->subMinutes($i),
                    'updated_at' => Carbon::now(),
                ];
                foreach (['is_featured' => $def['featured'], 'hide_from_online_store' => 0, 'not_selling' => 0] as $col => $val) {
                    if (Schema::hasColumn('products', $col)) {
                        $data[$col] = $val;
                    }
                }
                if (Schema::hasColumn('products', 'sub_category_id')) {
                    $data['sub_category_id'] = $sub($def['category'], $def['sub']);
                }
                if (Schema::hasColumn('products', 'tags')) {
                    $data['tags'] = $def['tags'];
                }

                $product = new Product;
                $product->forceFill($data);
                $product->save();

                if (Schema::hasTable('product_images')) {
                    foreach (array_values(array_unique($def['images'])) as $k => $key) {
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => self::img($key),
                            'is_main' => $k === 0,
                            'sort_order' => $k,
                        ]);
                    }
                }

                // Options + every combination the callback accepts.
                $options = $svc->normalizeOptions($def['options']);
                $map = $svc->sync($product, $options);

                $combos = [[]];
                foreach ($options as $opt) {
                    $next = [];
                    foreach ($combos as $c) {
                        foreach ($opt['values'] as $val) {
                            $next[] = [...$c, $val['value']];
                        }
                    }
                    $combos = $next;
                }

                $n = 0;
                foreach ($combos as $combo) {
                    $spec = ($def['variant'])($combo);
                    if ($spec === null) {
                        continue;
                    }
                    [$cost, $price, $qty, $imgKey] = $spec;
                    $n++;
                    $values = [];
                    foreach ($options as $k => $opt) {
                        $values[$opt['name']] = $combo[$k];
                    }

                    $variant = new ProductVariant;
                    $variant->product_id = $product->id;
                    $variant->name = $svc->title($options, $values);
                    $variant->code = $def['code'].'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                    $variant->cost = $cost;
                    $variant->price = $price;
                    if (Schema::hasColumn('product_variants', 'wholesale')) {
                        $variant->wholesale = round($price * 0.85, 2);
                    }
                    if (Schema::hasColumn('product_variants', 'min_price')) {
                        $variant->min_price = round($price * 0.75, 2);
                    }
                    $variant->image = $imgKey ? self::img($imgKey) : 'no-image.png';
                    $variant->qty = 0;
                    $variant->save();

                    $svc->attach($variant->id, $values, $map);

                    foreach ($warehouses as $wi => $wh) {
                        product_warehouse::create([
                            'product_id' => $product->id,
                            'warehouse_id' => $wh->id,
                            'product_variant_id' => $variant->id,
                            // Only the first warehouse holds the "no stock" demo quantity as-is;
                            // extra warehouses get a smaller random share.
                            'qte' => $qty === 0 ? 0 : ($wi === 0 ? $qty : random_int(0, max(1, (int) ($qty / 2)))),
                            'manage_stock' => 1,
                        ]);
                    }
                }

                $this->command?->info(sprintf('  %-26s %2d options, %2d variants', $def['name'], count($options), $n));
                $i++;
            }
        });

        $this->command?->info('Variant demo products seeded (codes VD-*).');
    }
}
