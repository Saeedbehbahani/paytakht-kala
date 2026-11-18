<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing production expense records are intentionally preserved.
    }
};
