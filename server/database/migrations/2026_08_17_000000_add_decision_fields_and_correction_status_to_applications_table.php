<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status', 30)->change();
            $table->text('decision_reason')->nullable()->after('remarks');
            $table->unsignedBigInteger('decided_by')->nullable()->after('approved_by');
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['decision_reason', 'decided_by', 'decided_at']);
            $table->string('status', 20)->change();
        });
    }
};
