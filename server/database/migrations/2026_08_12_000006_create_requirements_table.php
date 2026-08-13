<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standardized list of requirement types a cycle expects students to upload.
     */
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('program_cycle_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_cycle_id')->constrained('program_cycles')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('requirements')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['program_cycle_id', 'requirement_id'], 'cycle_requirement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_cycle_requirements');
        Schema::dropIfExists('requirements');
    }
};
