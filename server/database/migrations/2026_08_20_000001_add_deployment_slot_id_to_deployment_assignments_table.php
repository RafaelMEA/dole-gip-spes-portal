<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('deployment_slot_id')->nullable()->after('application_id');
            $table->foreign('deployment_slot_id')->references('id')->on('deployment_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployment_assignments', function (Blueprint $table) {
            $table->dropForeign(['deployment_slot_id']);
            $table->dropColumn('deployment_slot_id');
        });
    }
};
