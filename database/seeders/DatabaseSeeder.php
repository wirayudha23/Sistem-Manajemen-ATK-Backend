<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Checkout;
use App\Models\Reorder;
use App\Models\ReorderCart;
use App\Models\Unit;
use App\Models\Product;
use App\Models\CheckoutCart;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $category1 = Category::create([
            'name' => 'Alat Tulis',
        ]);

        $category2 = Category::create([
            'name' => 'Kertas',
        ]);

        $unit1 = Unit::create([
            'name' => 'Box',
        ]);

        $unit2 = Unit::create([
            'name' => 'Rim',
        ]);

        $product1 = Product::create([
            'name' => 'Pena',
            'price' => 10000,
            'stock' => 10,
            'image' => 'product1.jpg',
            'category_id' => $category1->id,
            'unit_id' => $unit1->id,
        ]);

        $product2 = Product::create([
            'name' => 'Kertas A4',
            'price' => 230000,
            'stock' => 20,
            'image' => 'product2.jpg',
            'category_id' => $category2->id,
            'unit_id' => $unit2->id,
        ]);

        $user1 = User::create([
            'name' => 'Dosen Wirayudha',
            'email' => 'userdosen1@gmail.com',
            'nip' => '123456',
            'prodi' => 'Sistem Informasi',
            'initial' => 'WYD',
            'role' => 'dosen',
        ]);

        CheckoutCart::create([
            'product_id' => $product1->id,
            'checkout_quantity' => 2,
        ]);

        CheckoutCart::create([
            'product_id' => $product2->id,
            'checkout_quantity' => 3,
        ]);

        // Checkout::create([
        //     'user_id' => $user1->id,
        //     'checkout_date' => now(),
        // ]);

        ReorderCart::create([
            'product_id' => $product1->id
        ]);

        ReorderCart::create([
            'product_id' => $product2->id
        ]);

        // Reorder::create([
        //     'reorder_date' => now(),
        //     'delivery_date' => now()->addDays(7),
        // ]);
    }
}
