<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('invoices', function (Blueprint $t) {
      $t->id();
      $t->string('invoice_number')->unique();
      $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
      $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

      $t->date('issue_date');
      $t->date('due_date')->nullable();

      $t->decimal('sub_total', 12, 2)->default(0);
      $t->decimal('discount_amount', 12, 2)->default(0);
      $t->decimal('tax_amount', 12, 2)->default(0);
      $t->decimal('shipping_amount', 12, 2)->default(0);
      $t->decimal('total_amount', 12, 2)->default(0);
      $t->decimal('amount_paid', 12, 2)->default(0);

      $t->enum('status', ['Draft','Sent','Partially Paid','Paid','Void'])->default('Draft');

      $t->string('currency', 3)->default('INR');
      $t->text('notes')->nullable();
      $t->text('terms')->nullable();
      $t->json('meta')->nullable();

      $t->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('invoices');
  }
};