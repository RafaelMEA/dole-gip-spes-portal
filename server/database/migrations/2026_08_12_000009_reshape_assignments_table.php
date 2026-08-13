<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generalize "assignments" (GIP only) into "deployment_assignments" usable
     * by any program: add deployment site, position, and an assignment workflow.
     */
    public function up(): void
    {
        Schema::rename('assignments', 'deployment_assignments');

        Schema::table('deployment_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('deployment_site_id')->nullable();
            $table->string('position')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('remarks')->nullable();
        });

        Schema::table('deployment_assignments', function (Blueprint $table) {
            $table->foreign('deployment_site_id')->references('id')->on('deployment_sites')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployment_assignments', function (Blueprint $table) {
            $table->dropForeign(['deployment_site_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropColumn(['deployment_site_id', 'position', 'status', 'assigned_by', 'assigned_at', 'remarks']);
        });

        Schema::rename('deployment_assignments', 'assignments');
    }
};
