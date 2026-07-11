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
        
        if ($user && $user->role === 'student') {
            $studentId = $user->student_id;
            $student = Student::with('class.teacher')->find($studentId);
            
            if (!$student) {
                return response()->json([
                    'role' => 'student',
                    'summary' => [
                        'average_score' => 0,
                        'overall_grade' => 'F',
                        'class_rank' => 0,
                        'class_size' => 0,
                        'passing_subjects' => 0,
                        'total_subjects' => 0,
                    ],
                    'scores' => []
                ]);
            }
            
            $scores = Score::with('subject')->where('student_id', $studentId)->get();
            $subjectsCount = $scores->count();
            $averageScore = $subjectsCount > 0 ? round($scores->avg('total'), 2) : 0.00;
            
            $overallGrade = 'F';
            $gradeRules = \App\Models\GradeRule::all();
            foreach ($gradeRules as $rule) {
                if ($averageScore >= $rule->min_score && $averageScore <= $rule->max_score) {
                    $overallGrade = $rule->grade;
                    break;
                }
            }
            
            $allStudentsInClass = Student::where('class_id', $student->class_id)->get();
            $rankings = [];
            foreach ($allStudentsInClass as $s) {
                $sScores = Score::where('student_id', $s->id)->get();
                $rankings[$s->id] = $sScores->count() > 0 ? $sScores->avg('total') : 0;
            }
            arsort($rankings);
            $rankKeys = array_keys($rankings);
            $rank = array_search($studentId, $rankKeys) + 1;
            
            $passingSubjectsCount = $scores->where('total', '>=', 50)->count();
            
            return response()->json([
                'role' => 'student',
                'summary' => [
                    'average_score' => $averageScore,
                    'overall_grade' => $overallGrade,
                    'class_rank' => $rank,
                    'class_size' => count($allStudentsInClass),
                    'passing_subjects' => $passingSubjectsCount,
                    'total_subjects' => $subjectsCount,
                ],
                'scores' => $scores->map(function ($s) {
                    return [
                        'subject_id' => $s->subject_id,
                        'subject_name' => $s->subject->name,
                        'total' => (float)$s->total,
                        'grade' => $s->grade,
                    ];
                })
            ]);
        }
        
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
        $students = Student::with('user')->get();
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
