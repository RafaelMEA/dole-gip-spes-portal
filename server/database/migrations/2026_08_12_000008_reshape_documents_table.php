<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generalize "documents" into per-requirement uploads: the raw type and
     * boolean verification are replaced by a requirement FK and a verification
     * workflow (status + optional rejection reason).
     */
    public function up(): void
    {
        Schema::rename('documents', 'application_documents');

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropColumn(['doc_type', 'is_verified']);
            $table->unsignedBigInteger('requirement_id')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->foreign('requirement_id')->references('id')->on('requirements')->nullOnDelete();
            $table->unique(['application_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['requirement_id']);
            $table->dropUnique(['application_id', 'requirement_id']);
            $table->dropColumn(['requirement_id', 'mime_type', 'file_size', 'verification_status', 'rejection_reason']);
            $table->string('document_type');
            $table->boolean('is_verified')->default(false);
        });

        Schema::rename('application_documents', 'documents');
    }
};
