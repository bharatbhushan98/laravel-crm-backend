<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'hsn_code')) {
                $table->string('hsn_code', 50)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('order_items', 'gst_rate')) {
                $table->decimal('gst_rate', 5, 2)->default(0)->after('hsn_code');
            }
            if (!Schema::hasColumn('order_items', 'cgst_rate')) {
                $table->decimal('cgst_rate', 5, 2)->default(0)->after('gst_rate');
            }
            if (!Schema::hasColumn('order_items', 'sgst_rate')) {
                $table->decimal('sgst_rate', 5, 2)->default(0)->after('cgst_rate');
            }
            if (!Schema::hasColumn('order_items', 'igst_rate')) {
                $table->decimal('igst_rate', 5, 2)->default(0)->after('sgst_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate']);
        });
    }
};
