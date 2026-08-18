<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_sites', function (Blueprint $table) {
            $table->foreignId('host_agency_id')->after('id')->constrained('host_agencies')->restrictOnDelete();
            $table->string('contact_person')->nullable()->after('region');
            $table->string('contact_number')->nullable()->after('contact_person');
            $table->string('email')->nullable()->after('contact_number');
            $table->text('description')->nullable()->after('email');

            $table->index('host_agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_sites', function (Blueprint $table) {
            $table->dropForeign(['host_agency_id']);
            $table->dropIndex(['host_agency_id']);
            $table->dropColumn([
                'host_agency_id',
                'contact_person',
                'contact_number',
                'email',
                'description',
            ]);
        });
    }
};
