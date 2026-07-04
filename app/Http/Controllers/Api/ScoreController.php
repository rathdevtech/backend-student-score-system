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

        // Fetch all students in this class
        $students = Student::where('class_id', $classId)->get();

        // Fetch scores for this subject
        $scores = Score::where('subject_id', $subjectId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        // Map together so that every student has an entry (even if score is null/default)
        $grid = $students->map(function ($student) use ($scores, $subjectId) {
            $score = $scores->get($student->id);

            return [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'gender' => $student->gender,
                'score_id' => $score ? $score->id : null,
                'subject_id' => (int) $subjectId,
                'quiz' => $score ? (float) $score->quiz : 0.00,
                'assignment' => $score ? (float) $score->assignment : 0.00,
                'midterm' => $score ? (float) $score->midterm : 0.00,
                'final' => $score ? (float) $score->final : 0.00,
                'total' => $score ? (float) $score->total : 0.00,
                'grade' => $score ? $score->grade : 'F',
                'is_scored' => $score ? true : false
            ];
        });

        return response()->json($grid);
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
            'quiz' => 'required|numeric|between:0,100',
            'assignment' => 'required|numeric|between:0,100',
            'midterm' => 'required|numeric|between:0,100',
            'final' => 'required|numeric|between:0,100',
        ]);

        $processed = $this->gradeService->processScore($fields);

        $score = Score::updateOrCreate(
            [
                'student_id' => $fields['student_id'],
                'subject_id' => $fields['subject_id']
            ],
            array_merge($fields, [
                'total' => $processed['total'],
                'grade' => $processed['grade']
            ])
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
            'scores.*.quiz' => 'required|numeric|between:0,100',
            'scores.*.assignment' => 'required|numeric|between:0,100',
            'scores.*.midterm' => 'required|numeric|between:0,100',
            'scores.*.final' => 'required|numeric|between:0,100',
        ]);

        $subjectId = $request->subject_id;
        $savedScores = [];

        foreach ($request->scores as $item) {
            $processed = $this->gradeService->processScore($item);

            $score = Score::updateOrCreate(
                [
                    'student_id' => $item['student_id'],
                    'subject_id' => $subjectId
                ],
                [
                    'quiz' => $item['quiz'],
                    'assignment' => $item['assignment'],
                    'midterm' => $item['midterm'],
                    'final' => $item['final'],
                    'total' => $processed['total'],
                    'grade' => $processed['grade']
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
            'quiz' => 'sometimes|required|numeric|between:0,100',
            'assignment' => 'sometimes|required|numeric|between:0,100',
            'midterm' => 'sometimes|required|numeric|between:0,100',
            'final' => 'sometimes|required|numeric|between:0,100',
        ]);

        $merged = array_merge([
            'quiz' => $score->quiz,
            'assignment' => $score->assignment,
            'midterm' => $score->midterm,
            'final' => $score->final,
        ], $fields);

        $processed = $this->gradeService->processScore($merged);

        $score->update(array_merge($fields, [
            'total' => $processed['total'],
            'grade' => $processed['grade']
        ]));

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
