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
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('component_name');
            $table->enum('component_type', ['Earning', 'Deduction']);
            $table->string('component_source')->default('Fixed'); // e.g. Fixed, LOP, Statutory
            $table->decimal('amount', 10, 2)->default(0);
            $table->foreignId('statutory_payroll_rule_id')->nullable()->constrained('statutory_payroll_rules')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
