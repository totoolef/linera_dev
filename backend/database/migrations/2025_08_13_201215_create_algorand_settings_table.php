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
        Schema::create('algorand_settings', function (Blueprint $table) {
            $table->id();
            $table->string('network', 16)->default('testnet');
            $table->unsignedBigInteger('asa_id')->nullable();
            $table->string('bank_address', 128);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('algorand_settings');
    }
};
