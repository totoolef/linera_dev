<?php
// database/migrations/2025_08_15_101000_add_billing_fields_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      // si ton JWT sub ≠ users.id (ex: string externe), décommente subject:
      // $t->string('subject')->nullable()->unique()->after('email');

      $t->unsignedBigInteger('balance_micro')->default(0)->after('remember_token');
      $t->unsignedBigInteger('reserved_micro')->default(0)->after('balance_micro');
      $t->index(['balance_micro','reserved_micro']);
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      // $t->dropColumn('subject');
      $t->dropIndex(['balance_micro','reserved_micro']);
      $t->dropColumn(['balance_micro','reserved_micro']);
    });
  }
};
