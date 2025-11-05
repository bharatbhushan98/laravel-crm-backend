<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                  ->constrained('customers')
                  ->onDelete('cascade');

            $table->date('date');
            $table->decimal('amount', 15, 2);

            $table->enum('payment', ['Paid', 'Pending', 'Refund', 'Failed', 'COD'])
                  ->default('Pending');

            $table->enum('status', ['Processing', 'Shipped', 'Delivered', 'Cancelled', 'Returned'])
                  ->default('Processing');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
