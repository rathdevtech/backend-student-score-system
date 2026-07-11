<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassController extends Controller
{

    public function index()
    {
        return response()->json(ClassModel::with(['teacher', 'subjects'])->get());
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'teacher_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $class = ClassModel::create($fields);

        return response()->json([
            'message' => 'Class created successfully.',
            'class' => $class->load(['teacher', 'subjects'])
        ], 201);
    }

    public function show($id)
    {
        $class = ClassModel::with(['teacher', 'subjects', 'students'])->find($id);

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        return response()->json($class);
    }

    public function update(Request $request, $id)
    {
        $class = ClassModel::find($id);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $fields = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'teacher_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $class->update($fields);

        return response()->json([
            'message' => 'Class updated successfully.',
            'class' => $class->load(['teacher', 'subjects'])
        ]);
    }

    public function destroy($id)
    {
        $class = ClassModel::find($id);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class->delete();

        return response()->json(['message' => 'Class deleted successfully.']);
    }

    /**
     * Assign subjects to the class.
     * Expects an array: [
     *   ['subject_id' => 1, 'teacher_id' => 2],
     *   ['subject_id' => 2, 'teacher_id' => null]
     * ]
     */
    public function assignSubjects(Request $request, $id)
    {
        $class = ClassModel::find($id);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.teacher_id' => 'nullable|exists:users,id',
        ]);

        $syncData = [];
        foreach ($request->assignments as $assign) {
            $syncData[$assign['subject_id']] = [
                'teacher_id' => $assign['teacher_id'] ?? null
            ];
        }

        $class->subjects()->sync($syncData);

        return response()->json([
            'message' => 'Subjects assigned to class successfully.',
            'class' => $class->load('subjects')
        ]);
    }
}
