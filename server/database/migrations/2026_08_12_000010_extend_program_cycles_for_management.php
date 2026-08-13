<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give staff the ability to publish/unpublish cycles and schedule
     * deployment windows.
     *
     * "status" is the stored publication state using the existing
     * ProgramCycleStatus values: draft (hidden), archived (hidden), or a
     * published phase. The date-derived phase (upcoming/open/closed) is still
     * resolved at read time by the model, so cycles transition automatically.
     *
     * Existing cycles were all visible before, so backfill them as published.
     */
    public function up(): void
    {
        Schema::table('program_cycles', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('description');
            $table->date('deployment_start')->nullable()->after('application_deadline');
            $table->date('deployment_end')->nullable()->after('deployment_start');
        });

        DB::table('program_cycles')->update(['status' => 'upcoming']);

        Schema::table('program_cycles', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('program_cycles', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'deployment_start', 'deployment_end']);
        });
    }
};
