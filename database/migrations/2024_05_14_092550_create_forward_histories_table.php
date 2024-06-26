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
        Schema::create('forward_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('price_id');
            $table->foreignId('user_id');
            $table->foreignId('branch_id');
            $table->string('count');
            $table->string('price_come');
            $table->string('price_sell');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forward_histories');
    }
};
