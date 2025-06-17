<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->integer('price');
            $table->integer('stock')->default(0);
            $table->decimal('economic_order_quantity', 10, 2)->nullable()->default(0);
            $table->decimal('safety_stock', 10, 2)->nullable()->default(0);
            $table->decimal('reorder_point', 10, 2)->nullable()->default(0);
            $table->string('image')->default('assets/images/default_product.jpg');

            $table->uuid('category_id');
            $table->uuid('unit_id');

            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories');
                // ->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')
                ->on('units');
                // ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
