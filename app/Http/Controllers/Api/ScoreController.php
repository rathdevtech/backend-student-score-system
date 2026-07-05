<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\ClassModel;
use App\Services\GradeService;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    protected $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /**
     * Get a spreadsheet-like grid of students and their scores for a given class and subject.
     * GET /api/scores?class_id=1&subject_id=2
     */
    public function index(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $classId = $request->class_id;
        $subjectId = $request->subject_id;

        $subject = \App\Models\Subject::findOrFail($subjectId);

        // Fetch all students in this class
        $students = Student::where('class_id', $classId)->get();

        // Fetch scores for this subject
        $scores = Score::where('subject_id', $subjectId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        // Map together so that every student has an entry (even if score is null/default)
        $grid = $students->map(function ($student) use ($scores, $subjectId, $subject) {
            $score = $scores->get($student->id);

            // Populate components scores based on the subject's configured components
            $comps = [];
            foreach ($subject->effective_components as $comp) {
                $key = $comp['key'];
                if ($score) {
                    if (isset($score->components_scores[$key])) {
                        $comps[$key] = (float) $score->components_scores[$key];
                    } else {
                        // fallback to column value if key matches legacy names
                        $comps[$key] = (float) ($score->$key ?? 0.00);
                    }
                } else {
                    $comps[$key] = 0.00;
                }
            }

            return [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_name_kh' => $student->name_kh,
                'gender' => $student->gender,
                'score_id' => $score ? $score->id : null,
                'subject_id' => (int) $subjectId,
                'components' => $comps,
                'total' => $score ? (float) $score->total : 0.00,
                'grade' => $score ? $score->grade : 'F',
                'is_scored' => $score ? true : false
            ];
        });

        return response()->json([
            'scores' => $grid,
            'score_components' => $subject->effective_components
        ]);
    }

    /**
     * Create or update score for a single student.
     * POST /api/scores
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'components' => 'required|array',
        ]);

        $subject = \App\Models\Subject::findOrFail($fields['subject_id']);
        $processed = $this->gradeService->processScore($fields['components'], $subject->effective_components);

        $score = Score::updateOrCreate(
            [
                'student_id' => $fields['student_id'],
                'subject_id' => $fields['subject_id']
            ],
            [
                'components_scores' => $processed['components_scores'],
                'total' => $processed['total'],
                'grade' => $processed['grade'],
                'quiz' => $processed['quiz'],
                'assignment' => $processed['assignment'],
                'midterm' => $processed['midterm'],
                'final' => $processed['final'],
            ]
        );

        return response()->json([
            'message' => 'Score saved successfully.',
            'score' => $score
        ]);
    }

    /**
     * Bulk save/update scores for a class.
     * POST /api/scores/bulk
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'scores' => 'required|array',
            'scores.*.student_id' => 'required|exists:students,id',
            'scores.*.components' => 'required|array',
        ]);

        $subjectId = $request->subject_id;
        $subject = \App\Models\Subject::findOrFail($subjectId);
        $savedScores = [];

        foreach ($request->scores as $item) {
            $processed = $this->gradeService->processScore($item['components'], $subject->effective_components);

            $score = Score::updateOrCreate(
                [
                    'student_id' => $item['student_id'],
                    'subject_id' => $subjectId
                ],
                [
                    'components_scores' => $processed['components_scores'],
                    'total' => $processed['total'],
                    'grade' => $processed['grade'],
                    'quiz' => $processed['quiz'],
                    'assignment' => $processed['assignment'],
                    'midterm' => $processed['midterm'],
                    'final' => $processed['final'],
                ]
            );

            $savedScores[] = $score;
        }

        return response()->json([
            'message' => 'Bulk scores updated successfully.',
            'count' => count($savedScores),
            'scores' => $savedScores
        ]);
    }

    /**
     * Update scores for a specific score ID.
     * PUT /api/scores/{id}
     */
    public function update(Request $request, $id)
    {
        $score = Score::find($id);
        if (!$score) {
            return response()->json(['message' => 'Score record not found'], 404);
        }

        $fields = $request->validate([
            'components' => 'required|array',
        ]);

        $subject = \App\Models\Subject::findOrFail($score->subject_id);
        $processed = $this->gradeService->processScore($fields['components'], $subject->effective_components);

        $score->update([
            'components_scores' => $processed['components_scores'],
            'total' => $processed['total'],
            'grade' => $processed['grade'],
            'quiz' => $processed['quiz'],
            'assignment' => $processed['assignment'],
            'midterm' => $processed['midterm'],
            'final' => $processed['final'],
        ]);

        return response()->json([
            'message' => 'Score updated successfully.',
            'score' => $score
        ]);
    }

    /**
     * Delete a score record.
     * DELETE /api/scores/{id}
     */
    public function destroy($id)
    {
        $score = Score::find($id);
        if (!$score) {
            return response()->json(['message' => 'Score record not found'], 404);
        }

        $score->delete();

        return response()->json(['message' => 'Score deleted successfully.']);
    }
}
