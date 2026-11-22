<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the thumbnail is the default picked after processing (true) or one
 * an encoder chose (false). Only a default may later be swapped for a key
 * frame with faces once the `objects` stage has counted them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->boolean('thumbnail_is_default')->default(false)->after('thumbnail_time_sec');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('thumbnail_is_default');
        });
    }
};
