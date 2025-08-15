<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jwt_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('nonce', 64)->unique();
            $table->unsignedBigInteger('iat');
            $table->unsignedBigInteger('exp');
            $table->string('iss', 128)->index(); // adresse Algorand
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('jwt_nonces');
    }
};
