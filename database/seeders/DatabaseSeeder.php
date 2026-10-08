<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
    'name' => 'Owner',
    'email' => 'owner@treklite.test',
    'password' => Hash::make('password'),
    'role' => 'owner',
]);

    User::create([
        'name' => 'Main Staff',
        'email' => 'staff.main@treklite.test',
        'password' => Hash::make('password'),
        'role' => 'staff',
    ]);
    foreach ($products as $product) {
    Product::create($product);
}

    }
}
