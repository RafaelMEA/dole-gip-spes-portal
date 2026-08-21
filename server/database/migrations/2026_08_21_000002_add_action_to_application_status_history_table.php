<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which workflow action produced each status history entry so the
     * timeline can distinguish a first submission from a resubmission and
     * similar transitions that share the same resulting status.
     */
    public function up(): void
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->string('action')->nullable()->after('status');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropColumn('action');
        });
    }
};
