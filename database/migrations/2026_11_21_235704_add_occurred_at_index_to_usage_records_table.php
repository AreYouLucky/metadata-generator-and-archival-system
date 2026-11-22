<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin Usage page and overview sum usage for a date range across
     * every encoder; (user_id, occurred_at) only serves one encoder's range.
     */
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex(['occurred_at']);
        });
    }
};
