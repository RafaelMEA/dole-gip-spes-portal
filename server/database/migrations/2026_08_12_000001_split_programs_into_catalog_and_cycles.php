<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split the old "programs" table (which actually held cycle data) into a
     * static program catalog ("programs") and per-run cycles ("program_cycles").
     *
     * The old table is empty, so the rename + reshape is safe and the FK from
     * "applications" follows the table rename automatically.
     */
    public function up(): void
    {
        Schema::rename('programs', 'program_cycles');

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('program_cycles', function (Blueprint $table) {
            $table->unsignedBigInteger('program_id')->nullable()->after('id');
            $table->dropColumn('program_type');
        });

        Schema::table('program_cycles', function (Blueprint $table) {
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->unsignedBigInteger('program_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('program_cycles', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
        });

        Schema::dropIfExists('programs');

        Schema::table('program_cycles', function (Blueprint $table) {
            $table->enum('program_type', ['GIP', 'SPES'])->nullable()->after('name');
            $table->dropColumn('program_id');
        });

        Schema::rename('program_cycles', 'programs');
    }
};
