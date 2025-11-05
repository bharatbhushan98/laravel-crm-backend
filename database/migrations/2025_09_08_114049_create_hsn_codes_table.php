<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('hsn_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // relation with categories
            $table->string('hsn_code');
            $table->decimal('gst_rate', 5, 2); // e.g. 5.00, 12.00, 18.00
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('hsn_codes');
    }
};
