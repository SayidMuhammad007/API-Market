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
        Schema::table('basket_prices', function (Blueprint $table) {
            $table->string('qty')->nullable();
            $table->string('old_price_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('basket_prices', function (Blueprint $table) {
            $table->dropColumn('qty');
            $table->dropColumn('old_price_id');
        });
    }
};
