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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('job_opening_id')->constrained('job_openings')->onDelete('cascade');
            $table->foreignId('interviewer_employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('interview_type', ['HR', 'Technical', 'Managerial', 'Final'])->default('Technical');
            $table->unsignedSmallInteger('interview_round')->default(1);
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->enum('mode', ['Online', 'In-person', 'Phone'])->default('Online');
            $table->string('location_or_link', 500)->nullable();
            $table->enum('status', ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'])->default('Scheduled');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['interviewer_employee_id', 'scheduled_at', 'status']);
            $table->index(['candidate_id', 'status']);
            $table->index(['job_opening_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
