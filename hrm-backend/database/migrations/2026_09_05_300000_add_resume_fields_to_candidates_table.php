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
            $table->string('resume_path', 255)->nullable()->after('notes');
            $table->string('resume_original_name', 255)->nullable()->after('resume_path');
            $table->string('resume_mime_type', 100)->nullable()->after('resume_original_name');
            $table->unsignedBigInteger('resume_size')->nullable()->after('resume_mime_type');
            $table->timestamp('resume_uploaded_at')->nullable()->after('resume_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'resume_path',
                'resume_original_name',
                'resume_mime_type',
                'resume_size',
                'resume_uploaded_at',
            ]);
        });
    }
};
