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
        Schema::create('interview_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->unique()->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('interviewer_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('technical_rating')->nullable();
            $table->unsignedTinyInteger('communication_rating')->nullable();
            $table->unsignedTinyInteger('problem_solving_rating')->nullable();
            $table->unsignedTinyInteger('cultural_fit_rating')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('comments')->nullable();
            $table->enum('recommendation', ['Strong Hire', 'Hire', 'Hold', 'No Hire']);
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['interviewer_employee_id', 'recommendation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_feedback');
    }
};
