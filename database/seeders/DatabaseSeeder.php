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
            'economic_order_quantity' => 10,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $kertas->id,
            'unit_id' => $rim->id,
        ]);

        $product2 = Product::create([
            'name' => 'Buku 3/4 Folio',
            'price' => 13000,
            'stock' => 100,
            'economic_order_quantity' => 10,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $buku->id,
            'unit_id' => $pcs->id,
        ]);

        $product3 = Product::create([
            'name' => 'Map Bantex',
            'price' => '933000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $map->id,
            'unit_id' => $pcs->id,
        ]);

        $product4 = Product::create([
            'name' => 'Pena Snowman Hitam',
            'price' => '3000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product5 = Product::create([
            'name' => 'Pena Snowman Merah',
            'price' => '3000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product6 = Product::create([
            'name' => 'Pena Snowman Biru',
            'price' => '3000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product7 = Product::create([
            'name' => 'Spidol Merah',
            'price' => '8500',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product8 = Product::create([
            'name' => 'Spidol Hitam',
            'price' => '8500',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product9 = Product::create([
            'name' => 'Spidol Biru',
            'price' => '8500',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $alatTulis->id,
            'unit_id' => $pcs->id,
        ]);

        $product10 = Product::create([
            'name' => 'Buku Folio F4',
            'price' => '80000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $buku->id,
            'unit_id' => $pcs->id,
        ]);

        $product11 = Product::create([
            'name' => 'Plastik PO',
            'price' => '30000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $plastik->id,
            'unit_id' => $pcs->id,
        ]);

        $product12 = Product::create([
            'name' => 'Sticky Note',
            'price' => '15000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $stickyNote->id,
            'unit_id' => $pcs->id,
        ]);

        $product13 = Product::create([
            'name' => 'Isolasi Kertas',
            'price' => '15000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $isolasi->id,
            'unit_id' => $pcs->id,
        ]);

        $product15 = Product::create([
            'name' => 'Gantungan Kunci',
            'price' => '37000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $dll->id,
            'unit_id' => $pcs->id,
        ]);

        $product16 = Product::create([
            'name' => 'Kertas A3 100gr',
            'price' => '161000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
            'category_id' => $kertas->id,
            'unit_id' => $rim->id,
        ]);

        $product17 = Product::create([
            'name' => 'Kertas A4 100gr',
            'price' => '90000',
            'stock' => 100,
            // 'image' => 'assets/images/default_product.jpg',
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
            'name' => 'User 1',
            'email' => 'ahmad21si@mahasiswa.pcr.ac.id',
            'nip' => '123456',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram2->id,
            'initial' => 'WYD',
            'role' => 'BAAK',
            'phone_number' => null,
        ]);

        $user5 = User::create([
            'name' => 'User 2',
            'email' => 'alexasep2304@gmail.com',
            'nip' => '134679',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram2->id,
            'initial' => 'ALX',
            'role' => 'BAAK',
            'phone_number' => null,
        ]);

        $user6 = User::create([
            'name' => 'User 3',
            'email' => 'asepalex2304@gmail.com',
            'nip' => '135790',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram2->id,
            'initial' => 'RTA',
            'role' => 'BAAK',
            'phone_number' => '081238827608',
        ]);

        $user8 = User::create([
            'name' => 'User 4',
            'email' => 'wirayudhawijaya1@gmail.com',
            'nip' => 555994,
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'YTA',
            'role' => 'BAAK',
            'phone_number' => '081238827607',
        ]);

        $user9 = User::create([
            'name' => 'User 5',
            'email' => 'wirayudhawijaya2@gmail.com',
            'nip' => '555996',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'WWW',
            'role' => 'BAAK',
            'phone_number' => '089621317672',
        ]);

        $user10 = User::create([
            'name' => 'User 6',
            'email' => 'wirayudhawijaya3@gmail.com',
            'nip' => '555997',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'WWE',
            'role' => 'BAAK',
            'phone_number' => '089621317673',
        ]);

        $user11 = User::create([
            'name' => 'User 7',
            'email' => 'wirayudhawijaya4@gmail.com',
            'nip' => '555998',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'WWR',
            'role' => 'BAAK',
            'phone_number' => '089621317674',
        ]);

        $user12 = User::create([
            'name' => 'User 8',
            'email' => 'wirayudhawijaya5@gmail.com',
            'nip' => '555999',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'WWT',
            'role' => 'BAAK',
            'phone_number' => '089621317675',
        ]);

        $user13 = User::create([
            'name' => 'User 9',
            'email' => 'wirayudhawijaya6@gmail.com',
            'nip' => '555910',
            'position' => 'Tendik',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'WWY',
            'role' => 'BAAK',
            'phone_number' => '089621317679',
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

        $user7 = User::create([
            'name' => 'Mingyu Sarah',
            'email' => 'sarahmingyu@gmail.com',
            'nip' => 555988,
            'position' => 'Rumah Tangga',
            'study_program_id' => $studyProgram1->id,
            'initial' => 'SNP',
            'role' => 'Staff',
            'phone_number' => '089621317671',
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
            'product_id' => $product1->id,
            'reorder_quantity' => 10,
        ]);

        ReorderCart::create([
            'product_id' => $product2->id,
            'reorder_quantity' => 10,
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
