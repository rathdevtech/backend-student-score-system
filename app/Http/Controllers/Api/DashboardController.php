<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Score;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $totalStudents = Student::count();
        $totalClasses = ClassModel::count();
        $totalSubjects = Subject::count();
        $totalTeachers = User::where('role', 'teacher')->count();

        // Calculate pass/fail ratios across all scores
        $allScores = Score::all();
        $totalScoresCount = $allScores->count();
        
        $passCount = $allScores->where('total', '>=', 50)->count();
        $failCount = $totalScoresCount - $passCount;
        $passPercentage = $totalScoresCount > 0 ? round(($passCount / $totalScoresCount) * 100, 2) : 0;

        // Class performance (average score per class)
        $classes = ClassModel::all();
        $classPerformance = [];

        foreach ($classes as $class) {
            $classStudentIds = Student::where('class_id', $class->id)->pluck('id');
            $classScores = Score::whereIn('student_id', $classStudentIds)->get();
            $avgTotal = $classScores->count() > 0 ? round($classScores->avg('total'), 2) : 0.00;

            $classPerformance[] = [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'student_count' => $classStudentIds->count(),
                'average_score' => $avgTotal
            ];
        }

        // Top students (based on total average of scores)
        $students = Student::all();
        $studentAverages = [];

        foreach ($students as $student) {
            $studentScores = Score::where('student_id', $student->id)->get();
            if ($studentScores->isNotEmpty()) {
                $avg = $studentScores->avg('total');
                $studentAverages[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'class_name' => $student->class ? $student->class->name : 'N/A',
                    'average_score' => round($avg, 2)
                ];
            }
        }

        usort($studentAverages, function($a, $b) {
            return $b['average_score'] <=> $a['average_score'];
        });

        $topStudents = array_slice($studentAverages, 0, 5);

        return response()->json([
            'role' => $user->role,
            'summary' => [
                'total_students' => $totalStudents,
                'total_classes' => $totalClasses,
                'total_subjects' => $totalSubjects,
                'total_teachers' => $totalTeachers,
                'pass_percentage' => $passPercentage,
                'pass_count' => $passCount,
                'fail_count' => $failCount,
            ],
            'class_performance' => $classPerformance,
            'top_students' => $topStudents
        ]);
    }
}
