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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('current_location', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('highest_qualification', 255)->nullable();
            $table->decimal('total_experience_years', 4, 1)->nullable()->default(0);
            $table->string('current_company', 255)->nullable();
            $table->string('current_designation', 255)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->string('source', 100)->nullable();
            $table->foreignId('job_opening_id')->constrained('job_openings')->cascadeOnDelete();
            $table->enum('status', ['New', 'Screening', 'Shortlisted', 'Rejected', 'Hired'])->default('New');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('email');
            $table->index('job_opening_id');
            $table->index('candidate_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
