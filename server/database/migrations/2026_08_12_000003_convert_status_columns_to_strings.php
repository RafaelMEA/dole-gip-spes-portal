<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen remaining status columns from MySQL ENUMs to strings so new
     * statuses can be introduced without schema changes.
     */
    public function up(): void
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->string('status', 30)->default('submitted')->change();
            $table->index('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->change();
        });
    }

    public function down(): void
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->enum('status', ['submitted', 'under_review', 'approved', 'rejected'])->default('submitted')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'student'])->default('student')->change();
        });
    }
};
