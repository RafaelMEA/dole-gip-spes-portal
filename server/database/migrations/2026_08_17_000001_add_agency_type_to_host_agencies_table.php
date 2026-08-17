<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_agencies', function (Blueprint $table) {
            $table->string('agency_type', 20)->default('other')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('host_agencies', function (Blueprint $table) {
            $table->dropColumn('agency_type');
        });
    }
};
