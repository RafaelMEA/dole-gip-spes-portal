<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_cycle_id')->constrained('program_cycles')->restrictOnDelete();
            $table->foreignId('deployment_site_id')->constrained('deployment_sites')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['program_cycle_id', 'status']);
            $table->index(['deployment_site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_slots');
    }
};
