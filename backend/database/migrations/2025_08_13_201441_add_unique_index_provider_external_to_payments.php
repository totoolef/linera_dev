<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Si la contrainte existe déjà, on ne fait rien
        if ($this->constraintExists('payments', 'payments_provider_external_unique')) {
            return;
        }

        // Sinon, on l'ajoute proprement
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_provider_external_unique UNIQUE (provider, external_id)');
    }

    public function down(): void
    {
        // On supprime si elle existe
        if ($this->constraintExists('payments', 'payments_provider_external_unique')) {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_provider_external_unique');
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM pg_constraint c
            JOIN pg_class t ON c.conrelid = t.oid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE t.relname = :table
              AND c.conname = :constraint
            LIMIT 1
        SQL;

        return (bool) DB::selectOne($sql, ['table' => $table, 'constraint' => $constraint]);
    }
};
