<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('requested_qty')->default(0);
            $table->decimal('buy_price', 10, 2)->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_orders');
    }
};
