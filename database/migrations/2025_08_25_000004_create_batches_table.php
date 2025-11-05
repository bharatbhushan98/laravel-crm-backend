<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('batch_number')->unique();
            $table->integer('stock_level');
            $table->date('expiry_date');
            $table->string('status')->default('Available'); // Low Stock, Expired, Available
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('batches');
    }
};
