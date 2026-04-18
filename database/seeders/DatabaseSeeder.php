<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Location;
use App\Models\Node;
use App\Models\Product;
use App\Models\QtyType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $node = Node::create([
            'id'      => config('app.node_id'),
            'name'    => config('app.node_name'),
            'url'     => null,
            'is_self' => true,
        ]);

        $admin = User::factory()->create([
            'name'     => 'Admin',
            'username' => 'admin',
            'email'    => 'admin@peer2pos.local',
            'password' => bcrypt('admin123'),
        ]);

        $supplier = Supplier::create([
            'origin_node_id' => $node->id,
            'name'           => 'Default Supplier',
            'address'        => null,
        ]);

        $storeroom = Location::create([
            'origin_node_id' => $node->id,
            'name'           => 'Storeroom',
        ]);

        $data = [
            'Beverages' => [
                [
                    'item_code' => 'BEV-001',
                    'name'      => 'Coffee',
                    'qty_types' => [
                        ['name' => 'Cup',   'qty' => 1,  'barcode' => 'BEV001-CUP',  'base_price' => 2.00, 'selling_price' => 3.50, 'is_base' => true],
                        ['name' => 'Dozen', 'qty' => 12, 'barcode' => 'BEV001-DOZ',  'base_price' => 20.00, 'selling_price' => 36.00, 'is_base' => false],
                    ],
                ],
                [
                    'item_code' => 'BEV-002',
                    'name'      => 'Tea',
                    'qty_types' => [
                        ['name' => 'Cup',  'qty' => 1, 'barcode' => 'BEV002-CUP', 'base_price' => 1.50, 'selling_price' => 2.50, 'is_base' => true],
                    ],
                ],
                [
                    'item_code' => 'BEV-003',
                    'name'      => 'Juice',
                    'qty_types' => [
                        ['name' => 'Bottle', 'qty' => 1, 'barcode' => 'BEV003-BTL', 'base_price' => 2.50, 'selling_price' => 4.00, 'is_base' => true],
                    ],
                ],
            ],
            'Food' => [
                [
                    'item_code' => 'FOOD-001',
                    'name'      => 'Sandwich',
                    'qty_types' => [
                        ['name' => 'Piece', 'qty' => 1, 'barcode' => 'FOOD001-PC', 'base_price' => 4.00, 'selling_price' => 6.50, 'is_base' => true],
                    ],
                ],
                [
                    'item_code' => 'FOOD-002',
                    'name'      => 'Salad',
                    'qty_types' => [
                        ['name' => 'Bowl', 'qty' => 1, 'barcode' => 'FOOD002-BWL', 'base_price' => 3.50, 'selling_price' => 5.50, 'is_base' => true],
                    ],
                ],
                [
                    'item_code' => 'FOOD-003',
                    'name'      => 'Muffin',
                    'qty_types' => [
                        ['name' => 'Piece', 'qty' => 1, 'barcode' => 'FOOD003-PC', 'base_price' => 1.50, 'selling_price' => 2.75, 'is_base' => true],
                        ['name' => 'Box-6', 'qty' => 6, 'barcode' => 'FOOD003-BX6', 'base_price' => 8.00, 'selling_price' => 14.00, 'is_base' => false,
                            'discount' => ['enabled' => true, 'type' => 'percent', 'value' => 5, 'timed' => false]],
                    ],
                ],
            ],
            'Snacks' => [
                [
                    'item_code' => 'SNK-001',
                    'name'      => 'Chips',
                    'qty_types' => [
                        ['name' => 'Pack',    'qty' => 1,  'barcode' => 'SNK001-PK',  'base_price' => 0.80, 'selling_price' => 1.50, 'is_base' => true],
                        ['name' => 'Carton',  'qty' => 24, 'barcode' => 'SNK001-CTN', 'base_price' => 16.00, 'selling_price' => 28.00, 'is_base' => false],
                    ],
                ],
                [
                    'item_code' => 'SNK-002',
                    'name'      => 'Chocolate Bar',
                    'qty_types' => [
                        ['name' => 'Bar', 'qty' => 1, 'barcode' => 'SNK002-BAR', 'base_price' => 1.00, 'selling_price' => 2.00, 'is_base' => true],
                    ],
                ],
            ],
        ];

        foreach ($data as $categoryName => $products) {
            $category = Category::create([
                'origin_node_id' => $node->id,
                'name'           => $categoryName,
            ]);

            foreach ($products as $productData) {
                $product = Product::create([
                    'origin_node_id' => $node->id,
                    'item_code'      => $productData['item_code'],
                    'category_id'    => $category->id,
                    'supplier_id'    => $supplier->id,
                    'location_id'    => $storeroom->id,
                    'name'           => $productData['name'],
                ]);

                foreach ($productData['qty_types'] as $qtData) {
                    $discountData = $qtData['discount'] ?? null;
                    unset($qtData['discount']);

                    $qtyType = QtyType::create(array_merge($qtData, ['product_id' => $product->id]));

                    if ($discountData) {
                        Discount::create(array_merge($discountData, ['qty_type_id' => $qtyType->id]));
                    }
                }
            }
        }
    }
}
