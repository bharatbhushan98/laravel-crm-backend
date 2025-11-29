<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->date('delivery_deadline')->nullable();
            $table->enum('status', ['Pending', 'Order Created', 'Completed'])->default('Pending');
            $table->timestamps();

            // 👇 Custom unique foreign key name
            $table->foreign('supplier_id', 'fk_purchase_orders_supplier')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
