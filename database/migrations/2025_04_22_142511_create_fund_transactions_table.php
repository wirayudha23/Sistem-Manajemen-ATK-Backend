<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_received_id')->nullable();
            $table->dateTime('date');
            $table->enum('type', ['in', 'out']);
            $table->unsignedBigInteger('amount');

            $table->foreign('product_received_id')
                ->references('id')
                ->on('product_receiveds')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
    }
};
