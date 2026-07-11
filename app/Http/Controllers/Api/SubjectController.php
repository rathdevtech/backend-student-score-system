<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{

    public function index()
    {
        $subjects = Subject::with(['classes.teacher'])->get()->map(function($subject) {
            $subject->classes->map(function($class) {
                if ($class->pivot->teacher_id) {
                    $class->assigned_teacher = \App\Models\User::find($class->pivot->teacher_id);
                } else {
                    $class->assigned_teacher = null;
                }
                return $class;
            });
            return $subject;
        });
        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255|unique:subjects',
            'score_components' => 'sometimes|required|array|min:1',
            'score_components.*.key' => 'required|string',
            'score_components.*.label' => 'required|string|max:50',
            'score_components.*.weight' => 'required|numeric|between:0,100',
        ]);

        if (isset($fields['score_components'])) {
            $sum = collect($fields['score_components'])->sum('weight');
            if ($sum != 100) {
                return response()->json(['message' => 'The sum of component weights must equal 100%.'], 422);
            }
        }

        $subject = Subject::create($fields);

        return response()->json([
            'message' => 'Subject created successfully.',
            'subject' => $subject
        ], 201);
    }

    public function show($id)
    {
        $subject = Subject::with('classes')->find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        return response()->json($subject);
    }

    public function update(Request $request, $id)
    {
        $subject = Subject::find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        $fields = $request->validate([
            'name'      => 'sometimes|required|string|max:255|unique:subjects,name,' . $id,
            'is_active' => 'sometimes|boolean',
            'score_components' => 'sometimes|required|array|min:1',
            'score_components.*.key' => 'required|string',
            'score_components.*.label' => 'required|string|max:50',
            'score_components.*.weight' => 'required|numeric|between:0,100',
        ]);

        if (isset($fields['score_components'])) {
            $sum = collect($fields['score_components'])->sum('weight');
            if ($sum != 100) {
                return response()->json(['message' => 'The sum of component weights must equal 100%.'], 422);
            }
        }

        $subject->update($fields);

        return response()->json([
            'message' => 'Subject updated successfully.',
            'subject' => $subject
        ]);
    }

    public function destroy($id)
    {
        $subject = Subject::find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        $subject->delete();

        return response()->json(['message' => 'Subject deleted successfully.']);
    }

    public function assign(Request $request, $id)
    {
        $subject = Subject::find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.class_id' => 'required|exists:classes,id',
            'assignments.*.teacher_id' => 'nullable|exists:users,id',
        ]);

        $syncData = [];
        foreach ($request->assignments as $assign) {
            $syncData[$assign['class_id']] = [
                'teacher_id' => $assign['teacher_id'] ?? null
            ];
        }

        $subject->classes()->sync($syncData);

        return response()->json([
            'message' => 'Classes and teachers synced for subject successfully.',
            'subject' => $subject->load('classes')
        ]);
    }
}
