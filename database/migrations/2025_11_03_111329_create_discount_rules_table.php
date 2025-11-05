<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_order_amount', 10, 2)->default(0)->comment('Minimum order amount for discount');
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage')->comment('Type of discount');
            $table->decimal('discount_value', 10, 2)->default(0)->comment('Value of discount (percentage or fixed)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
