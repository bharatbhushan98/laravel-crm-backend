<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('hsn_code')->nullable()->after('description');
            $table->decimal('cgst_rate', 5, 2)->default(0)->after('discount');
            $table->decimal('sgst_rate', 5, 2)->default(0)->after('cgst_rate');
            $table->decimal('igst_rate', 5, 2)->default(0)->after('sgst_rate');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'cgst_rate', 'sgst_rate', 'igst_rate']);
        });
    }
};
