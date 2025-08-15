<?php
// database/migrations/2025_08_15_101100_create_credit_holds_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('credit_holds', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->unsignedBigInteger('amount_micro');
      $t->unsignedBigInteger('captured_micro')->default(0);
      $t->enum('status', ['HELD','CAPTURED','RELEASED','EXPIRED','CANCELLED'])->index()->default('HELD');
      $t->string('idempotency_key')->unique();
      $t->string('capture_key')->nullable()->unique();
      $t->string('provider_ref')->nullable();
      $t->timestamp('expires_at')->index();
      $t->json('metadata')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('credit_holds'); }
};
