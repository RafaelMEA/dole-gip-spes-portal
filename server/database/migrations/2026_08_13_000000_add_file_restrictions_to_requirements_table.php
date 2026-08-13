<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give requirement definitions optional upload restrictions.
     */
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->json('allowed_file_types')->nullable()->after('description');
            $table->unsignedBigInteger('max_file_size')->nullable()->after('allowed_file_types');
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn(['allowed_file_types', 'max_file_size']);
        });
    }
};
