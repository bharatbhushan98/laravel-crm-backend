<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('invoice_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
      $t->foreignId('product_id')->constrained()->restrictOnDelete();
      $t->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();

      $t->string('description')->nullable();
      $t->decimal('quantity', 10, 2);
      $t->decimal('unit_price', 12, 2);
      $t->decimal('discount', 12, 2)->default(0);     // per-line discount
      $t->decimal('tax_rate', 5, 2)->default(0);      // % like 18.00
      $t->decimal('line_total', 12, 2);               // (qty*unit_price - discount) + tax

      $t->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('invoice_items');
  }
};