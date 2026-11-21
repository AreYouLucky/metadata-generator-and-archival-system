<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Pending reviews queue (submitted records, oldest first) and the
     * admin Logs page (newest first, filtered by action).
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->index(['review_status', 'submitted_at']);
        });

        Schema::table('video_audit_logs', function (Blueprint $table) {
            $table->index('performed_at');
            $table->index(['action', 'performed_at']);
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->index('accessed_at');
            $table->index(['action', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['review_status', 'submitted_at']);
        });

        Schema::table('video_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['performed_at']);
            $table->dropIndex(['action', 'performed_at']);
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex(['accessed_at']);
            $table->dropIndex(['action', 'accessed_at']);
        });
    }
};
