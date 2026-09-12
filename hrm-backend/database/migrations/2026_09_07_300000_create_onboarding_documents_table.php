<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('onboarding_checklist_items')->nullOnDelete();
            
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            
            $table->timestamp('uploaded_at')->useCurrent();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });

        // Add the reverse foreign key from checklist items
        Schema::table('onboarding_checklist_items', function (Blueprint $table) {
            $table->foreign('document_id')->references('id')->on('onboarding_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
        });
        Schema::dropIfExists('onboarding_documents');
    }
};
