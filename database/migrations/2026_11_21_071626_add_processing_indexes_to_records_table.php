<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The record history filters by processing status and, for admins (who see
     * every encoder's records), sorts by creation date across all records.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->index('processing_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropIndex(['created_at']);
        });
    }
};
