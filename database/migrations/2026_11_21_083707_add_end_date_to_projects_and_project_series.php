<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events often run for several days: `date` is now the start date and
     * `end_date` the last day (null for a one-day event). The public catalogue
     * (Phase 4) filters by date, so the end date is indexed like the start.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date')->index();
        });

        Schema::table('project_series', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['end_date']);
            $table->dropColumn('end_date');
        });

        Schema::table('project_series', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
