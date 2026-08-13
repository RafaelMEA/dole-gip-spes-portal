<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand student_details with fields required for DOLE-eligibility checks
     * and host-agency matching. School/course/year/gwa/indigent already exist,
     * so only genuinely new columns are added here.
     */
    public function up(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('birthplace')->nullable();
            $table->date('birthdate')->nullable();
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->boolean('is_4ps_member')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropColumn(['address', 'birthplace', 'birthdate', 'sex', 'is_4ps_member']);
        });
    }
};
