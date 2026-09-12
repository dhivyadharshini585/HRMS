<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('onboardings')->cascadeOnDelete();
            $table->unsignedBigInteger('document_id')->nullable(); // Foreign key added later to avoid circular issues
            
            $table->enum('item_type', [
                'Offer Letter',
                'ID Proof',
                'Address Proof',
                'PAN',
                'Bank Details',
                'Educational Certificates',
                'Previous Experience Documents',
                'Photograph',
                'NDA',
                'Company Policy Acceptance'
            ]);
            
            $table->enum('status', ['Pending', 'Submitted', 'Verified', 'Rejected', 'Not Applicable'])->default('Pending');
            
            $table->text('remarks')->nullable();
            
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['onboarding_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_checklist_items');
    }
};
