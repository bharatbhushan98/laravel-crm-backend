<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_price')) {
                $table->dropColumn('unit_price'); // remove unit_price
            }
        });
    }

    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable();
        });
    }
};
