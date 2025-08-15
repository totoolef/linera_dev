<?php
// database/migrations/2025_08_15_101200_create_credit_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('credit_transactions', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained()->cascadeOnDelete();
      $t->foreignId('credit_hold_id')->nullable()->constrained('credit_holds')->nullOnDelete();
      $t->enum('type', ['HOLD','CAPTURE','RELEASE','EXPIRE','ADJUST']);
      $t->bigInteger('delta_micro');
      $t->json('metadata')->nullable();
      $t->timestamps();
      $t->index(['user_id','created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('credit_transactions'); }
};
