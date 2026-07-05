<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StudentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = Student::with('class.teacher');

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'name' => 'required|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'photo' => 'nullable|file|max:10240',
        ]);

        if ($request->hasFile('photo')) {
            try {
                $path = $request->file('photo')->store('students', 'public');
                $fields['photo'] = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip photo upload if it fails (e.g., due to filesystem limitations)
                // Continue with student creation
            }
        }

        $student = Student::create($fields);

        return response()->json([
            'message' => 'Student created successfully.',
            'student' => $student->load('class')
        ], 201);
    }

    public function show($id)
    {
        $student = Student::with(['class', 'scores.subject'])->find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        return response()->json($student);
    }

    public function update(Request $request, $id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $fields = $request->validate([
            'class_id'  => 'sometimes|required|exists:classes,id',
            'name'      => 'sometimes|required|string|max:255',
            'name_kh'   => 'nullable|string|max:255',
            'gender'    => 'nullable|string|in:Male,Female,Other',
            'photo'     => 'nullable|file|max:10240',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('photo')) {
            try {
                // Delete old photo if exists
                if ($student->photo) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $student->photo));
                }

                $path = $request->file('photo')->store('students', 'public');
                $fields['photo'] = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip photo upload if it fails (e.g., due to filesystem limitations)
                // Continue with student update
            }
        }

        $student->update($fields);

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->load('class')
        ]);
    }

    public function destroy($id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        // Delete photo from storage if exists
        if ($student->photo) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $student->photo));
        }

        $student->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }
}
