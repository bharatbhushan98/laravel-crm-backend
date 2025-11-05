<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Company ka naam
            $table->string('gst_number')->nullable(); // GST number
            $table->text('address')->nullable();    // Address
            $table->string('email')->nullable();    // Email
            $table->string('phone')->nullable();    // Phone
            $table->text('bank_details')->nullable(); // Bank info
            $table->string('logo')->nullable();     // Logo file path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
