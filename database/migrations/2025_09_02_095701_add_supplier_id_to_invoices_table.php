<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add supplier_id (nullable so old invoices stay valid)
            $table->unsignedBigInteger('supplier_id')->nullable()->after('order_id');

            // Set up foreign key to suppliers table
            $table->foreign('supplier_id')
                  ->references('id')->on('suppliers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
