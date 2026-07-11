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
        // 1. Classes Table
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 2. Subjects Table
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // 3. Class-Subject Pivot Table (assigning subject to class and optionally teacher)
        Schema::create('class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 4. Students Table
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->string('gender')->nullable();
            $table->timestamps();
        });

        // 5. Scores Table
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->decimal('quiz', 5, 2)->default(0);
            $table->decimal('assignment', 5, 2)->default(0);
            $table->decimal('midterm', 5, 2)->default(0);
            $table->decimal('final', 5, 2)->default(0);
            $table->decimal('total', 5, 2)->default(0);
            $table->string('grade')->default('F');
            $table->timestamps();
            
            // Unique score record per student per subject
            $table->unique(['student_id', 'subject_id']);
        });

        // 6. Grade Rules Table
        Schema::create('grade_rules', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->string('grade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_rules');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('students');
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classes');
    }
};
