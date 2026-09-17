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
        Schema::table('candidates', function (Blueprint $table) {
            $table->integer('ai_match_score')->nullable()->after('status');
            $table->text('ai_extracted_skills')->nullable()->after('ai_match_score');
            $table->text('ai_extracted_experience')->nullable()->after('ai_extracted_skills');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['ai_match_score', 'ai_extracted_skills', 'ai_extracted_experience']);
        });
    }
};
