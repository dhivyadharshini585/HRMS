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
        Schema::create('candidate_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->enum('from_status', ['New', 'Screening', 'Shortlisted', 'Rejected', 'Hired'])->nullable();
            $table->enum('to_status', ['New', 'Screening', 'Shortlisted', 'Rejected', 'Hired']);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['candidate_id', 'changed_at']);
            $table->index(['candidate_id', 'to_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_status_histories');
    }
};
