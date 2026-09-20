<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Branches (matches Chapter 1: Main, Matina) ---
        // The Malita branch has since closed and is intentionally not
        // seeded — it no longer exists in the current operations.
        $main = Branch::create(['name' => 'Main', 'location' => 'Green Meadow Subdivision']);
        $matina = Branch::create(['name' => 'Matina', 'location' => 'Matina Centerpoint']);

        // --- Users (matches Chapter 1 Roles and Responsibilities) ---
        // Names are intentionally generic (no personal names), per the
        // data-privacy note validated with the adviser.
        User::create([
            'name' => 'Owner',
            'email' => 'owner@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'branch_id' => $main->id,
        ]);

        User::create([
            'name' => 'Manager',
            'email' => 'manager@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'branch_id' => $main->id,
        ]);

        User::create([
            'name' => 'Matina Staff',
            'email' => 'staff.matina@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'branch_id' => $matina->id,
        ]);

        // --- A few sample products (outdoor slippers/apparel/textiles) ---
        $products = [
            ['sku' => 'SLP-001', 'name' => 'Trail Slipper - Black', 'category' => 'Slippers', 'price' => 349.00],
            ['sku' => 'SLP-002', 'name' => 'Trail Slipper - Tan',   'category' => 'Slippers', 'price' => 349.00],
            ['sku' => 'APP-001', 'name' => 'Outdoor Trekking Shirt','category' => 'Apparel',  'price' => 599.00],
            ['sku' => 'TXT-001', 'name' => 'Microfiber Travel Towel','category' => 'Textiles','price' => 249.00],
        ];

        foreach ($products as $p) {
            $product = Product::create($p + ['low_stock_threshold' => 5]);

            // Seed starting stock per branch
            foreach ([$main, $matina] as $branch) {
                Inventory::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'quantity' => 20,
                ]);
            }
        }
    }
}
