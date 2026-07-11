<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Student::with(['class.teacher', 'user']);

        if ($user && $user->role === 'student') {
            $query->where('id', $user->student_id);
        } else {
            if ($request->has('class_id')) {
                $query->where('class_id', $request->class_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('name_kh', 'like', '%' . $search . '%');
                });
            }
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

        $photoPath = null;
        if ($request->hasFile('photo')) {
            try {
                $path = $request->file('photo')->store('students', 'public');
                $photoPath = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip photo upload if it fails
            }
        }

        // 1. Create Student record first (no name or photo column in students table)
        $student = Student::create([
            'class_id' => $fields['class_id'],
            'gender' => $fields['gender'] ?? null,
        ]);

        // 2. Automatically create a user account for the student
        try {
            $cleanedName = strtolower(str_replace(' ', '', $fields['name']));
            $user = User::create([
                'name' => $fields['name'],
                'name_kh' => $fields['name_kh'] ?? null,
                'email' => $cleanedName . $student->id . '@score.com',
                'password' => Hash::make('password'),
                'role' => 'student',
                'avatar' => $photoPath,
                'is_active' => true,
            ]);
            $student->update(['user_id' => $user->id]);
        } catch (\Exception $e) {
            // Skip user creation if it fails
        }

        return response()->json([
            'message' => 'Student created successfully.',
            'student' => $student->load(['class', 'user'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if ($user && $user->role === 'student' && $user->student_id != $id) {
            return response()->json(['message' => 'Unauthorized to view other students.'], 403);
        }

        $student = Student::with(['class', 'scores.subject', 'user'])->find($id);
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

        $photoPath = null;
        if ($request->hasFile('photo')) {
            try {
                $user = $student->user;
                $oldPhoto = $user ? $user->avatar : null;
                if ($oldPhoto) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $oldPhoto));
                }

                $path = $request->file('photo')->store('students', 'public');
                $photoPath = '/storage/' . $path;
            } catch (\Exception $e) {
                // Skip photo upload if it fails
            }
        }

        // Update student fields
        $studentData = [];
        if (isset($fields['class_id'])) {
            $studentData['class_id'] = $fields['class_id'];
        }
        if (array_key_exists('gender', $fields)) {
            $studentData['gender'] = $fields['gender'];
        }
        $student->update($studentData);

        // Update user fields
        $user = $student->user;
        if ($user) {
            $userData = [];
            if (isset($fields['name'])) {
                $userData['name'] = $fields['name'];
            }
            if (array_key_exists('name_kh', $fields)) {
                $userData['name_kh'] = $fields['name_kh'];
            }
            if ($photoPath) {
                $userData['avatar'] = $photoPath;
            }
            if (isset($fields['is_active'])) {
                $userData['is_active'] = $fields['is_active'];
            }
            if (!empty($userData)) {
                $user->update($userData);
            }
        }

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->load(['class', 'user'])
        ]);
    }

    public function destroy($id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $user = $student->user;
        if ($user) {
            if ($user->avatar) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }
            $user->delete();
        }

        $student->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }
}
