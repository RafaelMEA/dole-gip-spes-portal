<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename "programs" is now a catalog; "applications" points at a cycle.
     * Repoint program_id -> program_cycle_id, widen status to a string, and
     * add review-approval columns.
     *
     * NOTE: MySQL refuses to drop an FK and the index it uses in the same
     * ALTER statement, and MySQL may reuse the composite unique index for the
     * applicant FK, so each drop/add gets its own Schema::table() call and the
     * applicant FK is dropped/restored around the reshape.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['applicant_id', 'program_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->renameColumn('program_id', 'program_cycle_id');
            $table->string('status', 20)->default('draft')->change();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unique(['applicant_id', 'program_cycle_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('program_cycle_id')->references('id')->on('program_cycles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['program_cycle_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['applicant_id', 'program_cycle_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'approved_by']);
            $table->renameColumn('program_cycle_id', 'program_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->unique(['applicant_id', 'program_id']);
        });
    }
};
