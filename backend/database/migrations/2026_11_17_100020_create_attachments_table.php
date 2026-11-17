<?php

use App\Enums\AttachmentDocumentType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An uploaded signature image is NOT an authenticated system approval.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->string('document_type', 40);
            $table->string('original_name', 255)->nullable();
            $table->string('stored_path', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64);
            $table->integer('version_no')->default(1);
            $table->boolean('is_signed_copy')->default(false);
            Columns::fk($table, 'required_permission_id', 'permissions', nullable: true);
            Columns::fk($table, 'uploaded_by', 'users', nullable: true);
            $table->dateTime('uploaded_at')->nullable();
            Columns::std($table);
            $table->index(['attachable_type', 'attachable_id']);
        });

        Columns::enumCheck('attachments', 'document_type', AttachmentDocumentType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
