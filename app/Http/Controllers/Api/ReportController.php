<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\Score;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /**
     * Get class performance report.
     * GET /api/reports/class/{class_id}
     */
    public function classReport($classId)
    {
        $class = ClassModel::find($classId);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $students = Student::where('class_id', $classId)->with('user')->get();
        if ($students->isEmpty()) {
            return response()->json([
                'class' => $class,
                'statistics' => [
                    'total_students' => 0,
                    'class_average' => 0,
                    'pass_count' => 0,
                    'fail_count' => 0,
                    'pass_percentage' => 0,
                ],
                'rankings' => []
            ]);
        }

        // Calculate student averages
        $rankings = [];
        $classTotalAccumulator = 0;
        $scoredStudentsCount = 0;

        foreach ($students as $student) {
            $scores = Score::where('student_id', $student->id)->get();
            $subjectsCount = $scores->count();
            
            if ($subjectsCount > 0) {
                $avgScore = $scores->avg('total');
                $overallGrade = $this->gradeService->determineGrade($avgScore);
                $classTotalAccumulator += $avgScore;
                $scoredStudentsCount++;
            } else {
                $avgScore = 0;
                $overallGrade = 'F';
            }

            $rankings[] = [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_name_kh' => $student->name_kh,
                'gender' => $student->gender,
                'photo' => $student->photo,
                'subjects_graded' => $subjectsCount,
                'average_score' => round($avgScore, 2),
                'overall_grade' => $overallGrade,
                'status' => $avgScore >= 50 ? 'Pass' : 'Fail'
            ];
        }

        // Sort rankings descending by average score
        usort($rankings, function ($a, $b) {
            return $b['average_score'] <=> $a['average_score'];
        });

        // Add rank number (handling ties elegantly is a bonus, but simple index + 1 is fine)
        foreach ($rankings as $index => &$r) {
            $r['rank'] = $index + 1;
        }

        $totalStudents = $students->count();
        $classAverage = $scoredStudentsCount > 0 ? round($classTotalAccumulator / $scoredStudentsCount, 2) : 0;
        
        $passCount = collect($rankings)->where('status', 'Pass')->count();
        $failCount = $totalStudents - $passCount;
        $passPercentage = $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 2) : 0;

        return response()->json([
            'class' => $class,
            'statistics' => [
                'total_students' => $totalStudents,
                'class_average' => $classAverage,
                'pass_count' => $passCount,
                'fail_count' => $failCount,
                'pass_percentage' => $passPercentage,
            ],
            'rankings' => $rankings
        ]);
    }

    /**
     * Get student report card / transcript details.
     * GET /api/reports/student/{student_id}
     */
    public function studentTranscript(Request $request, $studentId)
    {
        $user = $request->user();
        if ($user && $user->role === 'student' && $user->student_id != $studentId) {
            return response()->json(['message' => 'Unauthorized to view other student transcripts.'], 403);
        }

        $student = Student::with('class.teacher')->find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        // Get student scores
        $scores = Score::with('subject')
            ->where('student_id', $studentId)
            ->get();

        // Calculate overall average
        $subjectsCount = $scores->count();
        $averageScore = $subjectsCount > 0 ? round($scores->avg('total'), 2) : 0.00;
        $overallGrade = $this->gradeService->determineGrade($averageScore);

        // Find rank within their class
        $allStudents = Student::where('class_id', $student->class_id)->get();
        $rankings = [];

        foreach ($allStudents as $s) {
            $sScores = Score::where('student_id', $s->id)->get();
            $rankings[$s->id] = $sScores->count() > 0 ? $sScores->avg('total') : 0;
        }

        arsort($rankings);
        $rankKeys = array_keys($rankings);
        $rank = array_search($studentId, $rankKeys) + 1;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'name_kh' => $student->name_kh,
                'gender' => $student->gender,
                'photo' => $student->photo,
                'class_name' => $student->class->name,
                'teacher_name' => $student->class->teacher ? $student->class->teacher->name : 'N/A',
                'teacher_name_kh' => ($student->class->teacher && $student->class->teacher->name_kh) ? $student->class->teacher->name_kh : null,
            ],
            'scores' => $scores->map(function ($s) {
                $breakdown = [];
                foreach ($s->subject->effective_components as $comp) {
                    $key = $comp['key'];
                    $scoreVal = 0.00;
                    if (isset($s->components_scores[$key])) {
                        $scoreVal = (float) $s->components_scores[$key];
                    } else {
                        $scoreVal = (float) ($s->$key ?? 0.00);
                    }
                    $breakdown[] = [
                        'label' => $comp['label'],
                        'weight' => $comp['weight'],
                        'score' => $scoreVal
                    ];
                }

                return [
                    'subject_name' => $s->subject->name,
                    'quiz' => (float) $s->quiz,
                    'assignment' => (float) $s->assignment,
                    'midterm' => (float) $s->midterm,
                    'final' => (float) $s->final,
                    'total' => (float) $s->total,
                    'grade' => $s->grade,
                    'components_breakdown' => $breakdown
                ];
            }),
            'summary' => [
                'subjects_count' => $subjectsCount,
                'average_score' => $averageScore,
                'overall_grade' => $overallGrade,
                'status' => $averageScore >= 50 ? 'Pass' : 'Fail',
                'class_rank' => $rank,
                'class_size' => count($allStudents)
            ]
        ]);
    }
}
