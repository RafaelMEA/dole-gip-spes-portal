<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "slots_available" is derived from active deployment_assignments, so it is
     * dropped; host agencies gain contact/status columns.
     */
    public function up(): void
    {
        Schema::table('host_agencies', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dropColumn('slots_available');
        });
    }

    public function down(): void
    {
        Schema::table('host_agencies', function (Blueprint $table) {
            $table->unsignedInteger('slots_available')->default(0);
            $table->dropColumn(['email', 'is_active']);
        });
    }
};
