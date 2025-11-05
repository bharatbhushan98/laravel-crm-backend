<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Remove bulk columns if they exist
            if (Schema::hasColumn('product_prices', 'bulk_discount_type')) {
                $table->dropColumn(['bulk_discount_type', 'bulk_discount_value', 'min_quantity']);
            }

            // Add simple discount fields (if not present)
            if (!Schema::hasColumn('product_prices', 'discount_type')) {
                $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('sell_price');
            }

            if (!Schema::hasColumn('product_prices', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
