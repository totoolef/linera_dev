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
        Schema::create('onchain_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 8); // 'in' | 'out'
            $table->unsignedBigInteger('amount_units'); // 1 = 1 micro-crédit
            $table->unsignedBigInteger('asa_id');
            $table->string('tx_id', 128)->unique()->nullable();
            $table->string('status', 16)->default('pending'); // pending|confirmed|failed
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('onchain_transfers');
    }

};
