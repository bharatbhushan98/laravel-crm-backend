<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('party_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('po_number')->nullable();
            $table->date('receiving_date')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->text('product_description')->nullable();
            $table->string('goods_category')->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('igst', 10, 2)->default(0);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('round_off', 10, 2)->default(0);
            $table->decimal('buy_price', 10, 2)->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchases');
    }
};
