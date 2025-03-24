<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CheckoutCart;
use App\Models\Product;
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

        $category = Category::create([
            'name' => 'Alat Tulis',
        ]);

        $category2 = Category::create([
            'name' => 'Kertas',
        ]);

        $product1 = Product::create([
            'name' => 'Spidol',
            'price' => 10000,
            'stock' => 10,
            'unit' => 'pcs',
            'image' => 'product1.jpg',
            'category_id' => $category->id,
        ]);

        $product2 = Product::create([
            'name' => 'Kertas A4',
            'price' => 230000,
            'stock' => 20,
            'unit' => 'pcs',
            'image' => 'product2.jpg',
            'category_id' => $category2->id,
        ]);

        $user = User::create([
            'name' => 'User Dosen 1',
            'email' => 'userdosen1@gmail.com',
            'initial' => 'UD1',
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


    }
}
