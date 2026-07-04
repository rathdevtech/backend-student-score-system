<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
