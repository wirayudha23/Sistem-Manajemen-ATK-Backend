<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('product_received_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_received_id');
            $table->uuid('product_id');
            $table->integer('received_quantity');
            $table->integer('price');
            $table->integer('total_product_price');
            $table->timestamps();

            $table->foreign('product_received_id')
                ->references('id')
                ->on('product_receiveds')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_received_details');
    }
};
