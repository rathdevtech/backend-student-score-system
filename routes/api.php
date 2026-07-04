<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // Can be restricted to admin if preferred, but open for developer ease

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);

    // Dashboard Statistics
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Classes (CRUD checks admin in Controller constructor)
    Route::apiResource('classes', ClassController::class);
    Route::post('/classes/{id}/subjects', [ClassController::class, 'assignSubjects']);

    // Subjects (CRUD checks admin in Controller constructor)
    Route::post('/subjects/{id}/assign', [SubjectController::class, 'assign']);
    Route::apiResource('subjects', SubjectController::class);

    // Students (CRUD checks admin in Controller constructor)
    Route::post('/students/{id}/update', [StudentController::class, 'update']);
    Route::apiResource('students', StudentController::class);

    // Users (CRUD checks admin in Controller constructor)
    Route::post('/users/{id}/update', [UserController::class, 'update']);
    Route::apiResource('users', UserController::class);

    // Scores
    Route::post('/scores/bulk', [ScoreController::class, 'bulkStore']);
    Route::apiResource('scores', ScoreController::class);

    // Reports
    Route::get('/reports/class/{class_id}', [ReportController::class, 'classReport']);
    Route::get('/reports/student/{student_id}', [ReportController::class, 'studentTranscript']);
});
