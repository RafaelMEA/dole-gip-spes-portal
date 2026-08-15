<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The staff dashboard's default query filters applications by status and
     * orders by submitted_at, so a composite index covers both the WHERE and
     * the ORDER BY in a single pass.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->index(['status', 'submitted_at'], 'applications_status_submitted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_status_submitted_at_index');
        });
    }
};
