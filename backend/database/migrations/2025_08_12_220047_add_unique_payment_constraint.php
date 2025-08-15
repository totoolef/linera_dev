<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) On supprime proprement une éventuelle contrainte portant ce nom (si elle existe)
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_provider_external_unique');

        // 2) On crée un INDEX UNIQUE idempotent (refuse les doublons, comme une contrainte unique)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS payments_provider_external_uidx ON payments (provider, external_id)');
    }

    public function down(): void
    {
        // On supprime l’index unique s’il existe
        DB::statement('DROP INDEX IF EXISTS payments_provider_external_uidx');

        // (Optionnel) Si tu veux restaurer une contrainte unique classique :
        // DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_provider_external_unique UNIQUE (provider, external_id)');
    }
};
