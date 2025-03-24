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
        Schema::create('checkout_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checkout_id');
            $table->uuid('product_id');
            $table->integer('checkout_quantity')->default(1);
            $table->timestamps();

            $table->foreign('checkout_id')
                ->references('id')
                ->on('checkouts')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_items');
    }
};
