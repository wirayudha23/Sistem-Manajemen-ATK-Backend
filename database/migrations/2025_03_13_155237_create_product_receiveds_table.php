<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('product_receiveds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reorder_id');
            $table->date('received_date');
            $table->enum('received_status', [
                'pending',
                'barang_tidak_tersedia',
                'selesai',
                'diretur'
            ])->default('selesai');
            $table->integer('total_received_price')->default(0);
            $table->timestamps();

            $table->foreign('reorder_id')
                ->references('id')
                ->on('reorders')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_receiveds');
    }
};
