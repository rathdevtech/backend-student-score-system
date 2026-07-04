<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SubjectController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('admin', only: ['store', 'update', 'destroy']),
        ];
    }

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
        ]);

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
            'name' => 'required|string|max:255|unique:subjects,name,' . $id,
        ]);

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
