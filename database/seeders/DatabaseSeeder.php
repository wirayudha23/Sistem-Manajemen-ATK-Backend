<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Checkout;
use App\Models\Purpose;
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

        $alatTulis = Category::create([
            'name' => 'Alat Tulis',
        ]);

        $kertas = Category::create([
            'name' => 'Kertas',
        ]);

        $buku = Category::create([
            'name' => 'Buku',
        ]);

        $map = Category::create([
            'name' => 'Map',
        ]);

        $plastik = Category::create([
            'name' => 'Plastik'
        ]);

        $stickyNote = Category::create([
            'name' => 'Sticky Note'
        ]);

        $isolasi = Category::create([
            'name' => 'Isolasi'
        ]);

        $dll = Category::create([
            'name' => 'Dll'
        ]);

        $rim = Unit::create([
            'name' => 'Rim',
        ]);

        $pcs = Unit::create([
            'name' => 'Pcs',
        ]);

        $product1 = Product::create([
            'name' => 'Kertas A4',
            'price' => 230000,
            'stock' => 100,
            'image' => 'product1.jpg',
            'category_id' => $kertas->id,
            'unit_id' => $rim->id,
        ]);

        $product2 = Product::create([
            'name' => 'Buku 3/4 Folio',
            'price' => 13000,
            'stock' => 100,
            'image' => 'product2.jpg',
            'category_id' => $buku->id,
            'unit_id' => $pcs->id,
        ]);

        $product3 = Product::create([
            'name' => 'Map Bantex',
            'price' => '933000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $map->id,
            'unit_id' => $pcs->id,
        ]);

        $product4 = Product::create([
            'name' => 'Pena Snowman Hitam',
            'price' => '3000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product5 = Product::create([
            'name' => 'Pena Snowman Merah',
            'price' => '3000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product6 = Product::create([
            'name' => 'Pena Snowman Biru',
            'price' => '3000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product7 = Product::create([
            'name' => 'Spidol Merah',
            'price' => '8500',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product8 = Product::create([
            'name' => 'Spidol Hitam',
            'price' => '8500',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product9 = Product::create([
            'name' => 'Spidol Biru',
            'price' => '8500',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product10 = Product::create([
            'name' => 'Buku Folio F4',
            'price' => '80000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $buku->id,
            'unit_id' => $pcs->id,
        ]);

        $product11 = Product::create([
            'name' => 'Plastik PO',
            'price' => '30000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $plastik->id,
            'unit_id' => $pcs->id,
        ]);

        $product12 = Product::create([
            'name' => 'Sticky Note',
            'price' => '15000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $stickyNote->id,
            'unit_id' => $pcs->id,
        ]);

        $product13 = Product::create([
            'name' => 'Isolasi Kertas',
            'price' => '15000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $isolasi->id,
            'unit_id' => $pcs->id,
        ]);

        $product14 = Product::create([
            'name' => 'Isolasi Kertas',
            'price' => '15000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $isolasi->id,
            'unit_id' => $pcs->id,
        ]);

        $product15 = Product::create([
            'name' => 'Gantungan Kunci',
            'price' => '37000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $dll->id,
            'unit_id' => $pcs->id,
        ]);

        $product16 = Product::create([
            'name' => 'Kertas A3 100gr',
            'price' => '161000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $kertas->id,
            'unit_id' => $rim->id,
        ]);

        $product17 = Product::create([
            'name' => 'Kertas A4 100gr',
            'price' => '90000',
            'stock' => 100,
            'image' => 'product3.jpg',
            'category_id' => $kertas->id,
            'unit_id' => $rim->id,
        ]);

        $studyProgram1 = \App\Models\StudyProgram::create([
            'name' => 'Teknik Informatika',
        ]);
        $studyProgram2 = \App\Models\StudyProgram::create([
            'name' => 'Sistem Informasi',
        ]);

        $user1 = User::create([
            'name' => 'Tendik Ahmad',
            'email' => 'ahmad21si@mahasiswa.pcr.ac.id',
            'nip' => '123456',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram2->id,
            'initial' => 'WYD',
            'role' => 'BAAK',
            'phone_number' => null,
        ]);

        $user2 = User::create([
            'name' => 'Kabag Ahmad',
            'email' => 'ahmadfadhil2003@gmail.com',
            'nip' => '122334',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'AFW',
            'role' => 'Kabag',
            'phone_number' => null,
        ]);

        $user3 = User::create([
            'name' => 'Sarah Nabilah',
            'email' => 'sarahnabilahputri@gmail.com',
            'nip' => '122222',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'MGY',
            'role' => 'BAAK',
            'phone_number' => null,
        ]);

        $user4 = User::create([
            'name' => 'Sarah Nabilah Putri',
            'email' => 'sarah21si@mahasiswa.pcr.ac.id',
            'nip' => '133333',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'SRB',
            'role' => 'BAAK',
            'phone_number' => null,
        ]);

        $user5 = User::create([
            'name' => 'Dosen Ahmad',
            'email' => 'alexasep2304@gmail.com',
            'nip' => '134679',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram2->id,
            'initial' => 'ALX',
            'role' => 'Staff',
            'phone_number' => null,
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

        Purpose::create(['name' => 'Prodi/Bagian']);
        Purpose::create(['name' => 'Perkuliahan']);
        Purpose::create(['name' => 'Pengabdian']);
        Purpose::create(['name' => 'Penelitian']);
        Purpose::create(['name' => 'Kepanitiaan']);






        // Reorder::create([
        //     'reorder_date' => now(),
        //     'delivery_date' => now()->addDays(7),
        // ]);
    }
}
