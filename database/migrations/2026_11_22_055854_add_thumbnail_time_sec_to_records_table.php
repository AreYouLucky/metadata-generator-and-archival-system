<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where in the video the thumbnail was taken (a key frame or "use current
 * frame"); null for an uploaded image. The JSON export's `visual.thumbnail`
 * reports it (PROJECT_SPEC §10).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->decimal('thumbnail_time_sec', 12, 3)->nullable()->after('thumbnail_frame_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('thumbnail_time_sec');
        });
    }
};
