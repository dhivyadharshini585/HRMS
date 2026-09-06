<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offer_letters', function (Blueprint $table) {
            $table->id();
            $table->string('offer_code', 50)->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_opening_id')->constrained('job_openings')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('offer_date');
            $table->date('joining_date');
            $table->date('expiry_date')->nullable();
            $table->string('designation');
            $table->enum('employment_type', ['Full-time', 'Part-time', 'Contract', 'Internship'])->default('Full-time');
            $table->string('work_location')->nullable();
            $table->decimal('salary_amount', 12, 2);
            $table->string('salary_currency', 10)->default('USD');
            $table->enum('salary_frequency', ['Annual', 'Monthly', 'Bi-weekly', 'Weekly', 'Hourly'])->default('Annual');
            $table->unsignedTinyInteger('probation_period_months')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->text('benefits')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->enum('offer_status', ['Draft', 'Sent', 'Accepted', 'Rejected', 'Withdrawn', 'Expired'])->default('Draft');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->text('response_remarks')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Additional indexes
            $table->index('candidate_id');
            $table->index('job_opening_id');
            $table->index('offer_status');
            $table->index('offer_date');
            $table->index('joining_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_letters');
    }
};
