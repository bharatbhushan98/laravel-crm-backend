<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id');
            $table->timestamps();

            // 👇 Custom foreign key names to avoid duplication
            $table->foreign('product_id', 'fk_product_supplier_product')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');

            $table->foreign('supplier_id', 'fk_product_supplier_supplier')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
