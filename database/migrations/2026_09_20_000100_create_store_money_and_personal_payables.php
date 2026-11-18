<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Retired before release: no new finance tables are created.
        // Existing production tables, if any, are deliberately left untouched.
    }

    public function down(): void
    {
        // Historical financial data must never be dropped by a rollback.
    }
};
