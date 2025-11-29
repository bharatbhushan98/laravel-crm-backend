<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 🔹 Step 1: Drop foreign key first
            $table->dropForeign(['product_id']);

            // 🔹 Step 2: Then drop the columns
            $table->dropColumn(['product_id', 'buy_price', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // rollback - add columns back
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('buy_price', 10, 2)->nullable();
            $table->integer('quantity')->default(0);
        });
    }
};
