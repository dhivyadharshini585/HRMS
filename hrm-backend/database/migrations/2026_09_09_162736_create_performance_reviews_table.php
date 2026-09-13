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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('employees')->cascadeOnDelete();
            $table->tinyInteger('rating_technical_skills')->unsigned()->default(0); // 1-5
            $table->tinyInteger('rating_communication')->unsigned()->default(0);
            $table->tinyInteger('rating_teamwork')->unsigned()->default(0);
            $table->tinyInteger('rating_leadership')->unsigned()->default(0);
            $table->tinyInteger('rating_productivity')->unsigned()->default(0);
            $table->tinyInteger('rating_problem_solving')->unsigned()->default(0);
            $table->tinyInteger('rating_attendance')->unsigned()->default(0);
            $table->tinyInteger('rating_goal_achievement')->unsigned()->default(0);
            $table->text('comments')->nullable();
            $table->string('status')->default('draft'); // draft, submitted, acknowledged
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
