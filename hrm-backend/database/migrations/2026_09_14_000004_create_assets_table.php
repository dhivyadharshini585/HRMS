<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_id')->unique(); // e.g. AST-000245
            $table->enum('type', [
                'Laptop', 'Desktop', 'Monitor', 'Keyboard', 'Mouse',
                'Mobile', 'ID Card', 'Software License',
            ]);
            $table->string('name'); // e.g. Dell Latitude 5450
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->enum('condition', ['New', 'Good', 'Fair', 'Damaged'])->default('New');
            $table->enum('status', ['Unassigned', 'Assigned', 'In Maintenance', 'Retired'])->default('Unassigned');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
