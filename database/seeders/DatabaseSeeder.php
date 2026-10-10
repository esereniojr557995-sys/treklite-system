<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // CHANGE THESE PASSWORDS before the trial.
        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@treklite.test',
            'role' => 'owner', 'password' => Hash::make('password'),
        ]);
        User::create([
            'name' => 'Staff', 'email' => 'staff@treklite.test',
            'role' => 'staff', 'password' => Hash::make('password'),
        ]);

        // Sample catalog so the screens have something to show.
        // Delete this part and encode the real products through Products > Add Product.
        $catalog = [
            ['Trail Slippers', 'Slippers', 'Treklite', 450.00, [
                ['TS-40-BLK', '40', 'Black', 12], ['TS-41-BLK', '41', 'Black', 10],
                ['TS-42-BLK', '42', 'Black', 3],  ['TS-42-OLV', '42', 'Olive', 0],
            ]],
            ['Quick-Dry Shirt', 'Apparel', 'Treklite', 650.00, [
                ['QD-M-NVY', 'M', 'Navy', 8], ['QD-L-NVY', 'L', 'Navy', 6], ['QD-L-GRY', 'L', 'Gray', 4],
            ]],
            ['Camping Towel', 'Textiles', null, 250.00, [
                ['CT-STD', null, null, 20],
            ]],
        ];

        foreach ($catalog as [$name, $category, $brand, $price, $variants]) {
            $product = Product::create(compact('name', 'category', 'brand', 'price'));

            foreach ($variants as [$sku, $size, $color, $qty]) {
                $variant = $product->variants()->create([
                    'sku' => $sku, 'size' => $size, 'color' => $color, 'quantity' => $qty,
                ]);

                if ($qty > 0) {
                    StockMovement::create([
                        'variant_id' => $variant->id, 'user_id' => $owner->id, 'type' => 'receive', 'quantity_before' => 0,
                        'quantity_change' => $qty, 'quantity_after' => $qty, 'note' => 'Opening stock',
                    ]);
                }
            }
        }
    }
}
