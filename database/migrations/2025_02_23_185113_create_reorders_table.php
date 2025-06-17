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
        Schema::create('reorders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('month_sequence')->default(0);
            $table->string('reorder_code', 20)->unique();
            $table->date('reorder_date');
            $table->date('delivery_date');
            $table->integer('total_reorder_price')->default(0);
            $table->enum('whatsapp_status', [
                'belum_dikirim',
                'sudah_dikirim',
                'gagal_dikirim',
                'update_belum_dikirim',
                'update_sudah_dikirim',
                'update_gagal_dikirim',
                'selesai',
                'dibatalkan'
                ])->default('belum_dikirim');
            $table->enum('reorder_status', [
                'draft',
                'proses',
                'selesai',
                'dibatalkan'])->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('wa_error_message')->nullable();
            $table->json('pending_update_diff')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reorder');
    }
};
