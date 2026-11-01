<?php

use Illuminate\Database\Migrations\Migration;

// Historical migration identifier retained for Laravel migration-ledger compatibility.
// The feature has been retired: a fresh installation must not recreate its table.
// Existing databases are left unchanged; use a verified case-free clone instead.
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty; retired feature.
    }

    public function down(): void
    {
        // Intentionally empty; no destructive rollback.
    }
};
