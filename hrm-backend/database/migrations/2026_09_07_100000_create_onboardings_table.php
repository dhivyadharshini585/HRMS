<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('offer_letter_id')->constrained('offer_letters')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            
            $table->date('joining_date');
            
            $table->enum('status', ['Not Started', 'In Progress', 'Completed', 'Cancelled'])->default('Not Started');
            
            $table->enum('it_account_status', ['Pending', 'Completed', 'Not Applicable'])->default('Pending');
            $table->text('it_account_remarks')->nullable();
            
            $table->enum('laptop_allocation_status', ['Pending', 'Completed', 'Not Applicable'])->default('Pending');
            $table->text('laptop_allocation_remarks')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique('candidate_id');
            $table->unique('offer_letter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboardings');
    }
};
