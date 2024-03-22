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
        Schema::table('returned_stores', function (Blueprint $table) {
            $table->string('price');
            $table->foreignId('price_id')->nullable();
            $table->foreignId('type_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returned_stores', function (Blueprint $table) {
            $table->dropColumn('price');
            $table->dropColumn('price_id');
            $table->dropColumn('type_id');
        });
    }
};
