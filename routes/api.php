<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);

    // Dashboard Statistics (controller handles student filtering)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Dynamic Roles & Permissions management
    Route::middleware('permission:manage_roles_permissions')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
    });

    // Users Management
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:view_users');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:view_users');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:create_users');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:edit_users');
    Route::post('/users/{id}/update', [UserController::class, 'update'])->middleware('permission:edit_users');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:delete_users');

    // Classes Management
    Route::get('/classes', [ClassController::class, 'index'])->middleware('permission:view_classes');
    Route::get('/classes/{id}', [ClassController::class, 'show'])->middleware('permission:view_classes');
    Route::post('/classes', [ClassController::class, 'store'])->middleware('permission:create_classes');
    Route::put('/classes/{id}', [ClassController::class, 'update'])->middleware('permission:edit_classes');
    Route::post('/classes/{id}/subjects', [ClassController::class, 'assignSubjects'])->middleware('permission:edit_classes');
    Route::delete('/classes/{id}', [ClassController::class, 'destroy'])->middleware('permission:delete_classes');

    // Subjects Management
    Route::get('/subjects', [SubjectController::class, 'index'])->middleware('permission:view_subjects');
    Route::get('/subjects/{id}', [SubjectController::class, 'show'])->middleware('permission:view_subjects');
    Route::post('/subjects', [SubjectController::class, 'store'])->middleware('permission:create_subjects');
    Route::put('/subjects/{id}', [SubjectController::class, 'update'])->middleware('permission:edit_subjects');
    Route::post('/subjects/{id}/assign', [SubjectController::class, 'assign'])->middleware('permission:edit_subjects');
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->middleware('permission:delete_subjects');

    // Students Management
    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:view_students');
    Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('permission:view_students');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:create_students');
    Route::post('/students/{id}/update', [StudentController::class, 'update'])->middleware('permission:edit_students');
    Route::delete('/students/{id}', [StudentController::class, 'destroy'])->middleware('permission:delete_students');

    // Scores Management
    Route::get('/scores', [ScoreController::class, 'index'])->middleware('permission:view_scores');
    Route::get('/scores/{id}', [ScoreController::class, 'show'])->middleware('permission:view_scores');
    Route::post('/scores', [ScoreController::class, 'store'])->middleware('permission:create_scores');
    Route::post('/scores/bulk', [ScoreController::class, 'bulkStore'])->middleware('permission:create_scores');
    Route::put('/scores/{id}', [ScoreController::class, 'update'])->middleware('permission:edit_scores');
    Route::delete('/scores/{id}', [ScoreController::class, 'destroy'])->middleware('permission:delete_scores');

    // Reports Management
    Route::get('/reports/class/{class_id}', [ReportController::class, 'classReport'])->middleware('permission:view_scores');
    Route::get('/reports/student/{student_id}', [ReportController::class, 'studentTranscript']); // Controller checks ownership
});
