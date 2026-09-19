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
        // --- Branches (matches Chapter 1: Main, Matina, Malita) ---
        $main = Branch::create(['name' => 'Main', 'location' => 'Green Meadow Subdivision']);
        $matina = Branch::create(['name' => 'Matina', 'location' => 'Matina Centerpoint']);
        $malita = Branch::create(['name' => 'Malita', 'location' => 'Malita, Davao Occidental']);

        // --- Users (matches Chapter 1 Roles and Responsibilities) ---
        User::create([
            'name' => 'Clyde Careñosa',
            'email' => 'owner@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'branch_id' => $main->id,
        ]);

        User::create([
            'name' => 'Co-Owner',
            'email' => 'coowner@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'co_owner',
            'branch_id' => $main->id,
        ]);

        User::create([
            'name' => 'Matina Staff',
            'email' => 'staff.matina@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'branch_id' => $matina->id,
        ]);

        User::create([
            'name' => 'Malita Staff',
            'email' => 'staff.malita@treklite.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'branch_id' => $malita->id,
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
            foreach ([$main, $matina, $malita] as $branch) {
                Inventory::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'quantity' => 20,
                ]);
            }
        }
    }
}
