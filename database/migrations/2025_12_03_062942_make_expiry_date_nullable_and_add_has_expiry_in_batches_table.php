<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->change();

            $table->boolean('has_expiry')->default(true)->after('stock_level');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->date('expiry_date')->nullable(false)->change();
            $table->dropColumn('has_expiry');
        });
    }
};